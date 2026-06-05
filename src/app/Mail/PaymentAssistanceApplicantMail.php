<?php

namespace App\Mail;

use App\Models\PaymentAssistanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class PaymentAssistanceApplicantMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly PaymentAssistanceRequest $assistanceRequest,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'We received your request — ' . $this->assistanceRequest->program_name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-assistance.applicant',
        );
    }
}
