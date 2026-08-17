<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class AdminNewApplicationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Application $application,
    ) {}

    public function envelope(): Envelope
    {
        $applicant = $this->application->jobSeeker?->user?->name ?? 'Applicant';
        $job = $this->application->job?->title ?? 'Job';

        return new Envelope(
            subject: "New Application Received — {$applicant} — {$job}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin.new-application');
    }
}
