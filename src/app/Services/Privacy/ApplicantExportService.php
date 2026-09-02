<?php

namespace App\Services\Privacy;

use App\Models\DataExport;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Services\Security\PrivacyAuditService;
use App\Support\PrivacySecurityPermissions;
use FilesystemIterator;
use Illuminate\Database\Query\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use ZipArchive;

final class ApplicantExportService
{
    public function __construct(
        private readonly PrivacyAuditService $audit,
        private readonly PrivateExportPermissions $permissions,
    ) {}

    /** @param list<string> $scope */
    public function authorize(User $actor, PrivacyRequest $request, array $scope): DataExport
    {
        $scope = array_values(array_unique($scope));
        if ($scope === [] || array_diff($scope, DataExport::ALLOWED_SCOPES) !== []) {
            throw ValidationException::withMessages(['scope' => 'The export scope is invalid.']);
        }

        return DB::transaction(function () use ($actor, $request, $scope): DataExport {
            $controller = $this->currentController($actor, PrivacySecurityPermissions::PRIVACY_EXPORTS_AUTHORIZE);
            $locked = PrivacyRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->assertDisclosureAllowed($locked);
            $export = DataExport::query()->create([
                'privacy_request_id' => $locked->id,
                'subject_user_id' => $locked->subject_user_id,
                'requested_by_user_id' => $controller->id,
                'approved_by_user_id' => $controller->id,
                'status' => DataExport::STATUS_AUTHORIZED,
                'scope_manifest' => $scope,
                'authorized_scope_hash' => $this->scopeHash($scope),
                'authorized_at' => now(),
            ]);
            $this->audit->record(event: 'data_export_authorized', actor: $controller, resource: $export, subjectUserId: $locked->subject_user_id, metadata: ['export_status' => $export->status, 'scope_count' => count($scope)]);

            return $export;
        });
    }

    public function reauthorize(User $actor, DataExport $export): DataExport
    {
        return DB::transaction(function () use ($actor, $export): DataExport {
            $controller = $this->currentController($actor, PrivacySecurityPermissions::PRIVACY_EXPORTS_AUTHORIZE);
            $current = DataExport::query()->lockForUpdate()->findOrFail($export->id);
            if (! in_array($current->status, [DataExport::STATUS_AUTHORIZED, DataExport::STATUS_FAILED, DataExport::STATUS_READY], true)
                || ($current->status === DataExport::STATUS_READY && ! $current->expires_at?->isFuture())) {
                throw ValidationException::withMessages(['export' => 'This export cannot be reauthorized from its current state.']);
            }
            $request = PrivacyRequest::query()->lockForUpdate()->findOrFail($current->privacy_request_id);
            $this->assertExportBindingAndScope($current, $request);
            $this->assertDisclosureAllowed($request);
            $current->forceFill([
                'approved_by_user_id' => $controller->id,
                'authorized_at' => now(),
                'authorized_scope_hash' => $this->scopeHash($current->scope_manifest),
            ])->save();
            $this->audit->record(event: 'data_export_reauthorized', actor: $controller, resource: $current, subjectUserId: $current->subject_user_id, metadata: ['export_status' => $current->status, 'scope_count' => count($current->scope_manifest)]);

            return $current->fresh();
        });
    }

    public function recordGenerationInitiated(User $actor, DataExport $export): DataExport
    {
        return DB::transaction(function () use ($actor, $export): DataExport {
            $controller = $this->currentController($actor, PrivacySecurityPermissions::PRIVACY_EXPORTS_GENERATE);
            $current = DataExport::query()->lockForUpdate()->findOrFail($export->id);
            if ($current->status !== DataExport::STATUS_AUTHORIZED) {
                throw ValidationException::withMessages(['export' => 'This export cannot be queued for generation.']);
            }
            $request = PrivacyRequest::query()->lockForUpdate()->findOrFail($current->privacy_request_id);
            $this->assertExportAuthority($current, $request);
            $current->forceFill(['generation_initiated_by_user_id' => $controller->id])->save();
            $this->audit->record(event: 'data_export_generation_queued', actor: $controller, resource: $current, subjectUserId: $current->subject_user_id, metadata: ['export_status' => $current->status, 'scope_count' => count($current->scope_manifest)]);

            return $current->fresh();
        });
    }

