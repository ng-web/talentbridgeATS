<?php

namespace App\Services\Security;

use App\Mail\AccountSetupMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class AccountSetupService
{
    public function __construct(
        private readonly AdminSessionService $sessions,
        private readonly PrivacyAuditService $audit,
    ) {}

    public function issue(User $user, User $actor): void
    {
        $this->assertSafeProductionMailer();

        $broker = Password::broker();
        $prepared = DB::transaction(function () use ($actor, $broker, $user): array {
            $user->forceFill([
                'password' => Hash::make(bin2hex(random_bytes(32))),
                'must_change_password' => true,
                'remember_token' => Str::random(60),
            ])->save();

            $revoked = $this->sessions->invalidateAll($user);
            $token = $broker->createToken($user);
            $expiresInMinutes = (int) config('auth.passwords.users.expire', 60);
            $setupUrl = URL::temporarySignedRoute(
                'account-setup.show',
                now()->addMinutes($expiresInMinutes),
                ['token' => $token, 'email' => $user->email],
            );

            $this->audit->record(
                event: 'account_setup_link_issued',
                actor: $actor,
                resource: $user,
                subjectUserId: $user->id,
                outcome: PrivacyAuditService::OUTCOME_PENDING,
                reasonCode: 'secure_setup_prepared',
                metadata: [
                    'account_role' => $user->getRoleNames()->first() ?? 'unassigned',
                    'delivery_status' => 'pending',
                    'session_revocation_count' => $revoked,
                ],
            );

            return compact('expiresInMinutes', 'revoked', 'setupUrl');
        });

        try {
            Mail::to($user)->send(new AccountSetupMail(
                $user,
                $prepared['setupUrl'],
                $prepared['expiresInMinutes'],
            ));
        } catch (Throwable $exception) {
            $broker->deleteToken($user);
            $this->recordDeliveryOutcome($user, $actor, $prepared['revoked'], false);

            throw $exception;
        }

        // Delivery has already succeeded. Audit failure must not turn this into a
        // retryable UI error that could issue a second token and duplicate email.
        $this->recordDeliveryOutcome($user, $actor, $prepared['revoked'], true);
    }

    private function assertSafeProductionMailer(): void
    {
        if ((string) config('app.env') !== 'production') {
            return;
        }

        $mailer = (string) config('mail.default');
        $visiting = [];
        $validated = [];

        $this->assertMailerGraphIsSafe($mailer, $visiting, $validated);
    }

    /**
     * @param  array<string, true>  $visiting
     * @param  array<string, true>  $validated
     */
    private function assertMailerGraphIsSafe(string $mailer, array &$visiting, array &$validated): void
    {
        if (isset($validated[$mailer])) {
            return;
        }

        if ($mailer === '' || isset($visiting[$mailer])) {
            throw new RuntimeException('Secure account setup requires a valid non-cyclic delivery configuration.');
        }

        $configuration = config("mail.mailers.{$mailer}");

        if (! is_array($configuration)) {
            throw new RuntimeException('Secure account setup requires a configured delivery transport.');
        }

        $transport = (string) ($configuration['transport'] ?? $mailer);

        if (in_array($transport, ['log', 'array'], true)) {
            throw new RuntimeException('Secure account setup requires a non-logging delivery transport.');
        }

        $visiting[$mailer] = true;

        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = $configuration['mailers'] ?? null;

            if (! is_array($children) || $children === []) {
                throw new RuntimeException('Secure account setup requires a configured delivery transport chain.');
            }

            foreach ($children as $child) {
                if (! is_string($child)) {
                    throw new RuntimeException('Secure account setup requires named delivery transports.');
                }

                $this->assertMailerGraphIsSafe($child, $visiting, $validated);
            }
        }

        unset($visiting[$mailer]);
        $validated[$mailer] = true;
    }

    private function recordDeliveryOutcome(User $user, User $actor, int $revoked, bool $succeeded): void
    {
        try {
            $this->audit->record(
                event: $succeeded ? 'account_setup_link_delivery_succeeded' : 'account_setup_link_delivery_failed',
                actor: $actor,
                resource: $user,
                subjectUserId: $user->id,
                outcome: $succeeded ? PrivacyAuditService::OUTCOME_SUCCESS : PrivacyAuditService::OUTCOME_FAILURE,
                reasonCode: $succeeded ? 'secure_setup_delivery_succeeded' : 'secure_setup_delivery_failed',
                metadata: [
                    'account_role' => $user->getRoleNames()->first() ?? 'unassigned',
                    'delivery_status' => $succeeded ? 'sent' : 'failed',
                    'session_revocation_count' => $revoked,
                ],
            );
        } catch (Throwable $exception) {
            Log::error('Account setup delivery outcome audit could not be persisted', [
                'target_user_id' => $user->id,
                'admin_user_id' => $actor->id,
                'delivery_succeeded' => $succeeded,
                'exception_class' => $exception::class,
            ]);
        }
    }
}
