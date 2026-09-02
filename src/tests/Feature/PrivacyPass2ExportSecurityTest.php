<?php

namespace Tests\Feature;

use App\Jobs\GenerateApplicantExport;
use App\Models\DataExport;
use App\Models\JobSeeker;
use App\Models\JobSeekerDocument;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Services\Privacy\ApplicantExportService;
use App\Services\Privacy\PrivacyRequestWorkflow;
use App\Services\Privacy\PrivateExportPermissions;
use App\Support\PrivacySecurityPermissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

final class PrivacyPass2ExportSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('private');
        config([
            'privacy.exports.disk' => 'private',
            'privacy.exports.expiry_hours' => 24,
            'privacy.identity_verification_methods' => ['controller_approved_process'],
        ]);
    }

    public function test_export_is_blocked_before_identity_verification_and_approval(): void
    {
        Queue::fake();
        [$admin, , $privacyRequest] = $this->requestContext(false);

        try {
            app(ApplicantExportService::class)->authorize($admin, $privacyRequest, ['account']);
            $this->fail('Authorization should have been denied.');
        } catch (\Illuminate\Validation\ValidationException) {
            // Assert the durable outcome after the denial.
        }
        $this->assertDatabaseCount('data_exports', 0);
        Queue::assertNothingPushed();
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_private_export_is_subject_isolated_restrictive_idempotent_and_excludes_high_risk_and_authentication_data(): void
    {
        [$admin, $applicant, $privacyRequest] = $this->requestContext();
        $applicant->forceFill([
            'password' => 'SECRET-PASSWORD-HASH-MARKER',
            'remember_token' => 'SECRET-REMEMBER-MARKER',
            'two_factor_secret' => 'SECRET-MFA-MARKER',
            'two_factor_recovery_codes' => 'SECRET-RECOVERY-MARKER',
        ])->save();
        JobSeekerDocument::query()->create([
            'job_seeker_id' => $applicant->jobSeeker->id,
            'document_type' => JobSeekerDocument::TYPE_PASSPORT,
            'file_path' => 'applicant-documents/private-passport.pdf',
            'original_name' => 'passport-secret.pdf',
            'uploaded_at' => now(),
        ]);
        $service = app(ApplicantExportService::class);
        $export = $service->authorize($admin, $privacyRequest, DataExport::ALLOWED_SCOPES);
        $ready = $this->generateExport($service, $admin, $export);

        $this->assertSame(DataExport::STATUS_READY, $ready->status);
        $this->assertStringStartsWith('privacy-exports/', $ready->private_path);
        $this->assertStringEndsWith($ready->uuid.'.zip', $ready->private_path);
        $this->assertFalse(Storage::disk('public')->exists($ready->private_path));
        $this->assertTrue(Storage::disk('private')->exists($ready->private_path));
        $this->assertSame(0600, fileperms(Storage::disk('private')->path($ready->private_path)) & 0777);
        $this->assertSame(0700, fileperms(dirname(Storage::disk('private')->path($ready->private_path))) & 0777);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $ready->sha256);
        $this->assertSame($ready->sha256, hash_file('sha256', Storage::disk('private')->path($ready->private_path)));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('private')->path($ready->private_path)) === true);
        $contents = '';
        $names = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            $names[] = $name;
            $contents .= (string) $zip->getFromIndex($index);
        }
        $zip->close();

        $this->assertContains('manifest.json', $names);
        $this->assertContains('account.json', $names);
        $this->assertNotContains('documents.json', $names);
        $this->assertStringNotContainsString('passport-secret.pdf', $contents);
        $this->assertStringNotContainsString('SECRET-PASSWORD-HASH-MARKER', $contents);
        $this->assertStringNotContainsString('SECRET-REMEMBER-MARKER', $contents);
        $this->assertStringNotContainsString('SECRET-MFA-MARKER', $contents);
        $this->assertStringNotContainsString('SECRET-RECOVERY-MARKER', $contents);
        $this->assertStringNotContainsString('raw_payload', $contents);

        $again = $service->generate($ready);
        $this->assertSame($ready->private_path, $again->private_path);
        $this->assertSame($ready->sha256, $again->sha256);
        $this->assertDatabaseCount('data_exports', 1);
    }

    public function test_export_generation_is_queued_and_privileged_routes_require_mfa_password_and_explicit_permissions(): void
    {
        Queue::fake();
        [$admin, , $privacyRequest] = $this->requestContext();
        $export = app(ApplicantExportService::class)->authorize($admin, $privacyRequest, ['account']);

        $admin->revokePermissionTo(PrivacySecurityPermissions::PRIVACY_EXPORTS_GENERATE);
        $this->actingAsMfaVerified($admin)->post(route('admin.privacy-requests.exports.generate', [$privacyRequest, $export]))->assertForbidden();
        $admin->givePermissionTo(PrivacySecurityPermissions::PRIVACY_EXPORTS_GENERATE);
        $this->actingAsMfaVerified($admin, false)->withSession(['auth.password_confirmed_at' => 0])->post(route('admin.privacy-requests.exports.generate', [$privacyRequest, $export]))->assertRedirect(route('password.confirm'));
        $this->actingAsMfaVerified($admin)->post(route('admin.privacy-requests.exports.generate', [$privacyRequest, $export]))->assertRedirect();

        Queue::assertPushed(GenerateApplicantExport::class, fn (GenerateApplicantExport $job) => $job->dataExportId === $export->id);
    }

    public function test_download_is_no_store_audited_and_cross_request_binding_is_denied(): void
    {
        [$admin, $applicant, $privacyRequest] = $this->requestContext();
        $service = app(ApplicantExportService::class);
        $export = $this->generateExport($service, $admin, $service->authorize($admin, $privacyRequest, ['account']));

        $response = $this->actingAsMfaVerified($admin)->get(route('admin.privacy-requests.exports.download', [$privacyRequest, $export]));
        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertNotNull($export->fresh()->downloaded_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'data_export_downloaded', 'subject_user_id' => $applicant->id]);

        $otherApplicant = $this->applicant();
        $otherRequest = app(PrivacyRequestWorkflow::class)->submit($otherApplicant, PrivacyRequest::TYPE_ACCESS);
        $this->actingAsMfaVerified($admin)->get(route('admin.privacy-requests.exports.download', [$otherRequest, $export]))->assertNotFound();
    }

    public function test_generation_failure_removes_partial_files_and_retry_state_is_safe(): void
    {
        [$admin, , $privacyRequest] = $this->requestContext();
        $service = app(ApplicantExportService::class);
        $export = $service->authorize($admin, $privacyRequest, ['account']);
        $service->recordGenerationInitiated($admin, $export);
        Storage::disk('private')->put('privacy-exports', 'blocking-file');

        try {
            $service->generate($export);
            $this->fail('Generation should have failed with an invalid private directory.');
        } catch (RuntimeException) {
            $this->assertSame(DataExport::STATUS_FAILED, $export->fresh()->status);
            $this->assertNull($export->fresh()->private_path);
            $this->assertSame(['privacy-exports'], Storage::disk('private')->allFiles());
        }
    }

    public function test_expired_export_purge_is_idempotent_private_and_audited(): void
    {
        [$admin, $applicant, $privacyRequest] = $this->requestContext();
        $service = app(ApplicantExportService::class);
        $export = $this->generateExport($service, $admin, $service->authorize($admin, $privacyRequest, ['account']));
        $path = $export->private_path;
        $export->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->artisan('privacy:exports:purge-expired')->assertSuccessful();
        $this->assertFalse(Storage::disk('private')->exists($path));
        $this->assertSame(DataExport::STATUS_PURGED, $export->fresh()->status);
        $this->assertNull($export->fresh()->private_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'data_export_purged', 'subject_user_id' => $applicant->id]);

        $this->artisan('privacy:exports:purge-expired')->assertSuccessful();
        $this->assertDatabaseCount('data_exports', 1);
    }

    public function test_duplicate_and_terminal_generation_jobs_are_harmless_and_cannot_resurrect_artifacts(): void
    {
        [$admin, , $privacyRequest] = $this->requestContext();
        $service = app(ApplicantExportService::class);
        $export = $service->authorize($admin, $privacyRequest, ['account']);
        $service->recordGenerationInitiated($admin, $export);
        $export->forceFill([
            'status' => DataExport::STATUS_GENERATING,
            'generation_token' => (string) \Illuminate\Support\Str::uuid(),
            'generation_started_at' => now(),
        ])->save();

        $duplicate = $service->generate($export);
        $this->assertSame(DataExport::STATUS_GENERATING, $duplicate->status);
        $this->assertSame([], Storage::disk('private')->allFiles());

        foreach ([DataExport::STATUS_EXPIRED, DataExport::STATUS_PURGING, DataExport::STATUS_PURGED] as $terminal) {
            $export->forceFill(['status' => $terminal, 'generation_token' => null])->save();
            $this->assertSame($terminal, $service->generate($export)->status);
            $this->assertSame([], Storage::disk('private')->allFiles());
        }
    }

    public function test_queued_generation_rechecks_decision_verification_authorizer_and_scope(): void
    {
        foreach (['decision', 'verification', 'authorizer', 'scope'] as $revocation) {
            [$admin, , $privacyRequest] = $this->requestContext();
            $service = app(ApplicantExportService::class);
            $export = $service->authorize($admin, $privacyRequest, ['account']);
            $service->recordGenerationInitiated($admin, $export);

            match ($revocation) {
                'decision' => $privacyRequest->forceFill(['state' => PrivacyRequest::STATE_REFUSED, 'controller_decision_code' => 'controller_refused'])->save(),
                'verification' => $privacyRequest->forceFill(['identity_verification_state' => PrivacyRequest::IDENTITY_UNABLE_TO_VERIFY])->save(),
                'authorizer' => $export->forceFill(['approved_by_user_id' => null])->save(),
                'scope' => $export->forceFill(['scope_manifest' => ['profile']])->save(),
            };

            try {
                $service->generate($export);
                $this->fail("Queued generation should fail after {$revocation} revocation.");
            } catch (\Illuminate\Validation\ValidationException) {
                $this->assertSame(DataExport::STATUS_AUTHORIZED, $export->fresh()->status);
                $this->assertSame([], Storage::disk('private')->allFiles());
            }
        }
    }

    public function test_generation_revalidates_request_immediately_before_publication(): void
    {
        [$admin, , $privacyRequest] = $this->requestContext();
        $service = app(ApplicantExportService::class);
        $export = $service->authorize($admin, $privacyRequest, ['account']);
        $service->recordGenerationInitiated($admin, $export);
        $mutated = false;
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$mutated, $privacyRequest): void {
            if (! $mutated && str_contains(strtolower($query->sql), 'users')) {
                $mutated = true;
                \Illuminate\Support\Facades\DB::table('privacy_requests')->where('id', $privacyRequest->id)->update([
                    'identity_verification_state' => PrivacyRequest::IDENTITY_UNABLE_TO_VERIFY,
                ]);
            }
        });

        try {
            $service->generate($export);
            $this->fail('Generation should fail when disclosure authority changes during construction.');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertTrue($mutated);
            $this->assertSame(DataExport::STATUS_FAILED, $export->fresh()->status);
            $this->assertSame([], Storage::disk('private')->allFiles());
        }
    }

    public function test_permission_failure_never_marks_ready_and_removes_temporary_artifacts(): void
    {
        [$admin, , $privacyRequest] = $this->requestContext();
        $permissions = Mockery::mock(PrivateExportPermissions::class);
        $permissions->shouldReceive('enforce')->once()->andThrow(new RuntimeException('simulated chmod failure'));
        $this->app->instance(PrivateExportPermissions::class, $permissions);
        $service = app(ApplicantExportService::class);
        $export = $service->authorize($admin, $privacyRequest, ['account']);
        $service->recordGenerationInitiated($admin, $export);

        try {
            $service->generate($export);
            $this->fail('Generation should fail closed when permissions cannot be enforced.');
        } catch (RuntimeException) {
            $this->assertSame(DataExport::STATUS_FAILED, $export->fresh()->status);
            $this->assertNull($export->fresh()->private_path);
            $this->assertSame([], Storage::disk('private')->allFiles());
        }
    }

    public function test_file_permission_failure_removes_built_artifact_and_never_marks_ready(): void
    {
        [$admin, , $privacyRequest] = $this->requestContext();
        $permissions = Mockery::mock(PrivateExportPermissions::class);
        $permissions->shouldReceive('enforce')->with(Mockery::type('string'), 0700, Mockery::type('string'))->times(4);
        $permissions->shouldReceive('enforce')->with(Mockery::type('string'), 0600, 'temporary export artifact')->once()->andThrow(new RuntimeException('simulated file-mode failure'));
        $this->app->instance(PrivateExportPermissions::class, $permissions);
        $service = app(ApplicantExportService::class);
        $export = $service->authorize($admin, $privacyRequest, ['account']);
        $service->recordGenerationInitiated($admin, $export);

        try {
            $service->generate($export);
            $this->fail('Generation should fail closed when file mode cannot be verified.');
        } catch (RuntimeException) {
            $this->assertSame(DataExport::STATUS_FAILED, $export->fresh()->status);
            $this->assertNull($export->fresh()->sha256);
            $this->assertSame([], Storage::disk('private')->allFiles());
        }
    }

    public function test_failure_after_atomic_rename_removes_unpublished_zip_and_marks_owned_attempt_failed(): void
    {
        [$admin, , $privacyRequest] = $this->requestContext();
        $realPermissions = new PrivateExportPermissions;
        $permissions = Mockery::mock(PrivateExportPermissions::class);
        $permissions->shouldReceive('enforce')->andReturnUsing(function (string $path, int $mode, string $description) use ($realPermissions): void {
            if ($description === 'published export artifact') {
                throw new RuntimeException('simulated post-rename permission failure');
            }
            $realPermissions->enforce($path, $mode, $description);
        });
        $this->app->instance(PrivateExportPermissions::class, $permissions);
        $service = app(ApplicantExportService::class);
        $export = $service->authorize($admin, $privacyRequest, ['account']);
        $service->recordGenerationInitiated($admin, $export);

        try {
            $service->generate($export);
            $this->fail('Post-rename verification failure must not publish READY.');
        } catch (RuntimeException) {
            $this->assertSame(DataExport::STATUS_FAILED, $export->fresh()->status);
            $this->assertNull($export->fresh()->private_path);
            $this->assertSame([], Storage::disk('private')->allFiles("privacy-exports/{$export->uuid}"));
        }
    }

    public function test_failed_publisher_cannot_delete_an_artifact_published_by_an_after_commit_retry(): void
    {
        [$admin, , $privacyRequest] = $this->requestContext();
        $realPermissions = new PrivateExportPermissions;
        $failedOnce = false;
        $permissions = Mockery::mock(PrivateExportPermissions::class);
        $permissions->shouldReceive('enforce')->andReturnUsing(function (string $path, int $mode, string $description) use ($realPermissions, &$failedOnce): void {
            if ($description === 'published export artifact' && ! $failedOnce) {
                $failedOnce = true;
                throw new RuntimeException('simulated generation A post-rename failure');
            }
            $realPermissions->enforce($path, $mode, $description);
        });
        $this->app->instance(PrivateExportPermissions::class, $permissions);
        $service = app(ApplicantExportService::class);
        $export = $service->authorize($admin, $privacyRequest, ['account']);
        $service->recordGenerationInitiated($admin, $export);

        $interleaving = new class($service, $admin, $export->id) implements ShouldHandleEventsAfterCommit
        {
            public bool $handled = false;

            public function __construct(
                private readonly ApplicantExportService $service,
                private readonly User $admin,
                private readonly int $exportId,
            ) {}

            public function updated(DataExport $candidate): void
            {
                if ($this->handled
                    || $candidate->id !== $this->exportId
                    || ! $candidate->wasChanged('status')
                    || $candidate->status !== DataExport::STATUS_FAILED) {
                    return;
                }

                $this->handled = true;
                $authorized = $this->service->retryFailedGeneration($this->admin, $candidate);
                $this->service->generate($authorized);
            }
        };
        $this->app->instance($interleaving::class, $interleaving);
        DataExport::observe($interleaving);

        try {
            $service->generate($export);
            $this->fail('Generation A must retain its original post-rename failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('simulated generation A post-rename failure', $exception->getMessage());
        }

        $ready = $export->fresh();
        $canonical = "privacy-exports/{$export->uuid}/{$export->uuid}.zip";
        $this->assertTrue($failedOnce);
        $this->assertTrue($interleaving->handled);
        $this->assertSame(DataExport::STATUS_READY, $ready->status);
        $this->assertSame(2, $ready->generation_attempt);
        $this->assertNull($ready->generation_token);
        $this->assertTrue(Storage::disk('private')->exists($canonical));
        $this->assertSame($ready->sha256, hash_file('sha256', Storage::disk('private')->path($canonical)));
        $this->assertSame($ready->id, $service->recordDownload($admin, $ready)->id);
    }

    public function test_purge_rejects_every_noncanonical_path_without_deleting_target(): void
    {
        [$admin, , $privacyRequest] = $this->requestContext();
        $service = app(ApplicantExportService::class);
        $export = $this->generateExport($service, $admin, $service->authorize($admin, $privacyRequest, ['account']));
        $other = $service->authorize($admin, $privacyRequest, ['account']);
        $targets = [
            '../escape.zip',
            '/tmp/absolute.zip',
            'privacy-exports/'.$other->uuid.'/'.$other->uuid.'.zip',
            'applicant-documents/private-passport.pdf',
            'privacy-exports/malformed.zip',
        ];
        Storage::disk('private')->put('applicant-documents/unrelated-private-file.pdf', 'DO-NOT-DELETE');

        foreach ($targets as $target) {
            $export->forceFill(['status' => DataExport::STATUS_EXPIRED, 'private_path' => $target, 'expires_at' => now()->subMinute()])->save();
            try {
                $service->purge($export);
                $this->fail("Purge should reject {$target}.");
            } catch (RuntimeException) {
                $this->assertTrue(Storage::disk('private')->exists('applicant-documents/unrelated-private-file.pdf'));
                $this->assertSame(DataExport::STATUS_EXPIRED, $export->fresh()->status);
            }
        }
    }

    public function test_purge_rejects_canonical_text_path_when_a_directory_segment_is_a_symlink(): void
    {
        [$admin, , $privacyRequest] = $this->requestContext();
        $service = app(ApplicantExportService::class);
        $export = $this->generateExport($service, $admin, $service->authorize($admin, $privacyRequest, ['account']));
        $canonicalDirectory = dirname(Storage::disk('private')->path($export->private_path));
        Storage::disk('private')->deleteDirectory('privacy-exports/'.$export->uuid);
        Storage::disk('private')->put('applicant-documents/'.$export->uuid.'.zip', 'DO-NOT-DELETE');
        $targetDirectory = Storage::disk('private')->path('applicant-documents');
        $this->assertTrue(symlink($targetDirectory, $canonicalDirectory));
        $export->forceFill(['status' => DataExport::STATUS_EXPIRED, 'expires_at' => now()->subMinute()])->save();

        try {
            $service->purge($export);
            $this->fail('Purge should reject a symlinked canonical path.');
        } catch (RuntimeException) {
            $this->assertSame(DataExport::STATUS_EXPIRED, $export->fresh()->status);
            $this->assertTrue(Storage::disk('private')->exists('applicant-documents/'.$export->uuid.'.zip'));
        }
    }

    public function test_two_phase_purge_retries_physical_and_finalization_failures_and_missing_files(): void
    {
        [$admin, , $privacyRequest] = $this->requestContext();
        $service = app(ApplicantExportService::class);
        $export = $this->generateExport($service, $admin, $service->authorize($admin, $privacyRequest, ['account']));
        $path = $export->private_path;
        $export->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->assertTrue($service->expire($export));
        Storage::disk('private')->delete($path);
        Storage::disk('private')->makeDirectory($path);

        try {
            $this->assertFalse($service->purge($export));
        } catch (\Throwable) {
            // Local adapters may throw instead of returning false for a directory target.
        }
        $this->assertSame(DataExport::STATUS_PURGING, $export->fresh()->status);
        Storage::disk('private')->deleteDirectory($path);
        $this->assertTrue($service->purge($export));
        $this->assertSame(DataExport::STATUS_PURGED, $export->fresh()->status);
        $this->assertFalse($service->purge($export));
        $this->assertSame(DataExport::STATUS_PURGED, $service->generate($export)->status);

        $second = $this->generateExport($service, $admin, $service->authorize($admin, $privacyRequest, ['account']));
        $secondPath = $second->private_path;
        $second->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->assertTrue($service->expire($second));
        $failFinalization = true;
        DataExport::updating(function (DataExport $candidate) use (&$failFinalization, $second): void {
            if ($failFinalization && $candidate->is($second) && $candidate->status === DataExport::STATUS_PURGED) {
                throw new RuntimeException('simulated finalization failure');
            }
        });
        try {
            $service->purge($second);
            $this->fail('Purge finalization should fail.');
        } catch (RuntimeException) {
            $this->assertSame(DataExport::STATUS_PURGING, $second->fresh()->status);
            $this->assertFalse(Storage::disk('private')->exists($secondPath));
        }
        $failFinalization = false;
        $this->assertTrue($service->purge($second));
        $this->assertSame(DataExport::STATUS_PURGED, $second->fresh()->status);
    }

    public function test_download_revalidates_current_request_scope_expiry_and_terminal_status(): void
    {
        foreach (['decision', 'verification', 'scope', 'expiry', 'purging', 'purged'] as $condition) {
            [$admin, , $privacyRequest] = $this->requestContext();
            $service = app(ApplicantExportService::class);
            $export = $this->generateExport($service, $admin, $service->authorize($admin, $privacyRequest, ['account']));

            match ($condition) {
                'decision' => $privacyRequest->forceFill(['state' => PrivacyRequest::STATE_REFUSED, 'controller_decision_code' => 'controller_refused'])->save(),
                'verification' => $privacyRequest->forceFill(['identity_verification_state' => PrivacyRequest::IDENTITY_UNABLE_TO_VERIFY])->save(),
                'scope' => $export->forceFill(['scope_manifest' => ['account', 'profile']])->save(),
                'expiry' => $export->forceFill(['expires_at' => now()->subMinute()])->save(),
                'purging' => $export->forceFill(['status' => DataExport::STATUS_PURGING])->save(),
                'purged' => $export->forceFill(['status' => DataExport::STATUS_PURGED])->save(),
            };

            try {
                $service->recordDownload($admin, $export);
                $this->fail("Download should fail for {$condition}.");
            } catch (\Illuminate\Validation\ValidationException) {
                $this->assertNull($export->fresh()->downloaded_at);
                if ($condition === 'expiry') {
                    $this->assertSame(DataExport::STATUS_EXPIRED, $export->fresh()->status);
                    $this->assertSame(DataExport::STATUS_EXPIRED, $service->generate($export)->status);
                }
            }
        }
    }

    public function test_download_route_denies_missing_permission_and_stale_password_confirmation(): void
    {
        [$admin, , $privacyRequest] = $this->requestContext();
        $service = app(ApplicantExportService::class);
        $export = $this->generateExport($service, $admin, $service->authorize($admin, $privacyRequest, ['account']));

        $admin->revokePermissionTo(PrivacySecurityPermissions::PRIVACY_EXPORTS_DOWNLOAD);
        $this->actingAsMfaVerified($admin)->get(route('admin.privacy-requests.exports.download', [$privacyRequest, $export]))->assertForbidden();
        $admin->givePermissionTo(PrivacySecurityPermissions::PRIVACY_EXPORTS_DOWNLOAD);
        $this->actingAsMfaVerified($admin, false)->withSession(['auth.password_confirmed_at' => 0])->get(route('admin.privacy-requests.exports.download', [$privacyRequest, $export]))->assertRedirect(route('password.confirm'));
    }

    public function test_failed_generation_requires_explicit_privileged_retry_and_produces_one_authoritative_artifact(): void
    {
        [$admin, , $privacyRequest] = $this->requestContext();
        $service = app(ApplicantExportService::class);
        $export = $service->authorize($admin, $privacyRequest, ['account']);
        $service->recordGenerationInitiated($admin, $export);
        Storage::disk('private')->put('privacy-exports', 'blocking-file');
        try {
            $service->generate($export);
        } catch (RuntimeException) {
            Storage::disk('private')->delete('privacy-exports');
        }

        (new GenerateApplicantExport($export->id))->handle($service);
        $this->assertSame(DataExport::STATUS_FAILED, $export->fresh()->status);
        $this->assertSame(DataExport::STATUS_FAILED, $service->generate($export)->status);
        $service->retryFailedGeneration($admin, $export);
        $ready = $service->generate($export);
        $this->assertSame(DataExport::STATUS_READY, $ready->status);
        $this->assertCount(1, Storage::disk('private')->allFiles());
        $this->assertSame($ready->sha256, hash_file('sha256', Storage::disk('private')->path($ready->private_path)));
        $this->assertDatabaseHas('audit_logs', ['action' => 'data_export_generation_queued', 'actor_user_id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'data_export_generation_retried', 'actor_user_id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'data_export_generated', 'actor_user_id' => $admin->id]);
    }

    public function test_fresh_generation_lease_cannot_be_stolen_but_stale_claim_is_rebuilt_with_a_new_attempt(): void
    {
        [$admin, , $privacyRequest] = $this->requestContext();
        $service = app(ApplicantExportService::class);
        $export = $service->authorize($admin, $privacyRequest, ['account']);
        $service->recordGenerationInitiated($admin, $export);
        $oldToken = (string) \Illuminate\Support\Str::uuid();
        $export->forceFill([
            'status' => DataExport::STATUS_GENERATING,
            'generation_token' => $oldToken,
            'generation_attempt' => 1,
            'generation_started_at' => now(),
        ])->save();

        $this->assertSame(DataExport::STATUS_GENERATING, $service->generate($export)->status);
        $this->assertSame($oldToken, $export->fresh()->generation_token);

        $oldStaging = "privacy-exports/{$export->uuid}/staging/{$oldToken}";
        Storage::disk('private')->makeDirectory($oldStaging);
        Storage::disk('private')->put($oldStaging.'/partial.json.tmp', 'STALE-PRIVATE-DATA');
        Storage::disk('private')->put('unrelated/source.txt', 'DO-NOT-DELETE');
        $export->forceFill(['generation_started_at' => now()->subMinutes(11)])->save();

        $ready = $service->generate($export);

        $this->assertSame(DataExport::STATUS_READY, $ready->status);
        $this->assertSame(2, $ready->generation_attempt);
        $this->assertNull($ready->generation_token);
        $this->assertFalse(Storage::disk('private')->directoryExists($oldStaging));
        $this->assertTrue(Storage::disk('private')->exists('unrelated/source.txt'));
        $this->assertCount(1, array_values(array_filter(
            Storage::disk('private')->allFiles("privacy-exports/{$export->uuid}"),
            fn (string $path): bool => str_ends_with($path, '.zip'),
        )));
    }

    public function test_stale_claim_with_finalized_orphan_is_not_adopted_and_old_token_is_fenced(): void
    {
        [$admin, , $privacyRequest] = $this->requestContext();
        $service = app(ApplicantExportService::class);
        $export = $service->authorize($admin, $privacyRequest, ['account']);
        $service->recordGenerationInitiated($admin, $export);
        $oldToken = (string) \Illuminate\Support\Str::uuid();
        $canonical = "privacy-exports/{$export->uuid}/{$export->uuid}.zip";
        Storage::disk('private')->put($canonical, 'UNTRUSTED-ORPHAN-FROM-OLD-TOKEN');
        chmod(Storage::disk('private')->path($canonical), 0600);
        $export->forceFill([
            'status' => DataExport::STATUS_GENERATING,
            'generation_token' => $oldToken,
            'generation_attempt' => 3,
            'generation_started_at' => now()->subMinutes(11),
        ])->save();

        $ready = $service->generate($export);

        $this->assertSame(DataExport::STATUS_READY, $ready->status);
        $this->assertSame(4, $ready->generation_attempt);
        $this->assertNotSame(hash('sha256', 'UNTRUSTED-ORPHAN-FROM-OLD-TOKEN'), $ready->sha256);
        $this->assertSame($ready->sha256, hash_file('sha256', Storage::disk('private')->path($canonical)));
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('private')->path($canonical)) === true);
        $zip->close();
        $this->assertStringNotContainsString($oldToken, (string) $ready->generation_token);
    }

    public function test_staging_modes_are_verified_and_multi_dataset_failure_cleans_the_entire_claim_tree(): void
    {
        [$admin, , $privacyRequest] = $this->requestContext();
        $realPermissions = new PrivateExportPermissions;
        $calls = [];
        $permissions = Mockery::mock(PrivateExportPermissions::class);
        $permissions->shouldReceive('enforce')->andReturnUsing(function (string $path, int $mode, string $description) use ($realPermissions, &$calls): void {
            $realPermissions->enforce($path, $mode, $description);
            $calls[] = [$mode, $description];
        });
        $this->app->instance(PrivateExportPermissions::class, $permissions);
        $service = app(ApplicantExportService::class);
        $export = $service->authorize($admin, $privacyRequest, ['applications']);
        $ready = $this->generateExport($service, $admin, $export);

        $this->assertSame(DataExport::STATUS_READY, $ready->status);
        $this->assertContains([0700, 'private export staging root'], $calls);
        $this->assertContains([0700, 'private export claim staging directory'], $calls);
        $this->assertContains([0600, 'private export staging file'], $calls);

        $this->app->forgetInstance(PrivateExportPermissions::class);
        [$admin, , $privacyRequest] = $this->requestContext();
        $service = app(ApplicantExportService::class);
        $failed = $service->authorize($admin, $privacyRequest, ['applications', 'payments']);
        $service->recordGenerationInitiated($admin, $failed);
        $thrown = false;
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$thrown): void {
            if (! $thrown && str_contains(strtolower($query->sql), 'payments')) {
                $thrown = true;
                throw new RuntimeException('simulated failure after earlier staging data');
            }
        });

        try {
            $service->generate($failed);
            $this->fail('A later dataset failure should abort the generation attempt.');
        } catch (RuntimeException) {
            $this->assertTrue($thrown);
            $this->assertSame(DataExport::STATUS_FAILED, $failed->fresh()->status);
            $this->assertSame([], Storage::disk('private')->allFiles("privacy-exports/{$failed->uuid}"));
        }
    }

    public function test_current_authorizer_is_required_for_publication_and_download_and_can_be_explicitly_reauthorized(): void
    {
        foreach (['soft_deleted', 'role_removed', 'permission_revoked'] as $condition) {
            [$admin, , $privacyRequest] = $this->requestContext();
            $service = app(ApplicantExportService::class);
            $export = $service->authorize($admin, $privacyRequest, ['account']);
            $service->recordGenerationInitiated($admin, $export);

            match ($condition) {
                'soft_deleted' => $admin->delete(),
                'role_removed' => $admin->removeRole('admin'),
                'permission_revoked' => $admin->revokePermissionTo(PrivacySecurityPermissions::PRIVACY_EXPORTS_AUTHORIZE),
            };

            try {
                $service->generate($export);
                $this->fail("Publication should reject a {$condition} authorizer.");
            } catch (\Illuminate\Validation\ValidationException) {
                $this->assertSame(DataExport::STATUS_AUTHORIZED, $export->fresh()->status);
            }
        }

        [$authorizer, , $privacyRequest] = $this->requestContext();
        $service = app(ApplicantExportService::class);
        $ready = $this->generateExport($service, $authorizer, $service->authorize($authorizer, $privacyRequest, ['account']));
        $authorizer->revokePermissionTo(PrivacySecurityPermissions::PRIVACY_EXPORTS_AUTHORIZE);
        try {
            $service->recordDownload($authorizer, $ready);
            $this->fail('Download should reject a revoked original authorizer.');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertNull($ready->fresh()->downloaded_at);
        }

        $replacement = User::factory()->create();
        $replacement->assignRole('admin');
        $replacement->givePermissionTo([
            PrivacySecurityPermissions::PRIVACY_EXPORTS_AUTHORIZE,
            PrivacySecurityPermissions::PRIVACY_EXPORTS_DOWNLOAD,
        ]);
        $service->reauthorize($replacement, $ready);
        $this->assertSame($replacement->id, $ready->fresh()->approved_by_user_id);
        $this->assertNotNull($service->recordDownload($replacement, $ready)->downloaded_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'data_export_reauthorized', 'actor_user_id' => $replacement->id]);
    }

    public function test_failed_retry_route_requires_permission_password_confirmation_and_records_explicit_audit(): void
    {
        Queue::fake();
        [$admin, , $privacyRequest] = $this->requestContext();
        $service = app(ApplicantExportService::class);
        $export = $service->authorize($admin, $privacyRequest, ['account']);
        $export->forceFill(['status' => DataExport::STATUS_FAILED, 'failure_code' => 'generation_failed'])->save();
        $admin->revokePermissionTo(PrivacySecurityPermissions::PRIVACY_EXPORTS_GENERATE);

        $this->actingAsMfaVerified($admin)->post(route('admin.privacy-requests.exports.retry', [$privacyRequest, $export]))->assertForbidden();
        $admin->givePermissionTo(PrivacySecurityPermissions::PRIVACY_EXPORTS_GENERATE);
        $this->actingAsMfaVerified($admin, false)->withSession(['auth.password_confirmed_at' => 0])->post(route('admin.privacy-requests.exports.retry', [$privacyRequest, $export]))->assertRedirect(route('password.confirm'));
        $this->actingAsMfaVerified($admin)->post(route('admin.privacy-requests.exports.retry', [$privacyRequest, $export]))->assertRedirect();

        $this->assertSame(DataExport::STATUS_AUTHORIZED, $export->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'data_export_generation_retried', 'actor_user_id' => $admin->id]);
        Queue::assertPushed(GenerateApplicantExport::class, fn (GenerateApplicantExport $job): bool => $job->dataExportId === $export->id);
    }

    public function test_explicit_failed_retry_revalidates_request_scope_and_original_authorization(): void
    {
        foreach (['request', 'scope', 'authorizer'] as $condition) {
            [$admin, , $privacyRequest] = $this->requestContext();
            $service = app(ApplicantExportService::class);
            $export = $service->authorize($admin, $privacyRequest, ['account']);
            $export->forceFill(['status' => DataExport::STATUS_FAILED, 'failure_code' => 'generation_failed'])->save();

            match ($condition) {
                'request' => $privacyRequest->forceFill(['state' => PrivacyRequest::STATE_REFUSED, 'controller_decision_code' => 'controller_refused'])->save(),
                'scope' => $export->forceFill(['scope_manifest' => ['profile']])->save(),
                'authorizer' => $admin->revokePermissionTo(PrivacySecurityPermissions::PRIVACY_EXPORTS_AUTHORIZE),
            };

            try {
                $service->retryFailedGeneration($admin, $export);
                $this->fail("Explicit retry must revalidate {$condition}.");
            } catch (\Illuminate\Validation\ValidationException) {
                $this->assertSame(DataExport::STATUS_FAILED, $export->fresh()->status);
                $this->assertDatabaseMissing('audit_logs', ['action' => 'data_export_generation_retried', 'entity_id' => $export->id]);
            }
        }
    }

    public function test_exact_expiration_and_purge_graph_rejects_every_other_entry_state(): void
    {
        [$admin, , $privacyRequest] = $this->requestContext();
        $service = app(ApplicantExportService::class);

        foreach ([DataExport::STATUS_AUTHORIZED, DataExport::STATUS_GENERATING, DataExport::STATUS_FAILED, DataExport::STATUS_READY] as $state) {
            $export = $service->authorize($admin, $privacyRequest, ['account']);
            $export->forceFill([
                'status' => $state,
                'expires_at' => $state === DataExport::STATUS_READY ? now()->addHour() : null,
                'generation_token' => $state === DataExport::STATUS_GENERATING ? (string) \Illuminate\Support\Str::uuid() : null,
                'generation_started_at' => $state === DataExport::STATUS_GENERATING ? now() : null,
            ])->save();
            $this->assertFalse($service->purge($export), "{$state} must not enter purging.");
            $this->assertSame($state, $export->fresh()->status);
        }

        $pastReady = $service->authorize($admin, $privacyRequest, ['account']);
        $pastReady->forceFill(['status' => DataExport::STATUS_READY, 'expires_at' => now()->subMinute()])->save();
        $this->assertFalse($service->purge($pastReady));
        $this->assertSame(DataExport::STATUS_READY, $pastReady->fresh()->status);
        $this->assertTrue($service->expire($pastReady));
        $this->assertSame(DataExport::STATUS_EXPIRED, $pastReady->fresh()->status);
        $this->assertTrue($service->purge($pastReady));
        $this->assertSame(DataExport::STATUS_PURGED, $pastReady->fresh()->status);
        $this->assertFalse($service->purge($pastReady));
    }

    public function test_reconciliation_is_dry_run_by_default_and_execute_cleans_only_stale_claim_artifacts(): void
    {
        [$admin, , $privacyRequest] = $this->requestContext();
        $service = app(ApplicantExportService::class);
        $export = $service->authorize($admin, $privacyRequest, ['account']);
        $token = (string) \Illuminate\Support\Str::uuid();
        $staging = "privacy-exports/{$export->uuid}/staging/{$token}";
        $canonical = "privacy-exports/{$export->uuid}/{$export->uuid}.zip";
        $export->forceFill([
            'status' => DataExport::STATUS_GENERATING,
            'generation_token' => $token,
            'generation_started_at' => now()->subMinutes(11),
        ])->save();
        Storage::disk('private')->put($staging.'/partial.json.tmp', 'PRIVATE-STAGING');
        Storage::disk('private')->put($canonical, 'UNPUBLISHED-ZIP');
        Storage::disk('private')->put('unrelated/keep.txt', 'KEEP');

        $this->artisan('privacy:exports:reconcile')->assertSuccessful()->expectsOutputToContain('dry run');
        $this->assertSame(DataExport::STATUS_GENERATING, $export->fresh()->status);
        $this->assertTrue(Storage::disk('private')->exists($staging.'/partial.json.tmp'));

        $this->artisan('privacy:exports:reconcile', ['--execute' => true])->assertSuccessful();
        $this->assertSame(DataExport::STATUS_FAILED, $export->fresh()->status);
        $this->assertSame('generation_lease_expired', $export->fresh()->failure_code);
        $this->assertFalse(Storage::disk('private')->directoryExists($staging));
        $this->assertFalse(Storage::disk('private')->exists($canonical));
        $this->assertTrue(Storage::disk('private')->exists('unrelated/keep.txt'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'data_export_generation_reconciled', 'subject_user_id' => $export->subject_user_id]);

        $ready = $this->generateExport($service, $admin, $service->authorize($admin, $privacyRequest, ['account']));
        $orphanToken = (string) \Illuminate\Support\Str::uuid();
        $orphanStaging = "privacy-exports/{$ready->uuid}/staging/{$orphanToken}";
        Storage::disk('private')->put($orphanStaging.'/left-after-ready.json.tmp', 'ORPHAN-STAGING');
        $this->artisan('privacy:exports:reconcile', ['--execute' => true])->assertSuccessful();
        $this->assertSame(DataExport::STATUS_READY, $ready->fresh()->status);
        $this->assertTrue(Storage::disk('private')->exists($ready->private_path));
        $this->assertFalse(Storage::disk('private')->directoryExists($orphanStaging));
    }

    public function test_complete_missing_export_directories_are_state_aware_and_idempotently_recoverable(): void
    {
        [$admin, , $privacyRequest] = $this->requestContext();
        $service = app(ApplicantExportService::class);

        $stale = $service->authorize($admin, $privacyRequest, ['account']);
        $service->recordGenerationInitiated($admin, $stale);
        $stale->forceFill([
            'status' => DataExport::STATUS_GENERATING,
            'generation_token' => (string) \Illuminate\Support\Str::uuid(),
            'generation_started_at' => now()->subMinutes(11),
        ])->save();

        $failed = $service->authorize($admin, $privacyRequest, ['account']);
        $failed->forceFill(['status' => DataExport::STATUS_FAILED, 'failure_code' => 'generation_failed'])->save();

        $expired = $this->generateExport($service, $admin, $service->authorize($admin, $privacyRequest, ['account']));
        $expired->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->assertTrue($service->expire($expired));
        Storage::disk('private')->deleteDirectory("privacy-exports/{$expired->uuid}");

        $purging = $this->generateExport($service, $admin, $service->authorize($admin, $privacyRequest, ['account']));
        $purging->forceFill(['status' => DataExport::STATUS_PURGING, 'expires_at' => now()->subMinute()])->save();
        Storage::disk('private')->deleteDirectory("privacy-exports/{$purging->uuid}");

        $ready = $this->generateExport($service, $admin, $service->authorize($admin, $privacyRequest, ['account']));
        Storage::disk('private')->deleteDirectory("privacy-exports/{$ready->uuid}");
        Storage::disk('private')->put('unrelated/keep.txt', 'KEEP');

        $service->reconcileDeterministically($failed, 'failed_generation_debris');
        $service->reconcileDeterministically($failed, 'failed_generation_debris');
        $this->assertSame(DataExport::STATUS_FAILED, $failed->fresh()->status);

        $this->artisan('privacy:exports:reconcile')
            ->assertSuccessful()
            ->expectsOutputToContain('stale generating: 1')
            ->expectsOutputToContain('ready missing artifact: 1')
            ->expectsOutputToContain('expired pending purge: 1')
            ->expectsOutputToContain('purging pending cleanup: 1');

        $this->artisan('privacy:exports:reconcile', ['--execute' => true])->assertSuccessful();
        $this->assertSame(DataExport::STATUS_FAILED, $stale->fresh()->status);
        $this->assertSame('generation_lease_expired', $stale->fresh()->failure_code);
        $this->assertSame(DataExport::STATUS_FAILED, $failed->fresh()->status);
        $this->assertSame(DataExport::STATUS_PURGED, $expired->fresh()->status);
        $this->assertSame(DataExport::STATUS_PURGED, $purging->fresh()->status);
        $this->assertSame(DataExport::STATUS_READY, $ready->fresh()->status);
        $this->assertSame('ready_missing_artifact', $service->reconciliationCondition($ready));
        $this->assertTrue(Storage::disk('private')->exists('unrelated/keep.txt'));

        $this->artisan('privacy:exports:reconcile', ['--execute' => true])->assertSuccessful();
        $this->assertFalse($service->purge($expired));
        $this->assertFalse($service->purge($purging));
        $this->assertSame(DataExport::STATUS_READY, $ready->fresh()->status);
        $this->assertFalse(Storage::disk('private')->directoryExists("privacy-exports/{$ready->uuid}"));
    }

    public function test_stale_reconciliation_cannot_delete_an_artifact_published_by_an_after_commit_retry(): void
    {
        [$admin, , $privacyRequest] = $this->requestContext();
        $service = app(ApplicantExportService::class);
        $export = $service->authorize($admin, $privacyRequest, ['account']);
        $service->recordGenerationInitiated($admin, $export);
        $staleToken = (string) \Illuminate\Support\Str::uuid();
        $export->forceFill([
            'status' => DataExport::STATUS_GENERATING,
            'generation_token' => $staleToken,
            'generation_attempt' => 1,
            'generation_started_at' => now()->subMinutes(11),
        ])->save();
        $canonical = "privacy-exports/{$export->uuid}/{$export->uuid}.zip";
        $staleStaging = "privacy-exports/{$export->uuid}/staging/{$staleToken}";
        Storage::disk('private')->put($canonical, 'STALE-CLAIM-A-ARTIFACT');
        Storage::disk('private')->put($staleStaging.'/claim-a.tmp', 'STALE-CLAIM-A-DEBRIS');

        $interleaving = new class($service, $admin, $export->id) implements ShouldHandleEventsAfterCommit
        {
            public bool $handled = false;

            public ?DataExport $ready = null;

            public function __construct(
                private readonly ApplicantExportService $service,
                private readonly User $admin,
                private readonly int $exportId,
            ) {}

            public function updated(DataExport $candidate): void
            {
                if ($this->handled
                    || $candidate->id !== $this->exportId
                    || ! $candidate->wasChanged('status')
                    || $candidate->status !== DataExport::STATUS_FAILED) {
                    return;
                }

                $this->handled = true;
                $authorized = $this->service->retryFailedGeneration($this->admin, $candidate);
                $this->ready = $this->service->generate($authorized);
            }
        };
        $this->app->instance($interleaving::class, $interleaving);
        DataExport::observe($interleaving);

        $service->reconcileDeterministically($export, 'stale_generating_with_canonical');

        $ready = $export->fresh();
        $this->assertTrue($interleaving->handled);
        $this->assertInstanceOf(DataExport::class, $interleaving->ready);
        $this->assertSame(DataExport::STATUS_READY, $ready->status);
        $this->assertSame(2, $ready->generation_attempt);
        $this->assertNull($ready->generation_token);
        $this->assertTrue(Storage::disk('private')->exists($canonical));
        $this->assertNotSame('STALE-CLAIM-A-ARTIFACT', Storage::disk('private')->get($canonical));
        $this->assertSame($ready->sha256, hash_file('sha256', Storage::disk('private')->path($canonical)));
        $this->assertFalse(Storage::disk('private')->directoryExists($staleStaging));

        $downloadable = $service->recordDownload($admin, $ready);
        $this->assertSame($ready->id, $downloadable->id);
        $service->reconcileDeterministically($export, 'stale_generating_with_canonical');
        $this->assertSame(DataExport::STATUS_READY, $export->fresh()->status);
        $this->assertTrue(Storage::disk('private')->exists($canonical));
        $this->assertSame($ready->sha256, hash_file('sha256', Storage::disk('private')->path($canonical)));
    }

    public function test_orphan_canonical_reconciliation_is_state_specific_non_destructive_and_convergent(): void
    {
        [$admin, , $privacyRequest] = $this->requestContext();
        $service = app(ApplicantExportService::class);

        $authorized = $service->authorize($admin, $privacyRequest, ['account']);
        $failed = $service->authorize($admin, $privacyRequest, ['account']);
        $failed->forceFill(['status' => DataExport::STATUS_FAILED, 'failure_code' => 'generation_failed'])->save();
        $purged = $service->authorize($admin, $privacyRequest, ['account']);
        $purged->forceFill(['status' => DataExport::STATUS_PURGED, 'purged_at' => now()])->save();
        $freshGenerating = $service->authorize($admin, $privacyRequest, ['account']);
        $service->recordGenerationInitiated($admin, $freshGenerating);
        $freshGenerating->forceFill([
            'status' => DataExport::STATUS_GENERATING,
            'generation_token' => (string) \Illuminate\Support\Str::uuid(),
            'generation_started_at' => now(),
        ])->save();
        $staleGenerating = $service->authorize($admin, $privacyRequest, ['account']);
        $service->recordGenerationInitiated($admin, $staleGenerating);
        $staleGenerating->forceFill([
            'status' => DataExport::STATUS_GENERATING,
            'generation_token' => (string) \Illuminate\Support\Str::uuid(),
            'generation_started_at' => now()->subMinutes(11),
        ])->save();
        $ready = $this->generateExport($service, $admin, $service->authorize($admin, $privacyRequest, ['account']));
        $expired = $this->generateExport($service, $admin, $service->authorize($admin, $privacyRequest, ['account']));
        $expired->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->assertTrue($service->expire($expired));
        $purging = $this->generateExport($service, $admin, $service->authorize($admin, $privacyRequest, ['account']));
        $purging->forceFill(['status' => DataExport::STATUS_PURGING, 'expires_at' => now()->subMinute()])->save();

        foreach ([$authorized, $failed, $purged, $freshGenerating, $staleGenerating] as $export) {
            Storage::disk('private')->put("privacy-exports/{$export->uuid}/{$export->uuid}.zip", "UNPUBLISHED-{$export->status}");
        }
        Storage::disk('private')->put("privacy-exports/{$authorized->uuid}/unknown.keep", 'DO-NOT-SWEEP');
        Storage::disk('private')->put('unrelated/keep.txt', 'KEEP');

        $this->artisan('privacy:exports:reconcile')
            ->assertSuccessful()
            ->expectsOutputToContain('orphan canonical authorized: 1')
            ->expectsOutputToContain('orphan canonical failed: 1')
            ->expectsOutputToContain('orphan canonical purged: 1')
            ->expectsOutputToContain('generating canonical pending: 1')
            ->expectsOutputToContain('stale generating with canonical: 1')
            ->expectsOutputToContain('expired pending purge: 1')
            ->expectsOutputToContain('purging pending cleanup: 1');

        $this->artisan('privacy:exports:reconcile', ['--execute' => true])->assertSuccessful();

        foreach ([$authorized, $failed, $purged, $staleGenerating, $expired, $purging] as $export) {
            $this->assertFalse(Storage::disk('private')->exists("privacy-exports/{$export->uuid}/{$export->uuid}.zip"));
        }
        $this->assertSame(DataExport::STATUS_AUTHORIZED, $authorized->fresh()->status);
        $this->assertSame(DataExport::STATUS_FAILED, $failed->fresh()->status);
        $this->assertSame(DataExport::STATUS_PURGED, $purged->fresh()->status);
        $this->assertSame(DataExport::STATUS_GENERATING, $freshGenerating->fresh()->status);
        $this->assertTrue(Storage::disk('private')->exists("privacy-exports/{$freshGenerating->uuid}/{$freshGenerating->uuid}.zip"));
        $this->assertSame(DataExport::STATUS_FAILED, $staleGenerating->fresh()->status);
        $this->assertSame(DataExport::STATUS_READY, $ready->fresh()->status);
        $this->assertTrue(Storage::disk('private')->exists($ready->private_path));
        $this->assertSame(DataExport::STATUS_PURGED, $expired->fresh()->status);
        $this->assertSame(DataExport::STATUS_PURGED, $purging->fresh()->status);
        $this->assertTrue(Storage::disk('private')->exists("privacy-exports/{$authorized->uuid}/unknown.keep"));
        $this->assertTrue(Storage::disk('private')->exists('unrelated/keep.txt'));

        $this->artisan('privacy:exports:reconcile', ['--execute' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('generating canonical pending: 1')
            ->expectsOutputToContain('orphan canonical authorized: 0')
            ->expectsOutputToContain('orphan canonical failed: 0')
            ->expectsOutputToContain('orphan canonical purged: 0');
        $this->assertTrue(Storage::disk('private')->exists($ready->private_path));
        $this->assertTrue(Storage::disk('private')->exists("privacy-exports/{$freshGenerating->uuid}/{$freshGenerating->uuid}.zip"));
    }

    /** @return array{User,User,PrivacyRequest} */
    private function requestContext(bool $approved = true): array
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->givePermissionTo([
            PrivacySecurityPermissions::PRIVACY_EXPORTS_AUTHORIZE,
            PrivacySecurityPermissions::PRIVACY_EXPORTS_GENERATE,
            PrivacySecurityPermissions::PRIVACY_EXPORTS_DOWNLOAD,
        ]);
        $admin = $this->enrollAdministratorMfa($admin);
        $applicant = $this->applicant();
        $workflow = app(PrivacyRequestWorkflow::class);
        $request = $workflow->submit($applicant, PrivacyRequest::TYPE_ACCESS);

        if ($approved) {
            $request = $workflow->transition($admin, $request, PrivacyRequest::STATE_UNDER_REVIEW);
            $request = $workflow->verifyIdentity($admin, $request, 'controller_approved_process');
            $request = $workflow->transition($admin, $request, PrivacyRequest::STATE_DECISION_REQUIRED);
            $request = $workflow->transition($admin, $request, PrivacyRequest::STATE_APPROVED, 'controller_approved');
        }

        return [$admin, $applicant, $request];
    }

    private function applicant(): User
    {
        $user = User::factory()->create();
        $user->assignRole('job_seeker');
        JobSeeker::query()->create(['user_id' => $user->id]);

        return $user;
    }

    private function generateExport(ApplicantExportService $service, User $actor, DataExport $export): DataExport
    {
        $service->recordGenerationInitiated($actor, $export);

        return $service->generate($export);
    }
}