    public function retryFailedGeneration(User $actor, DataExport $export): DataExport
    {
        return DB::transaction(function () use ($actor, $export): DataExport {
            $controller = $this->currentController($actor, PrivacySecurityPermissions::PRIVACY_EXPORTS_GENERATE);
            $current = DataExport::query()->lockForUpdate()->findOrFail($export->id);
            if ($current->status !== DataExport::STATUS_FAILED) {
                throw ValidationException::withMessages(['export' => 'Only a failed export can be explicitly retried.']);
            }
            $request = PrivacyRequest::query()->lockForUpdate()->findOrFail($current->privacy_request_id);
            $this->assertExportAuthority($current, $request);
            $current->forceFill([
                'status' => DataExport::STATUS_AUTHORIZED,
                'generation_initiated_by_user_id' => $controller->id,
                'generation_token' => null,
                'generation_started_at' => null,
                'failure_code' => null,
                'private_path' => null,
                'sha256' => null,
                'artifact_scope_hash' => null,
                'generated_at' => null,
                'expires_at' => null,
            ])->save();
            $this->audit->record(event: 'data_export_generation_retried', actor: $controller, resource: $current, subjectUserId: $current->subject_user_id, metadata: ['export_status' => $current->status, 'scope_count' => count($current->scope_manifest)]);

            return $current->fresh();
        });
    }

