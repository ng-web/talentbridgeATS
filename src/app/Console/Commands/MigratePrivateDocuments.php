<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\ApplicationFile;
use App\Models\AuditLog;
use App\Models\JobSeeker;
use App\Models\JobSeekerDocument;
use App\Services\Documents\ApplicantDocumentLifecycle;
use App\Services\Documents\ApplicantDocumentStorage;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class MigratePrivateDocuments extends Command
{
    protected $signature = 'kairox:migrate-private-documents
        {--dry-run : Inspect legacy references without modifying files or the database}
        {--execute : Copy, verify, update, and remove eligible legacy public files}
        {--user= : Limit processing to one user ID}
        {--limit= : Maximum number of document references to inspect}';

    protected $description = 'Safely migrate applicant document references from public to private storage';

    /** @var array<string, array<string, int>> */
    private array $counts = [];

    public function __construct(
        private readonly ApplicantDocumentLifecycle $lifecycle,
        private readonly ApplicantDocumentStorage $storage,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->counts = [];

        if ($this->option('dry-run') && $this->option('execute')) {
            $this->error('Choose either --dry-run or --execute, not both.');

            return self::INVALID;
        }

        $execute = (bool) $this->option('execute');
        $userId = $this->validatedPositiveInteger('user');
        $limit = $this->validatedPositiveInteger('limit');

        if (($this->option('user') !== null && $userId === null)
            || ($this->option('limit') !== null && $limit === null)) {
            return self::INVALID;
        }

        if (! $execute) {
            $this->info('DRY RUN: no database or filesystem changes will be made.');
        } else {
            $this->warn('EXECUTE: eligible legacy public applicant files will be migrated.');
        }

        $processed = 0;

        foreach ($this->sources($userId) as $source) {
            foreach ($source['query']->lazyById() as $model) {
                foreach (($source['references'])($model) as $reference) {
                    if ($limit !== null && $processed >= $limit) {
                        break 3;
                    }

                    $processed++;
                    $status = $this->inspect($reference);

                    if ($execute && $status === 'already_private') {
                        $status = $this->hardenPrivateVisibility($reference);
                    }

                    if ($execute && in_array($status, ['migratable', 'migratable_private_copy_present'], true)) {
                        $status = $this->migrate($reference);
                    }

                    $this->counts[$reference['category']][$status] =
                        ($this->counts[$reference['category']][$status] ?? 0) + 1;
                }
            }
        }

        $rows = [];
        foreach ($this->counts as $category => $statuses) {
            foreach ($statuses as $status => $count) {
                $rows[] = [$category, $status, $count];
            }
        }

        $this->newLine();
        $this->table(['Category', 'Result', 'Count'], $rows);
        $this->info(sprintf('Inspected %d document reference(s). No applicant PII was displayed.', $processed));

        $failures = collect($this->counts)->sum(fn (array $statuses) => ($statuses['failed'] ?? 0) + ($statuses['missing_source'] ?? 0) + ($statuses['missing_private'] ?? 0) + ($statuses['orphan_private_copy'] ?? 0)
        );

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, array{query: Builder, references: callable(Model): array<int, array<string, mixed>>}>
     */
    private function sources(?int $userId): array
    {
        return [
            [
                'query' => JobSeeker::query()->when($userId, fn ($query) => $query->where('user_id', $userId)),
                'references' => fn (JobSeeker $model) => array_values(array_filter([
                    $this->reference($model, 'resume_path', 'profile_resume', 'profile/resume', $model->id),
                    $this->reference($model, 'cover_letter_path', 'profile_cover_letter', 'profile/cover-letter', $model->id),
                ])),
            ],
            [
                'query' => JobSeekerDocument::query()
                    ->when($userId, fn ($query) => $query->whereHas('jobSeeker', fn ($jobSeeker) => $jobSeeker->where('user_id', $userId))),
                'references' => fn (JobSeekerDocument $model) => [
                    $this->reference(
                        $model,
                        'file_path',
                        'job_seeker_document:'.$model->document_type,
                        'documents/'.$model->document_type,
                        $model->job_seeker_id,
                    ),
                ],
            ],
            [
                'query' => Application::query()
                    ->when($userId, fn ($query) => $query->whereHas('jobSeeker', fn ($jobSeeker) => $jobSeeker->where('user_id', $userId))),
                'references' => fn (Application $model) => array_values(array_filter([
                    $this->reference($model, 'submitted_resume_path', 'application_resume', 'applications/'.$model->id.'/resume', $model->job_seeker_id),
                    $this->reference($model, 'submitted_cover_letter_path', 'application_cover_letter', 'applications/'.$model->id.'/cover-letter', $model->job_seeker_id),
                ])),
            ],
            [
                'query' => ApplicationFile::query()
                    ->with('application')
                    ->when($userId, fn ($query) => $query->whereHas('application.jobSeeker', fn ($jobSeeker) => $jobSeeker->where('user_id', $userId))),
                'references' => fn (ApplicationFile $model) => [
                    $this->reference(
                        $model,
                        'file_path',
                        'application_file:'.$model->document_type,
                        'applications/'.$model->application_id.'/files/'.$model->document_type,
                        $model->application->job_seeker_id,
                    ),
                ],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function reference(Model $model, string $field, string $category, string $directory, int $jobSeekerId): ?array
    {
        $path = $model->getAttribute($field);

        if (! filled($path)) {
            return null;
        }

        $this->storage->assertSafePath($path);
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $fingerprint = hash('sha256', $model::class.'|'.$model->getKey().'|'.$field.'|'.$path);
        $destination = sprintf(
            'applicants/%d/%s/%s%s',
            $jobSeekerId,
            trim($directory, '/'),
            $fingerprint,
            $extension !== '' ? '.'.$extension : '',
        );

        return compact('model', 'field', 'category', 'path', 'destination');
    }

    /** @param array<string, mixed> $reference */
    private function inspect(array $reference): string
    {
        $path = $reference['path'];
        $destination = $reference['destination'];
        $private = Storage::disk(ApplicantDocumentStorage::PRIVATE_DISK);
        $public = Storage::disk(ApplicantDocumentStorage::LEGACY_PUBLIC_DISK);

        if (str_starts_with($path, 'applicants/')) {
            return $private->exists($path) ? 'already_private' : 'missing_private';
        }

        if ($public->exists($path)) {
            return $private->exists($destination) ? 'migratable_private_copy_present' : 'migratable';
        }

        return $private->exists($destination) ? 'orphan_private_copy' : 'missing_source';
    }

    /** @param array<string, mixed> $reference */
    private function migrate(array $reference): string
    {
        /** @var Model $model */
        $model = $reference['model'];
        $field = $reference['field'];
        $source = $reference['path'];
        $destination = $reference['destination'];
        $category = $reference['category'];
        $private = Storage::disk(ApplicantDocumentStorage::PRIVATE_DISK);
        $public = Storage::disk(ApplicantDocumentStorage::LEGACY_PUBLIC_DISK);

        try {
            if (! $this->storage->ensurePrivateRoot()) {
                throw new RuntimeException('Private storage root verification failed.');
            }

            if (! $public->exists($source)) {
                throw new RuntimeException('Legacy source is unavailable for integrity verification.');
            }

            $sourceSize = $public->size($source);
            $sourceHash = $this->sha256($public, $source);

            if ($private->exists($destination)) {
                $destinationMatches = $private->size($destination) === $sourceSize
                    && hash_equals($sourceHash, $this->sha256($private, $destination));

                if (! $destinationMatches && ! $private->delete($destination)) {
                    throw new RuntimeException('Corrupt private destination could not be removed.');
                }
            }

            if (! $private->exists($destination)) {
                $stream = $public->readStream($source);
                if ($stream === null) {
                    throw new RuntimeException('Legacy source could not be read.');
                }

                try {
                    $written = $private->writeStream($destination, $stream, [
                        'visibility' => 'private',
                        'directory_visibility' => 'private',
                    ]);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                if (! $written) {
                    throw new RuntimeException('Private destination could not be written.');
                }
            }

            if (! $private->exists($destination)) {
                throw new RuntimeException('Private destination verification failed.');
            }

            if (! $this->storage->enforcePrivateVisibility($destination)) {
                throw new RuntimeException('Private destination visibility verification failed.');
            }

            if ($private->size($destination) !== $sourceSize) {
                throw new RuntimeException('Private destination size verification failed.');
            }

            if (! hash_equals($sourceHash, $this->sha256($private, $destination))) {
                throw new RuntimeException('Private destination hash verification failed.');
            }

            if ($public->size($source) !== $sourceSize || ! hash_equals($sourceHash, $this->sha256($public, $source))) {
                throw new RuntimeException('Legacy source changed during migration.');
            }

            DB::transaction(function () use ($model, $field, $source, $destination, $category): void {
                $updated = $model->newQuery()
                    ->whereKey($model->getKey())
                    ->where($field, $source)
                    ->update([$field => $destination]);

                if ($updated !== 1) {
                    throw new RuntimeException('Document reference changed during migration.');
                }

                AuditLog::create([
                    'actor_user_id' => null,
                    'action' => 'applicant_document_migrated_private',
                    'entity_type' => $model::class,
                    'entity_id' => $model->getKey(),
                    'meta' => [
                        'document_category' => $category,
                        'source_disk' => ApplicantDocumentStorage::LEGACY_PUBLIC_DISK,
                        'destination_disk' => ApplicantDocumentStorage::PRIVATE_DISK,
                    ],
                ]);

                $this->lifecycle->deleteAfterCommit($source);
            });

            return 'migrated';
        } catch (Throwable) {
            $currentPath = $model->newQuery()->whereKey($model->getKey())->value($field);
            if ($currentPath !== $destination) {
                $this->lifecycle->deleteIfUnreferenced($destination);
            }

            return 'failed';
        }
    }

    /** @param array<string, mixed> $reference */
    private function hardenPrivateVisibility(array $reference): string
    {
        try {
            return $this->storage->enforcePrivateVisibility($reference['path'])
                ? 'already_private'
                : 'failed';
        } catch (Throwable) {
            return 'failed';
        }
    }

    private function sha256(FilesystemAdapter $disk, string $path): string
    {
        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException('Document could not be read for integrity verification.');
        }

        try {
            $context = hash_init('sha256');
            $bytes = hash_update_stream($context, $stream);

            if ($bytes === false) {
                throw new RuntimeException('Document integrity verification could not be completed.');
            }

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }

    private function validatedPositiveInteger(string $option): ?int
    {
        $value = $this->option($option);

        if ($value === null) {
            return null;
        }

        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($validated === false) {
            $this->error(sprintf('--%s must be a positive integer.', $option));

            return null;
        }

        return $validated;
    }
}
