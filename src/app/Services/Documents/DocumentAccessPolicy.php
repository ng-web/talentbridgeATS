<?php

namespace App\Services\Documents;

use App\Models\Application;
use App\Models\JobSeeker;
use App\Models\JobSeekerDocument;
use App\Models\User;

final class DocumentAccessPolicy
{
    public const PROFILE_RESUME = 'profile_resume';

    public const PROFILE_COVER_LETTER = 'profile_cover_letter';

    public const APPLICATION_RESUME = 'application_resume';

    public const APPLICATION_COVER_LETTER = 'application_cover_letter';

    public const EMPLOYER_VISIBLE_TYPES = [
        JobSeekerDocument::TYPE_PROFILE_PHOTO,
        JobSeekerDocument::TYPE_CERTIFICATE,
        self::APPLICATION_RESUME,
        self::APPLICATION_COVER_LETTER,
    ];

    public const HIGH_RISK_TYPES = [
        JobSeekerDocument::TYPE_PASSPORT,
        JobSeekerDocument::TYPE_DRIVERS_LICENSE,
        JobSeekerDocument::TYPE_POLICE_RECORD,
        JobSeekerDocument::TYPE_MEDICAL_RECORD,
    ];

    public function canAccess(
        User $actor,
        JobSeeker $jobSeeker,
        string $documentType,
        ?Application $application = null,
    ): bool {
        if ($actor->hasRole('admin')) {
            return true;
        }

        if ($actor->hasRole('job_seeker')) {
            return $jobSeeker->user_id === $actor->id;
        }

        if (! $actor->hasRole('employer') || ! in_array($documentType, self::EMPLOYER_VISIBLE_TYPES, true)) {
            return false;
        }

        $employerId = $actor->employer?->id;

        if (! $employerId) {
            return false;
        }

        if ($application !== null) {
            return $application->job_seeker_id === $jobSeeker->id
                && $application->status !== Application::STATUS_WITHDRAWN
                && $application->job?->employer_id === $employerId;
        }

        return Application::query()
            ->where('job_seeker_id', $jobSeeker->id)
            ->where('status', '!=', Application::STATUS_WITHDRAWN)
            ->whereHas('job', fn ($query) => $query->where('employer_id', $employerId))
            ->exists();
    }

    public function isHighRisk(string $documentType): bool
    {
        return in_array($documentType, self::HIGH_RISK_TYPES, true);
    }
}