    public function generate(DataExport $export): DataExport
    {
        $claim = DB::transaction(function () use ($export): ?array {
            $current = DataExport::query()->lockForUpdate()->findOrFail($export->id);
            $staleToken = null;

            if ($current->status === DataExport::STATUS_GENERATING) {
                if (! $this->isGenerationStale($current) || $current->expires_at?->isPast()) {
                    return null;
                }
                $staleToken = is_string($current->generation_token) && Str::isUuid($current->generation_token)
                    ? $current->generation_token
                    : null;
            } elseif ($current->status !== DataExport::STATUS_AUTHORIZED) {
                return null;
            }

            $request = PrivacyRequest::query()->lockForUpdate()->findOrFail($current->privacy_request_id);
            $this->assertExportAuthority($current, $request);
            if (! $current->generation_initiated_by_user_id) {
                throw ValidationException::withMessages(['export' => 'Export generation has not been initiated by an authorized administrator.']);
            }

            $token = (string) Str::uuid();
            $current->forceFill([
                'status' => DataExport::STATUS_GENERATING,
                'generation_token' => $token,
                'generation_attempt' => ((int) $current->generation_attempt) + 1,
                'generation_started_at' => now(),
                'failure_code' => null,
                'private_path' => null,
                'sha256' => null,
                'artifact_scope_hash' => null,
                'generated_at' => null,
                'expires_at' => null,
            ])->save();

            return ['export' => $current->fresh(), 'token' => $token, 'stale_token' => $staleToken];
        });

        if ($claim === null) {
            return DataExport::query()->findOrFail($export->id);
        }

        /** @var DataExport $claimed */
        $claimed = $claim['export'];
        $token = $claim['token'];
        $staleToken = $claim['stale_token'];
        $disk = $this->disk();
        $finalPath = $this->canonicalPath($claimed);
        $claimDirectory = $this->claimStagingDirectory($claimed, $token);
        $temporaryPath = $claimDirectory.'/archive.tmp';
        $temporaryAbsolutePath = $disk->path($temporaryPath);
        $finalAbsolutePath = $disk->path($finalPath);
        $published = false;
        $zip = null;
        $zipClosed = false;

        try {
            $this->preparePrivateDirectory($disk, $this->canonicalDirectory($claimed));
            if (is_string($staleToken)) {
                $this->cleanupClaimStaging($claimed, $staleToken);
            }
            $this->removeUntrustedCanonicalArtifact($claimed);
            $this->prepareClaimStagingDirectory($claimed, $token);
            $this->createSecureEmptyFile($temporaryAbsolutePath, 'temporary export artifact');

            $zip = new ZipArchive;
            if ($zip->open($temporaryAbsolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to initialize private export archive.');
            }
            $this->permissions->enforce($temporaryAbsolutePath, 0600, 'temporary export artifact');
            $this->populateArchive($zip, $claimed, $disk->path($claimDirectory));
            if (! $zip->close()) {
                throw new RuntimeException('Unable to finalize private export archive.');
            }
            $zipClosed = true;
            $this->permissions->enforce($temporaryAbsolutePath, 0600, 'temporary export artifact');

            return DB::transaction(function () use ($claimed, $token, $finalPath, $temporaryAbsolutePath, $finalAbsolutePath, &$published): DataExport {
                $current = DataExport::query()->lockForUpdate()->findOrFail($claimed->id);
                $request = PrivacyRequest::query()->lockForUpdate()->findOrFail($current->privacy_request_id);
                $this->assertCurrentClaim($current, $token);
                $this->assertExportAuthority($current, $request);
                $this->assertCanonicalPath($current, $finalPath);
                if (! rename($temporaryAbsolutePath, $finalAbsolutePath)) {
                    throw new RuntimeException('Unable to atomically publish private export archive.');
                }
                $published = true;
                $this->permissions->enforce($finalAbsolutePath, 0600, 'published export artifact');
                $sha256 = hash_file('sha256', $finalAbsolutePath);
                if (! is_string($sha256)) {
                    throw new RuntimeException('Unable to hash private export archive.');
                }

                $current->forceFill([
                    'status' => DataExport::STATUS_READY,
                    'private_path' => $finalPath,
                    'sha256' => $sha256,
                    'artifact_scope_hash' => $this->scopeHash($current->scope_manifest),
                    'generated_at' => now(),
                    'expires_at' => now()->addHours(max(1, (int) config('privacy.exports.expiry_hours', 72))),
                    'generation_token' => null,
                    'failure_code' => null,
                ])->save();
                $this->audit->record(event: 'data_export_generated', actor: $current->generationInitiatedBy, resource: $current, subjectUserId: $current->subject_user_id, metadata: ['export_status' => $current->status, 'scope_count' => count($current->scope_manifest)]);

                return $current->fresh();
            });
        } catch (Throwable $exception) {
            if ($zip instanceof ZipArchive && ! $zipClosed) {
                $zip->close();
            }
            DB::transaction(function () use ($claimed, $token, $published): void {
                $current = DataExport::query()->lockForUpdate()->find($claimed->id);
                if ($current?->status === DataExport::STATUS_GENERATING && hash_equals((string) $current->generation_token, $token)) {
                    if ($published) {
                        $this->quarantineUntrustedCanonicalArtifact($current, $token);
                    }
                    $current->forceFill([
                        'status' => DataExport::STATUS_FAILED,
                        'private_path' => null,
                        'sha256' => null,
                        'artifact_scope_hash' => null,
                        'generation_token' => null,
                        'failure_code' => 'generation_failed',
                    ])->save();
                }
            });
            throw $exception;
        } finally {
            $this->cleanupClaimStaging($claimed, $token);
        }
    }

    public function recordDownload(User $actor, DataExport $export): DataExport
    {
        $downloadable = DB::transaction(function () use ($actor, $export): ?DataExport {
            $downloader = $this->currentController($actor, PrivacySecurityPermissions::PRIVACY_EXPORTS_DOWNLOAD);
            $current = DataExport::query()->lockForUpdate()->findOrFail($export->id);
            $request = PrivacyRequest::query()->lockForUpdate()->findOrFail($current->privacy_request_id);
            if ($current->status === DataExport::STATUS_READY && $current->expires_at?->isPast()) {
                $this->markExpired($current);

                return null;
            }
            if ($current->status !== DataExport::STATUS_READY || ! $current->expires_at?->isFuture() || ! $current->private_path || ! $current->sha256 || ! $current->artifact_scope_hash) {
                throw ValidationException::withMessages(['export' => 'The private export is unavailable or expired.']);
            }
            $this->assertExportAuthority($current, $request);
            $this->assertCanonicalPath($current, $current->private_path);
            $path = $this->disk()->path($current->private_path);
            $actualHash = $this->disk()->exists($current->private_path) ? hash_file('sha256', $path) : false;
            if (! hash_equals($this->scopeHash($current->scope_manifest), $current->artifact_scope_hash) || ! is_string($actualHash) || ! hash_equals($current->sha256, $actualHash)) {
                throw ValidationException::withMessages(['export' => 'The private export failed its integrity check.']);
            }

            $current->forceFill(['downloaded_at' => now()])->save();
            $this->audit->record(event: 'data_export_downloaded', actor: $downloader, resource: $current, subjectUserId: $current->subject_user_id, metadata: ['export_status' => $current->status]);

            return $current->fresh();
        });

        if ($downloadable === null) {
            throw ValidationException::withMessages(['export' => 'The private export is unavailable or expired.']);
        }

        return $downloadable;
    }

    public function expire(DataExport $export): bool
    {
        return DB::transaction(function () use ($export): bool {
            $current = DataExport::query()->lockForUpdate()->findOrFail($export->id);
            if ($current->status !== DataExport::STATUS_READY || ! $current->expires_at?->isPast()) {
                return false;
            }
            $this->markExpired($current);

            return true;
        });
    }

    public function purge(DataExport $export): bool
    {
        $claimed = DB::transaction(function () use ($export): ?DataExport {
            $current = DataExport::query()->lockForUpdate()->findOrFail($export->id);
            if ($current->status === DataExport::STATUS_PURGED) {
                return null;
            }
            if ($current->status !== DataExport::STATUS_PURGING) {
                if ($current->status !== DataExport::STATUS_EXPIRED) {
                    return null;
                }
                if ($current->private_path) {
                    $this->assertCanonicalPath($current, $current->private_path);
                }
                $current->forceFill(['status' => DataExport::STATUS_PURGING])->save();
            }

            return $current->fresh();
        });

        if (! $claimed) {
            return false;
        }
        if ($claimed->private_path) {
            $this->assertCanonicalPath($claimed, $claimed->private_path);
            if ($this->disk()->exists($claimed->private_path) && ! $this->disk()->delete($claimed->private_path)) {
                return false;
            }
        }

        return DB::transaction(function () use ($claimed): bool {
            $current = DataExport::query()->lockForUpdate()->findOrFail($claimed->id);
            if ($current->status === DataExport::STATUS_PURGED) {
                return false;
            }
            if ($current->status !== DataExport::STATUS_PURGING) {
                throw new RuntimeException('The export purge claim is no longer valid.');
            }
            $current->forceFill(['status' => DataExport::STATUS_PURGED, 'private_path' => null, 'sha256' => null, 'purged_at' => now()])->save();
            $this->audit->record(event: 'data_export_purged', resource: $current, subjectUserId: $current->subject_user_id, metadata: ['export_status' => $current->status]);

            return true;
        });
    }

    public function reconciliationCondition(DataExport $export): ?string
    {
        $current = $export->fresh();
        $canonicalExists = $this->canonicalArtifactExists($current);

        if ($current->status === DataExport::STATUS_GENERATING && $this->isGenerationStale($current) && $canonicalExists) {
            return 'stale_generating_with_canonical';
        }
        if ($current->status === DataExport::STATUS_GENERATING && $this->isGenerationStale($current)) {
            return 'stale_generating';
        }
        if ($current->status === DataExport::STATUS_GENERATING && $canonicalExists) {
            return 'generating_canonical_pending';
        }
        if ($current->status === DataExport::STATUS_READY && $current->expires_at?->isPast()) {
            return 'ready_past_expiry';
        }
        if ($current->status === DataExport::STATUS_READY
            && ($current->private_path !== $this->canonicalPath($current) || ! $canonicalExists)) {
            return 'ready_missing_artifact';
        }
        if ($current->status === DataExport::STATUS_AUTHORIZED && $canonicalExists) {
            return 'orphan_canonical_authorized';
        }
        if ($current->status === DataExport::STATUS_FAILED && $canonicalExists) {
            return 'orphan_canonical_failed';
        }
        if ($current->status === DataExport::STATUS_PURGED && $canonicalExists) {
            return 'orphan_canonical_purged';
        }
        if ($current->status === DataExport::STATUS_EXPIRED) {
            return 'expired_pending_purge';
        }
        if ($current->status === DataExport::STATUS_PURGING) {
            return 'purging_pending_cleanup';
        }
        if ($current->status === DataExport::STATUS_FAILED && $this->stagingHasEntries($current)) {
            return 'failed_generation_debris';
        }
        if ($current->status !== DataExport::STATUS_GENERATING && $this->stagingHasEntries($current)) {
            return 'orphan_staging';
        }

        return null;
    }

    public function reconcileDeterministically(DataExport $export, string $condition): void
    {
        match ($condition) {
            'stale_generating_with_canonical' => $this->failStaleGeneration($export),
            'stale_generating' => $this->failStaleGeneration($export),
            'generating_canonical_pending' => null,
            'ready_past_expiry' => $this->expire($export),
            'expired_pending_purge' => $this->purge($export),
            'purging_pending_cleanup' => $this->purge($export),
            'orphan_canonical_authorized' => $this->cleanupOrphanCanonicalArtifact($export, DataExport::STATUS_AUTHORIZED),
            'orphan_canonical_failed' => $this->cleanupFailedGenerationArtifacts($export),
            'orphan_canonical_purged' => $this->cleanupOrphanCanonicalArtifact($export, DataExport::STATUS_PURGED),
            'failed_generation_debris' => $this->cleanupFailedGenerationArtifacts($export),
            'orphan_staging' => $this->cleanupOrphanStaging($export),
            'ready_missing_artifact' => $this->cleanupOrphanStaging($export),
            default => throw new RuntimeException('Unknown private export reconciliation condition.'),
        };
    }

    private function failStaleGeneration(DataExport $export): void
    {
        $claim = DB::transaction(function () use ($export): ?array {
            $current = DataExport::query()->lockForUpdate()->findOrFail($export->id);
            if ($current->status !== DataExport::STATUS_GENERATING || ! $this->isGenerationStale($current)) {
                return null;
            }
            $token = is_string($current->generation_token) && Str::isUuid($current->generation_token) ? $current->generation_token : null;
            $quarantineToken = $token ?? (string) Str::uuid();
            $quarantined = $this->quarantineUntrustedCanonicalArtifact($current, $quarantineToken);
            $current->forceFill(['status' => DataExport::STATUS_FAILED, 'generation_token' => null, 'failure_code' => 'generation_lease_expired'])->save();
            $this->audit->record(event: 'data_export_generation_reconciled', resource: $current, subjectUserId: $current->subject_user_id, metadata: ['export_status' => $current->status]);

            return [
                'export' => $current->fresh(),
                'generation_token' => $token,
                'quarantine_token' => $quarantined ? $quarantineToken : null,
            ];
        });

        if ($claim === null) {
            return;
        }
        $cleanupTokens = array_unique(array_filter([
            $claim['generation_token'],
            $claim['quarantine_token'],
        ], 'is_string'));
        foreach ($cleanupTokens as $cleanupToken) {
            $this->cleanupClaimStaging($claim['export'], $cleanupToken);
        }
    }

    private function cleanupFailedGenerationArtifacts(DataExport $export): void
    {
        DB::transaction(function () use ($export): void {
            $current = DataExport::query()->lockForUpdate()->findOrFail($export->id);
            if ($current->status !== DataExport::STATUS_FAILED) {
                return;
            }
            $this->cleanupAllStaging($current);
            $this->removeUntrustedCanonicalArtifact($current);
        });
    }

    private function cleanupOrphanCanonicalArtifact(DataExport $export, string $expectedStatus): void
    {
        DB::transaction(function () use ($export, $expectedStatus): void {
            $current = DataExport::query()->lockForUpdate()->findOrFail($export->id);
            if ($current->status !== $expectedStatus) {
                return;
            }
            $this->removeUntrustedCanonicalArtifact($current);
        });
    }

    private function cleanupOrphanStaging(DataExport $export): void
    {
        DB::transaction(function () use ($export): void {
            $current = DataExport::query()->lockForUpdate()->findOrFail($export->id);
            if ($current->status === DataExport::STATUS_GENERATING) {
                return;
            }
            $this->cleanupAllStaging($current);
        });
    }

    private function markExpired(DataExport $export): void
    {
        $export->forceFill(['status' => DataExport::STATUS_EXPIRED])->save();
        $this->audit->record(event: 'data_export_expired', resource: $export, subjectUserId: $export->subject_user_id, metadata: ['export_status' => $export->status]);
    }

    private function assertCurrentClaim(DataExport $export, string $token): void
    {
        if ($export->status !== DataExport::STATUS_GENERATING || ! is_string($export->generation_token) || ! hash_equals($export->generation_token, $token)) {
            throw ValidationException::withMessages(['export' => 'The export generation claim is no longer valid.']);
        }
    }

    private function assertExportAuthority(DataExport $export, PrivacyRequest $request): void
    {
        $this->assertExportBindingAndScope($export, $request);
        $authorizer = User::query()->find($export->approved_by_user_id);
        if (! $authorizer || ! $authorizer->hasRole('admin') || ! $authorizer->can(PrivacySecurityPermissions::PRIVACY_EXPORTS_AUTHORIZE)) {
            throw ValidationException::withMessages(['export' => 'The controller authorization for this export is no longer valid.']);
        }
        $this->assertDisclosureAllowed($request);
    }

    private function assertExportBindingAndScope(DataExport $export, PrivacyRequest $request): void
    {
        if ($export->privacy_request_id !== $request->id
            || $export->subject_user_id !== $request->subject_user_id
            || ! $export->approved_by_user_id
            || $export->scope_manifest === []
            || array_diff($export->scope_manifest, DataExport::ALLOWED_SCOPES) !== []
            || ! is_string($export->authorized_scope_hash)
            || ! hash_equals($export->authorized_scope_hash, $this->scopeHash($export->scope_manifest))) {
            throw ValidationException::withMessages(['export' => 'Export authorization is no longer valid.']);
        }
    }

    private function assertDisclosureAllowed(PrivacyRequest $request): void
    {
        if ($request->identity_verification_state !== PrivacyRequest::IDENTITY_VERIFIED || ! in_array($request->state, [PrivacyRequest::STATE_APPROVED, PrivacyRequest::STATE_PARTIALLY_APPROVED], true) || ! $request->controller_decision_code) {
            throw ValidationException::withMessages(['export' => 'An approved, identity-verified request is required for disclosure.']);
        }
    }

    private function currentController(User $actor, string $permission): User
    {
        $current = User::query()->find($actor->id);
        if (! $current || ! $current->hasRole('admin') || ! $current->can($permission)) {
            throw ValidationException::withMessages(['authorization' => 'A current authorized controller administrator is required.']);
        }

        return $current;
    }

    private function isGenerationStale(DataExport $export): bool
    {
        return $export->generation_started_at === null
            || $export->generation_started_at->lte(now()->subMinutes(max(1, (int) config('privacy.exports.generation_stale_minutes', 10))));
    }

    private function canonicalDirectory(DataExport $export): string
    {
        if (! is_string($export->uuid) || ! Str::isUuid($export->uuid)) {
            throw new RuntimeException('The export identifier is not a valid UUID.');
        }
        $configured = (string) config('privacy.exports.directory', 'privacy-exports');
        $root = trim($configured, '/');
        if ($root === '' || str_contains($root, '..') || str_contains($root, '\\') || str_starts_with($configured, '/')) {
            throw new RuntimeException('The private export directory configuration is invalid.');
        }

        return $root.'/'.$export->uuid;
    }

    private function canonicalPath(DataExport $export): string
    {
        return $this->canonicalDirectory($export).'/'.$export->uuid.'.zip';
    }

    private function stagingRoot(DataExport $export): string
    {
        return $this->canonicalDirectory($export).'/staging';
    }

    private function claimStagingDirectory(DataExport $export, string $token): string
    {
        if (! Str::isUuid($token)) {
            throw new RuntimeException('The export generation claim token is invalid.');
        }

        return $this->stagingRoot($export).'/'.$token;
    }

    private function assertCanonicalPath(DataExport $export, string $path): void
    {
        if ($path !== $this->canonicalPath($export) || str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, '\\')) {
            throw new RuntimeException('The stored export path is not canonical.');
        }
        $this->assertPhysicalPathConfined($this->disk()->path($path));
    }

