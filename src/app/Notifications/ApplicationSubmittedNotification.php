<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class ApplicationSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Application $application,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $jobTitle = $this->application->job?->title ?? 'a job';
        $applicantName = $this->application->jobSeeker?->user?->name ?? 'A new applicant';

        return [
            'type' => 'application_submitted',
            'title' => 'New application received',
            'message' => $applicantName . ' applied for ' . $jobTitle . '.',
            'url' => route('employer.applicants.index'),
            'application_id' => $this->application->id,
            'job_id' => $this->application->job_id,
        ];
    }
}