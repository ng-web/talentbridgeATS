<?php

namespace App\Console\Commands;

use App\Mail\KairoxTestMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class TestMail extends Command
{
    protected $signature = 'kairox:test-mail';

    protected $description = 'Send a test email through the configured Kairox mail transport';

    public function handle(): int
    {
        $recipient = trim((string) config('mail.admin_address'));

        if ($recipient === '') {
            $this->error('Kairox admin mail recipient is not configured. Set MAIL_ADMIN_ADDRESS.');

            Log::error('Kairox mail test failed', [
                'reason' => 'admin_recipient_not_configured',
            ]);

            return self::FAILURE;
        }

        $this->info('Sending Kairox test email to the configured admin recipient...');

        try {
            Mail::to($recipient)->send(new KairoxTestMail);

            Log::info('Kairox mail test dispatched', [
                'mailable' => KairoxTestMail::class,
            ]);

            $this->info('Test email dispatched successfully.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            Log::error('Kairox mail test failed', [
                'exception_class' => $e::class,
            ]);

            $this->error('Test email dispatch failed. Check the Laravel log for details.');

            return self::FAILURE;
        }
    }
}
