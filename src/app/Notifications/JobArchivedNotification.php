<?php

namespace App\Notifications;

use App\Models\Job;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class JobArchivedNotification extends Notification
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
            'type' => 'job_archived',
            'title' => 'Job archived',
            'message' => 'Your job "' . ($this->job->title ?? 'Untitled Job') . '" has been archived.',
            'url' => route('employer.jobs.index'),
            'job_id' => $this->job->id,
        ];
    }
}