    private function preparePrivateDirectory(FilesystemAdapter $disk, string $directory): void
    {
        $root = dirname($directory);
        if (! $disk->makeDirectory($directory) && ! $disk->directoryExists($directory)) {
            throw new RuntimeException('Unable to create the private export directory.');
        }
        $this->assertPhysicalPathConfined($disk->path($directory.'/placeholder'));
        $this->permissions->enforce($disk->path($root), 0700, 'private export root');
        $this->permissions->enforce($disk->path($directory), 0700, 'private export directory');
    }

    private function prepareClaimStagingDirectory(DataExport $export, string $token): void
    {
        $disk = $this->disk();
        $stagingRoot = $this->stagingRoot($export);
        $claimDirectory = $this->claimStagingDirectory($export, $token);
        if (! $disk->makeDirectory($claimDirectory) && ! $disk->directoryExists($claimDirectory)) {
            throw new RuntimeException('Unable to create claim-scoped export staging.');
        }
        $this->assertPhysicalPathConfined($disk->path($claimDirectory.'/placeholder'));
        $this->permissions->enforce($disk->path($stagingRoot), 0700, 'private export staging root');
        $this->permissions->enforce($disk->path($claimDirectory), 0700, 'private export claim staging directory');
    }

    private function createSecureEmptyFile(string $path, string $description): void
    {
        $stream = fopen($path, 'x+b');
        if ($stream === false) {
            throw new RuntimeException("Unable to initialize {$description}.");
        }
        fclose($stream);
        $this->permissions->enforce($path, 0600, $description);
    }

