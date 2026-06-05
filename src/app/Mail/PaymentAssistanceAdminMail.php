<?php

namespace App\Mail;

use App\Models\PaymentAssistanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class PaymentAssistanceAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly PaymentAssistanceRequest $assistanceRequest,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Payment Assistance Request — ' . $this->assistanceRequest->program_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-assistance.admin',
        );
    }
}
