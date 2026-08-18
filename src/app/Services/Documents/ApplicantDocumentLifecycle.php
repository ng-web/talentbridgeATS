<?php

namespace App\Services\Documents;

use App\Jobs\DeleteUnreferencedApplicantDocument;
use App\Models\Application;
use App\Models\ApplicationFile;
use App\Models\JobSeeker;
use App\Models\JobSeekerDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ApplicantDocumentLifecycle
{
    public function __construct(private readonly ApplicantDocumentStorage $storage) {}

    public function deleteAfterCommit(?string $path): void
    {
        if (! filled($path)) {
            return;
        }

        DB::afterCommit(function () use ($path): void {
            try {
                DeleteUnreferencedApplicantDocument::dispatch($path);
            } catch (Throwable $e) {
                Log::error('Applicant document cleanup could not be queued', [
                    'path_fingerprint' => hash('sha256', $path),
                    'exception_class' => $e::class,
                ]);
            }
        });
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