    private function removeUntrustedCanonicalArtifact(DataExport $export): void
    {
        $path = $this->canonicalPath($export);
        $this->assertCanonicalPath($export, $path);
        if ($this->disk()->exists($path) && ! $this->disk()->delete($path)) {
            throw new RuntimeException('Unable to remove an unpublished export artifact.');
        }
    }

    private function quarantineUntrustedCanonicalArtifact(DataExport $export, string $ownershipToken): bool
    {
        $path = $this->canonicalPath($export);
        $this->assertCanonicalPath($export, $path);
        $source = $this->disk()->path($path);
        if (! file_exists($source) && ! is_link($source)) {
            return false;
        }
        if (! is_file($source) || is_link($source)) {
            throw new RuntimeException('The unpublished export artifact is not a regular private file.');
        }

        $this->prepareClaimStagingDirectory($export, $ownershipToken);
        $quarantinePath = $this->claimStagingDirectory($export, $ownershipToken).'/canonical.quarantine';
        $quarantine = $this->disk()->path($quarantinePath);
        $this->assertPhysicalPathConfined($quarantine);
        if (file_exists($quarantine) || is_link($quarantine)) {
            throw new RuntimeException('The claim-scoped export quarantine already exists.');
        }
        if (! rename($source, $quarantine)) {
            throw new RuntimeException('Unable to quarantine an unpublished export artifact.');
        }
        $this->permissions->enforce($quarantine, 0600, 'quarantined export artifact');

        return true;
    }

