<?php

namespace App\Mail;

use App\Models\Job;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class JobApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Job $job
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Your Job Has Been Approved')
            ->view('emails.employer.job-approved');
    }
}