<?php

namespace App\Notifications;

use App\Models\Job;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class JobApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Job $job,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'job_approved',
            'title' => 'Job approved',
            'message' => 'Your job "' . ($this->job->title ?? 'Untitled Job') . '" has been approved and is now visible to seekers.',
            'url' => route('employer.jobs.index'),
            'job_id' => $this->job->id,
        ];
    }
}