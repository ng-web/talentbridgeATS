<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\ApplicationFile;
use App\Models\DispositionPlanItem;
use App\Models\JobSeeker;
use App\Models\JobSeekerDocument;
use App\Services\Documents\ApplicantDocumentLifecycle;
use App\Services\Privacy\LegalHoldService;
use App\Services\Privacy\RetentionDataCategories;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class DeleteUnreferencedApplicantDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly string $path,
        public readonly ?int $subjectUserId = null,
        public readonly ?string $dataCategory = null,
        public readonly ?int $resourceId = null,
        public readonly ?int $planItemId = null,
        public readonly ?string $artifactRevision = null,
        public readonly ?string $artifactFingerprint = null,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(ApplicantDocumentLifecycle $lifecycle, ?LegalHoldService $holds = null): void
    {
        DB::transaction(function () use ($lifecycle, $holds): void {
            if ($this->subjectUserId === null || $this->subjectUserId < 1
                || $this->dataCategory === null || $this->resourceId === null || $this->resourceId < 1
                || $this->artifactFingerprint === null
                || preg_match('/^[a-f0-9]{64}$/', $this->artifactFingerprint) !== 1
                || ! hash_equals($this->artifactFingerprint, hash('sha256', $this->path))
                || ($this->dataCategory === RetentionDataCategories::APPLICANT_DOCUMENT && ! Str::isUuid((string) $this->artifactRevision))) {
                throw new RuntimeException('Applicant document cleanup identity is invalid.');
            }

            $holds ??= app(LegalHoldService::class);
            $holds->lockSubject($this->subjectUserId);
            if ($holds->activeAppliesCurrent($this->subjectUserId, $this->dataCategory, $this->resourceId)) {
                throw new RuntimeException('A legal hold prevents physical applicant-document cleanup.');
            }
            $this->assertResourceBinding();

            if (! $lifecycle->deleteIfUnreferenced($this->path)) {
                throw new RuntimeException('An unreferenced applicant document could not be physically removed.');
            }

            if ($this->planItemId !== null) {
                DispositionPlanItem::query()->whereKey($this->planItemId)->where('file_cleanup_status', DispositionPlanItem::CLEANUP_PENDING)
                    ->update(['file_cleanup_status' => DispositionPlanItem::CLEANUP_COMPLETED, 'cleanup_path' => null, 'updated_at' => now()]);
            }
        });
    }

    private function assertResourceBinding(): void
    {
        $actualSubjectId = match ($this->dataCategory) {
            RetentionDataCategories::APPLICANT_PROFILE => JobSeeker::query()->lockForUpdate()->find($this->resourceId)?->user_id,
            RetentionDataCategories::APPLICANT_DOCUMENT => $this->documentSubjectId(),
            RetentionDataCategories::APPLICATION => $this->applicationSubjectId(),
            RetentionDataCategories::APPLICATION_FILE => $this->applicationFileSubjectId(),
            default => throw new RuntimeException('Applicant document cleanup category is unsupported.'),
        };

        if ($actualSubjectId !== null && (int) $actualSubjectId !== $this->subjectUserId) {
            throw new RuntimeException('Applicant document cleanup resource binding is invalid.');
        }
    }

    private function documentSubjectId(): ?int
    {
        $document = JobSeekerDocument::query()->lockForUpdate()->find($this->resourceId);
        if (! $document) {
            return null;
        }

        return JobSeeker::query()->lockForUpdate()->find($document->job_seeker_id)?->user_id;
    }

    private function applicationSubjectId(): ?int
    {
        $application = Application::query()->lockForUpdate()->find($this->resourceId);
        if (! $application) {
            return null;
        }

        return JobSeeker::query()->lockForUpdate()->find($application->job_seeker_id)?->user_id;
    }

    private function applicationFileSubjectId(): ?int
    {
        $file = ApplicationFile::query()->lockForUpdate()->find($this->resourceId);
        if (! $file) {
            return null;
        }
        $application = Application::query()->lockForUpdate()->find($file->application_id);

        return $application ? JobSeeker::query()->lockForUpdate()->find($application->job_seeker_id)?->user_id : null;
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->planItemId !== null) {
            DispositionPlanItem::query()->whereKey($this->planItemId)->where('file_cleanup_status', DispositionPlanItem::CLEANUP_PENDING)
                ->update(['file_cleanup_status' => DispositionPlanItem::CLEANUP_FAILED, 'updated_at' => now()]);
        }

        Log::error('Applicant document cleanup exhausted its retries', [
            'path_fingerprint' => hash('sha256', $this->path),
            'exception_class' => $exception ? $exception::class : null,
        ]);
    }
}