    private function canonicalArtifactExists(DataExport $export): bool
    {
        $path = $this->canonicalPath($export);
        $this->assertCanonicalPath($export, $path);

        return $this->disk()->exists($path);
    }

    private function cleanupClaimStaging(DataExport $export, string $token): void
    {
        $this->deleteCanonicalStagingDirectory($export, $this->claimStagingDirectory($export, $token));
        $this->removeEmptyStagingRoot($export);
    }

    private function cleanupAllStaging(DataExport $export): void
    {
        $this->deleteCanonicalStagingDirectory($export, $this->stagingRoot($export));
    }

    private function stagingHasEntries(DataExport $export): bool
    {
        $absolute = $this->disk()->path($this->stagingRoot($export));
        if (! is_dir($absolute) || is_link($absolute)) {
            return file_exists($absolute) || is_link($absolute);
        }

        return (new FilesystemIterator($absolute, FilesystemIterator::SKIP_DOTS))->valid();
    }

    private function removeEmptyStagingRoot(DataExport $export): void
    {
        $absolute = $this->disk()->path($this->stagingRoot($export));
        if (! is_dir($absolute) || is_link($absolute)) {
            return;
        }
        $iterator = new FilesystemIterator($absolute, FilesystemIterator::SKIP_DOTS);
        if (! $iterator->valid() && ! rmdir($absolute)) {
            throw new RuntimeException('Unable to remove the empty export staging root.');
        }
    }

