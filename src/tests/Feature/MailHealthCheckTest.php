<?php

namespace Tests\Feature;

use App\Mail\KairoxTestMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class MailHealthCheckTest extends TestCase
{
    public function test_mail_health_command_uses_configured_admin_recipient(): void
    {
        Mail::fake();
        config()->set('mail.admin_address', 'operations@example.test');

        $this->artisan('kairox:test-mail')
            ->expectsOutput('Sending Kairox test email to operations@example.test...')
            ->expectsOutput('Test email dispatched successfully.')
            ->assertSuccessful();

        Mail::assertSent(KairoxTestMail::class, fn ($mail) => $mail->hasTo('operations@example.test'));
    }

    public function test_mail_health_command_fails_when_admin_recipient_is_missing(): void
    {
        Mail::fake();
        config()->set('mail.admin_address', null);

        $this->artisan('kairox:test-mail')
            ->expectsOutput('Kairox admin mail recipient is not configured. Set MAIL_ADMIN_ADDRESS.')
            ->assertFailed();

        Mail::assertNothingSent();
    }
}
