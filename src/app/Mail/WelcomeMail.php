<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $role,
    ) {
    }

    public function build(): self
    {
        return $this->subject('Welcome to Kairox Exchange')
            ->view('emails.welcome');
    }
}