    private function deleteCanonicalStagingDirectory(DataExport $export, string $directory): void
    {
        $stagingRoot = $this->stagingRoot($export);
        if ($directory !== $stagingRoot && ! str_starts_with($directory, $stagingRoot.'/')) {
            throw new RuntimeException('The staging cleanup path is not canonical.');
        }
        $absolute = $this->disk()->path($directory);
        if (! file_exists($absolute) && ! is_link($absolute)) {
            return;
        }
        $this->assertPhysicalPathConfined($absolute.'/placeholder');
        $this->deleteDirectoryWithoutFollowingLinks($absolute);
    }

    private function deleteDirectoryWithoutFollowingLinks(string $directory): void
    {
        if (is_link($directory)) {
            if (! unlink($directory)) {
                throw new RuntimeException('Unable to remove a staging symbolic link safely.');
            }

            return;
        }
        if (! is_dir($directory)) {
            if (file_exists($directory) && ! unlink($directory)) {
                throw new RuntimeException('Unable to remove an invalid staging artifact.');
            }

            return;
        }
        foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $entry) {
            $path = $entry->getPathname();
            if ($entry->isLink() || ! $entry->isDir()) {
                if (! unlink($path)) {
                    throw new RuntimeException('Unable to remove a claim-scoped staging artifact.');
                }
            } else {
                $this->deleteDirectoryWithoutFollowingLinks($path);
            }
        }
        if (! rmdir($directory)) {
            throw new RuntimeException('Unable to remove claim-scoped export staging.');
        }
    }

    private function assertPhysicalPathConfined(string $absolutePath): void
    {
        $diskRootPath = rtrim($this->disk()->path(''), DIRECTORY_SEPARATOR);
        $diskRoot = realpath($diskRootPath);
        if ($diskRoot === false) {
            throw new RuntimeException('The export path escapes the private disk.');
        }
        if ($absolutePath !== $diskRootPath && ! str_starts_with($absolutePath, $diskRootPath.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The export path escapes the private disk.');
        }
        $relative = ltrim(substr($absolutePath, strlen($diskRootPath)), DIRECTORY_SEPARATOR);
        $current = $diskRootPath;
        foreach ($relative === '' ? [] : explode(DIRECTORY_SEPARATOR, $relative) as $segment) {
            $current .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($current)) {
                throw new RuntimeException('The stored export path uses a symbolic link.');
            }
            if (! file_exists($current)) {
                break;
            }
            $resolved = realpath($current);
            if ($resolved === false || ($resolved !== $diskRoot && ! str_starts_with($resolved, $diskRoot.DIRECTORY_SEPARATOR))) {
                throw new RuntimeException('The export path escapes the private disk.');
            }
        }
    }

    private function populateArchive(ZipArchive $zip, DataExport $export, string $workDirectory): void
    {
        $scope = $export->scope_manifest;
        $user = DB::table('users')->where('id', $export->subject_user_id)->first(['id', 'name', 'email', 'email_verified_at', 'created_at']);
        if (! $user) {
            throw new RuntimeException('The export subject no longer exists.');
        }

        $this->addString($zip, 'manifest.json', $this->json([
            'format_version' => 1,
            'export_reference' => $export->uuid,
            'request_reference' => PrivacyRequest::query()->whereKey($export->privacy_request_id)->value('uuid'),
            'generated_at' => now()->toIso8601String(),
            'scope' => $scope,
            'excluded_by_default' => ['documents', 'passport', 'drivers_license', 'police_record', 'medical_record', 'password_and_authentication_secrets', 'session_data', 'raw_payment_payloads', 'internal_audit_metadata'],
        ]));
        $this->addString($zip, 'README.txt', "Kairox applicant data export\n\nThis archive contains only the controller-authorized structured scope listed in manifest.json. High-risk documents and authentication/security secrets are excluded.\n");

        if (in_array('account', $scope, true)) {
            $this->addString($zip, 'account.json', $this->json(['name' => $user->name, 'email' => $user->email, 'email_verified_at' => $user->email_verified_at, 'created_at' => $user->created_at]));
        }
        if (in_array('profile', $scope, true)) {
            $profile = DB::table('job_seekers')->leftJoin('programs', 'programs.id', '=', 'job_seekers.program_id')->where('job_seekers.user_id', $user->id)->first(['programs.name as program', 'job_seekers.date_of_birth', 'job_seekers.location', 'job_seekers.phone', 'job_seekers.education', 'job_seekers.experience_summary', 'job_seekers.skills', 'job_seekers.work_study_interest_flag']);
            $this->addString($zip, 'profile.json', $this->json($profile));
        }
        if (in_array('applications', $scope, true)) {
            $this->addChunkedDataset($zip, $workDirectory, 'applications.json', DB::table('applications')->join('job_seekers', 'job_seekers.id', '=', 'applications.job_seeker_id')->leftJoin('jobs', 'jobs.id', '=', 'applications.job_id')->where('job_seekers.user_id', $user->id), 'applications.id', ['applications.id as reference', 'jobs.title as job_title', 'applications.status', 'applications.applied_at']);
        }
        if (in_array('payments', $scope, true)) {
            $this->addChunkedDataset($zip, $workDirectory, 'payments.json', DB::table('payments')->where('user_id', $user->id), 'payments.id', ['payments.id as chunk_id', 'order_id as order_reference', 'gateway', 'amount', 'currency', 'status', 'paid_at'], 'chunk_id');
        }
        if (in_array('entitlements', $scope, true)) {
            $this->addChunkedDataset($zip, $workDirectory, 'entitlements.json', DB::table('entitlements')->where('user_id', $user->id), 'entitlements.id', ['entitlements.id as chunk_id', 'type', 'status', 'starts_at', 'expires_at'], 'chunk_id');
        }
        if (in_array('policy_evidence', $scope, true)) {
            $this->addChunkedDataset($zip, $workDirectory, 'policy_evidence.json', DB::table('policy_acknowledgements')->join('policy_documents', 'policy_documents.id', '=', 'policy_acknowledgements.policy_document_id')->where('policy_acknowledgements.user_id', $user->id), 'policy_acknowledgements.id', ['policy_acknowledgements.id as chunk_id', 'policy_documents.type as policy_type', 'policy_documents.version as policy_version', 'policy_documents.canonical_reference_hash', 'policy_acknowledgements.acknowledgement_type', 'policy_acknowledgements.acknowledged_at'], 'chunk_id');
        }
        if (in_array('privacy_requests', $scope, true)) {
            $this->addChunkedDataset($zip, $workDirectory, 'privacy_requests.json', DB::table('privacy_requests')->where('subject_user_id', $user->id), 'privacy_requests.id', ['privacy_requests.id as chunk_id', 'uuid as reference', 'request_type as type', 'state', 'submitted_at', 'completed_at'], 'chunk_id');
        }
    }

    /** @param list<string> $columns */
    private function addChunkedDataset(ZipArchive $zip, string $directory, string $archiveName, Builder $query, string $chunkColumn, array $columns, ?string $removeKey = null): void
    {
        $path = $directory.'/'.str_replace('.json', '', $archiveName).'.json.tmp';
        $stream = fopen($path, 'x+b');
        if ($stream === false) {
            throw new RuntimeException('Unable to initialize a minimized export dataset.');
        }

        try {
            $this->permissions->enforce($path, 0600, 'private export staging file');
            $this->writeToStream($stream, "[\n");
            $first = true;
            $query->select($columns)->orderBy($chunkColumn)->chunkById(250, function ($rows) use ($stream, &$first, $removeKey): void {
                foreach ($rows as $row) {
                    $value = (array) $row;
                    if ($removeKey) {
                        unset($value[$removeKey]);
                    }
                    $this->writeToStream($stream, ($first ? '' : ",\n").json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                    $first = false;
                }
            }, $chunkColumn, $removeKey ?? 'reference');
            $this->writeToStream($stream, "\n]\n");
        } finally {
            fclose($stream);
        }

        $this->permissions->enforce($path, 0600, 'private export staging file');
        if (! $zip->addFile($path, $archiveName)) {
            throw new RuntimeException('Unable to add a minimized export dataset.');
        }
    }

    private function addString(ZipArchive $zip, string $name, string $contents): void
    {
        if (! $zip->addFromString($name, $contents)) {
            throw new RuntimeException('Unable to add an approved export dataset.');
        }
    }

    /** @param resource $stream */
    private function writeToStream($stream, string $contents): void
    {
        $remaining = $contents;
        while ($remaining !== '') {
            $written = fwrite($stream, $remaining);
            if ($written === false || $written === 0) {
                throw new RuntimeException('Unable to write a minimized export dataset.');
            }
            $remaining = substr($remaining, $written);
        }
    }

    /** @param list<string> $scope */
    private function scopeHash(array $scope): string
    {
        sort($scope);

        return hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR));
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk((string) config('privacy.exports.disk', 'private'));
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    }
}
