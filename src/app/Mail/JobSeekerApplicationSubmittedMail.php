<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class JobSeekerApplicationSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Application $application
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Application Submitted Successfully')
            ->view('emails.jobseeker.application-submitted');
    }
}