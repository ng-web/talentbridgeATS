<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class EmployerProvisionedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $temporaryPassword,
        public readonly string $loginUrl,
    ) {
    }

    public function build(): self
    {
        return $this->subject('Your Kairox Exchange account is ready')
            ->view('emails.employer.provisioned');
    }
}