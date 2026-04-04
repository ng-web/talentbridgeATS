<?php

namespace App\Mail;

use App\Models\Entitlement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class AccessActivatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Entitlement $entitlement
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Your Platform Access Has Been Activated')
            ->view('emails.access.activated');
    }
}