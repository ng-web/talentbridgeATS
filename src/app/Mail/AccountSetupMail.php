<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class AccountSetupMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $setupUrl,
        public readonly int $expiresInMinutes,
    ) {}

    public function build(): self
    {
        return $this->subject('Set up your Kairox Exchange account')
            ->view('emails.account-setup');
    }
}
