<?php

namespace App\Observers;

use App\Models\Application;
use App\Models\ApplicationFile;
use App\Models\JobSeeker;
use App\Models\JobSeekerDocument;
use App\Services\Documents\ApplicantDocumentLifecycle;
use App\Services\Privacy\LegalHoldService;
use App\Services\Privacy\RetentionDataCategories;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class ApplicantDocumentObserver
{
    public function __construct(
        private readonly ApplicantDocumentLifecycle $lifecycle,
        private readonly LegalHoldService $holds,
    ) {}

    public function deleting(Model $model): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Applicant-data deletion must run inside a transaction so hold fencing survives through commit.');
        }
        if ($model instanceof JobSeekerDocument) {
            [$current, $subjectUserId] = $this->lockCurrentDocumentIdentity($model);
            if ($this->holds->activeAppliesCurrent($subjectUserId, RetentionDataCategories::APPLICANT_DOCUMENT, (int) $current->id)) {
                throw ValidationException::withMessages(['resource' => 'This resource is protected by an active hold.']);
            }
            $planItemId = $model->relationLoaded('__dispositionPlanItemId') ? $model->getRelation('__dispositionPlanItemId') : null;
            $model->setRelation('__privacyCleanupRequests', collect([
                $this->cleanupRequest(
                    (string) $current->file_path,
                    $subjectUserId,
                    RetentionDataCategories::APPLICANT_DOCUMENT,
                    (int) $current->id,
                    (string) $current->artifact_revision,
                    is_int($planItemId) ? $planItemId : null,
                ),
            ]));

            return;
        }

        if ($model instanceof ApplicationFile) {
            $model->loadMissing('application.jobSeeker');
            $subjectUserId = (int) $model->application?->jobSeeker?->user_id;
            $this->assertNotHeld($subjectUserId, RetentionDataCategories::APPLICATION_FILE, (int) $model->id);
            $model->setRelation('__privacyCleanupRequests', collect([
                $this->cleanupRequest((string) $model->file_path, $subjectUserId, RetentionDataCategories::APPLICATION_FILE, (int) $model->id),
            ])->filter());

            return;
        }

        if ($model instanceof Application) {
            $model->loadMissing('jobSeeker', 'files');
            $subjectUserId = (int) $model->jobSeeker?->user_id;
            $this->assertNotHeld($subjectUserId, RetentionDataCategories::APPLICATION, (int) $model->id);
            foreach ($model->files as $file) {
                $this->assertNotHeld($subjectUserId, RetentionDataCategories::APPLICATION_FILE, (int) $file->id);
            }
            $requests = collect([
                $this->cleanupRequest((string) $model->submitted_resume_path, $subjectUserId, RetentionDataCategories::APPLICATION, (int) $model->id),
                $this->cleanupRequest((string) $model->submitted_cover_letter_path, $subjectUserId, RetentionDataCategories::APPLICATION, (int) $model->id),
            ]);
            foreach ($model->files as $file) {
                $requests->push($this->cleanupRequest((string) $file->file_path, $subjectUserId, RetentionDataCategories::APPLICATION_FILE, (int) $file->id));
            }
            $model->setRelation('__privacyCleanupRequests', $requests->filter());

            return;
        }

        if ($model instanceof JobSeeker) {
            $subjectUserId = (int) $model->user_id;
            $model->loadMissing('documents', 'applications.files');
            $this->assertNotHeld($subjectUserId, RetentionDataCategories::APPLICANT_PROFILE, (int) $model->id);
            foreach ($model->documents as $document) {
                $this->assertNotHeld($subjectUserId, RetentionDataCategories::APPLICANT_DOCUMENT, (int) $document->id);
            }
            foreach ($model->applications as $application) {
                $this->assertNotHeld($subjectUserId, RetentionDataCategories::APPLICATION, (int) $application->id);
                foreach ($application->files as $file) {
                    $this->assertNotHeld($subjectUserId, RetentionDataCategories::APPLICATION_FILE, (int) $file->id);
                }
            }

            $requests = collect([
                $this->cleanupRequest((string) $model->resume_path, $subjectUserId, RetentionDataCategories::APPLICANT_PROFILE, (int) $model->id),
                $this->cleanupRequest((string) $model->cover_letter_path, $subjectUserId, RetentionDataCategories::APPLICANT_PROFILE, (int) $model->id),
            ]);
            foreach ($model->documents as $document) {
                $requests->push($this->cleanupRequest(
                    (string) $document->file_path,
                    $subjectUserId,
                    RetentionDataCategories::APPLICANT_DOCUMENT,
                    (int) $document->id,
                    (string) $document->artifact_revision,
                ));
            }
            foreach ($model->applications as $application) {
                $requests->push($this->cleanupRequest((string) $application->submitted_resume_path, $subjectUserId, RetentionDataCategories::APPLICATION, (int) $application->id));
                $requests->push($this->cleanupRequest((string) $application->submitted_cover_letter_path, $subjectUserId, RetentionDataCategories::APPLICATION, (int) $application->id));
                foreach ($application->files as $file) {
                    $requests->push($this->cleanupRequest((string) $file->file_path, $subjectUserId, RetentionDataCategories::APPLICATION_FILE, (int) $file->id));
                }
            }
            $model->setRelation('__privacyCleanupRequests', $requests->filter());
        }
    }

    public function updating(Model $model): void
    {
        if ($model instanceof JobSeekerDocument && $model->isDirty('file_path')) {
            if (DB::transactionLevel() === 0) {
                throw new LogicException('Applicant-document replacement must run inside a transaction so hold fencing survives through commit.');
            }
            [$current, $subjectUserId] = $this->lockCurrentDocumentIdentity($model);
            if ($this->holds->activeAppliesCurrent($subjectUserId, RetentionDataCategories::APPLICANT_DOCUMENT, (int) $current->id)) {
                throw ValidationException::withMessages(['resource' => 'This resource is protected by an active hold.']);
            }
        }
    }

    public function deleted(Model $model): void
    {
        $requests = $model->getRelation('__privacyCleanupRequests')?->all() ?? [];

        foreach ($requests as $request) {
            $this->lifecycle->deleteAfterCommit($request['path'], $request['context']);
        }
    }

    /** @return array{path:string,context:array<string, int|string|null>}|null */
    private function cleanupRequest(
        string $path,
        int $subjectUserId,
        string $category,
        int $resourceId,
        ?string $artifactRevision = null,
        ?int $planItemId = null,
    ): ?array {
        if (! filled($path)) {
            return null;
        }

        return [
            'path' => $path,
            'context' => [
                'subject_user_id' => $subjectUserId,
                'data_category' => $category,
                'resource_id' => $resourceId,
                'plan_item_id' => $planItemId,
                'artifact_revision' => $artifactRevision,
                'artifact_fingerprint' => hash('sha256', $path),
            ],
        ];
    }

    private function assertNotHeld(int $subjectUserId, string $category, int $resourceId): void
    {
        if ($subjectUserId) {
            $this->holds->lockSubject($subjectUserId);
        }
        if (! $subjectUserId || $this->holds->activeAppliesCurrent($subjectUserId, $category, $resourceId)) {
            throw ValidationException::withMessages(['resource' => 'This resource is protected by an active hold or its hold scope is ambiguous.']);
        }
    }

    /** @return array{JobSeekerDocument, int} */
    private function lockCurrentDocumentIdentity(JobSeekerDocument $document): array
    {
        $document->loadMissing('jobSeeker');
        $subjectUserId = (int) $document->jobSeeker?->user_id;
        if (! $subjectUserId) {
            throw ValidationException::withMessages(['resource' => 'The document subject cannot be established.']);
        }

        $this->holds->lockSubject($subjectUserId);
        $current = JobSeekerDocument::query()->lockForUpdate()->find($document->id);
        $profile = $current ? JobSeeker::query()->lockForUpdate()->find($current->job_seeker_id) : null;
        if (! $current || ! $profile || (int) $profile->user_id !== $subjectUserId
            || ! hash_equals((string) $current->artifact_revision, (string) $document->getOriginal('artifact_revision'))
            || ! hash_equals(hash('sha256', (string) $current->file_path), hash('sha256', (string) $document->getOriginal('file_path')))) {
            throw ValidationException::withMessages(['resource' => 'The document artifact identity changed before mutation.']);
        }

        return [$current, $subjectUserId];
    }
}
