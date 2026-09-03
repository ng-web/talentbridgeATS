<?php

namespace App\Services\Documents;

use App\Jobs\DeleteUnreferencedApplicantDocument;
use App\Models\Application;
use App\Models\ApplicationFile;
use App\Models\JobSeeker;
use App\Models\JobSeekerDocument;
use App\Services\Privacy\RetentionDataCategories;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ApplicantDocumentLifecycle
{
    public function __construct(private readonly ApplicantDocumentStorage $storage) {}

    /** @param array{subject_user_id:int,data_category:string,resource_id:int,plan_item_id:?int,artifact_revision:?string,artifact_fingerprint:string} $cleanupContext */
    public function deleteAfterCommit(?string $path, array $cleanupContext = []): void
    {
        if (! filled($path)) {
            return;
        }

        $cleanupContext = $this->validateCleanupContext($path, $cleanupContext);

        DB::afterCommit(function () use ($path, $cleanupContext): void {
            try {
                DeleteUnreferencedApplicantDocument::dispatch(
                    $path,
                    $cleanupContext['subject_user_id'],
                    $cleanupContext['data_category'],
                    $cleanupContext['resource_id'],
                    $cleanupContext['plan_item_id'],
                    $cleanupContext['artifact_revision'],
                    $cleanupContext['artifact_fingerprint'],
                );
            } catch (Throwable $e) {
                Log::error('Applicant document cleanup could not be queued', [
                    'path_fingerprint' => hash('sha256', $path),
                    'exception_class' => $e::class,
                ]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{subject_user_id:int,data_category:string,resource_id:int,plan_item_id:?int,artifact_revision:?string,artifact_fingerprint:string}
     */
    private function validateCleanupContext(string $path, array $context): array
    {
        $subjectUserId = filter_var($context['subject_user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $resourceId = filter_var($context['resource_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $category = $context['data_category'] ?? null;
        $fingerprint = $context['artifact_fingerprint'] ?? null;
        $revision = $context['artifact_revision'] ?? null;
        $planItemId = $context['plan_item_id'] ?? null;

        if ($subjectUserId === false || $resourceId === false
            || ! in_array($category, [
                RetentionDataCategories::APPLICANT_PROFILE,
                RetentionDataCategories::APPLICANT_DOCUMENT,
                RetentionDataCategories::APPLICATION,
                RetentionDataCategories::APPLICATION_FILE,
            ], true)
            || ! is_string($fingerprint) || preg_match('/^[a-f0-9]{64}$/', $fingerprint) !== 1
            || ! hash_equals($fingerprint, hash('sha256', $path))
            || ($category === RetentionDataCategories::APPLICANT_DOCUMENT && ! Str::isUuid((string) $revision))
            || ($planItemId !== null && filter_var($planItemId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false)) {
            throw new RuntimeException('Complete authoritative applicant-document cleanup context is required.');
        }

        return [
            'subject_user_id' => $subjectUserId,
            'data_category' => $category,
            'resource_id' => $resourceId,
            'plan_item_id' => $planItemId === null ? null : (int) $planItemId,
            'artifact_revision' => $revision === null ? null : (string) $revision,
            'artifact_fingerprint' => $fingerprint,
        ];
    }

    public function deleteIfUnreferenced(?string $path): bool
    {
        if (! filled($path) || $this->isReferenced($path)) {
            return true;
        }

        return $this->storage->delete($path);
    }

    public function isReferenced(string $path): bool
    {
        return JobSeeker::query()
            ->where(fn ($query) => $query
                ->where('resume_path', $path)
                ->orWhere('cover_letter_path', $path))
            ->exists()
            || JobSeekerDocument::query()->where('file_path', $path)->exists()
            || Application::query()
                ->where(fn ($query) => $query
                    ->where('submitted_resume_path', $path)
                    ->orWhere('submitted_cover_letter_path', $path))
                ->exists()
            || ApplicationFile::query()->where('file_path', $path)->exists();
    }
}
