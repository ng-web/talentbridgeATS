<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class ApplicationStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Application $application,
        private readonly string $newStatus,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $jobTitle = $this->application->job?->title ?? 'your application';

        return [
            'type' => 'application_status_changed',
            'title' => 'Application status updated',
            'message' => 'Your application for ' . $jobTitle . ' is now ' . $this->labelForStatus($this->newStatus) . '.',
            'url' => route('jobseeker.applications.index'),
            'application_id' => $this->application->id,
            'job_id' => $this->application->job_id,
            'status' => $this->newStatus,
        ];
    }

    private function labelForStatus(string $status): string
    {
        return \App\Models\Application::labelFor($status);
    }
}