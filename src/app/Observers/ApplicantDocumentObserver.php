<?php

namespace App\Observers;

use App\Models\Application;
use App\Models\ApplicationFile;
use App\Models\JobSeeker;
use App\Models\JobSeekerDocument;
use App\Services\Documents\ApplicantDocumentLifecycle;
use Illuminate\Database\Eloquent\Model;

final class ApplicantDocumentObserver
{
    public function __construct(private readonly ApplicantDocumentLifecycle $lifecycle) {}

    public function deleting(Model $model): void
    {
        $model->setRelation('__privacyDeletionPaths', collect($this->pathsFor($model)));
    }

    public function deleted(Model $model): void
    {
        $paths = $model->getRelation('__privacyDeletionPaths')?->all() ?? [];

        foreach (array_unique(array_filter($paths)) as $path) {
            $this->lifecycle->deleteAfterCommit($path);
        }
    }

    /** @return array<int, string> */
    private function pathsFor(Model $model): array
    {
        if ($model instanceof JobSeekerDocument || $model instanceof ApplicationFile) {
            return [$model->file_path];
        }

        if ($model instanceof Application) {
            return array_filter([
                $model->submitted_resume_path,
                $model->submitted_cover_letter_path,
                ...$model->files()->pluck('file_path')->all(),
            ]);
        }

        if ($model instanceof JobSeeker) {
            $paths = [
                $model->resume_path,
                $model->cover_letter_path,
                ...$model->documents()->pluck('file_path')->all(),
            ];

            foreach ($model->applications()->with('files')->get() as $application) {
                $paths[] = $application->submitted_resume_path;
                $paths[] = $application->submitted_cover_letter_path;
                array_push($paths, ...$application->files->pluck('file_path')->all());
            }

            return array_filter($paths);
        }

        return [];
    }
}
