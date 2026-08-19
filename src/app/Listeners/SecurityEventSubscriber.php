<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Security\AdministratorMfaSession;
use App\Services\Security\AdminSessionService;
use App\Services\Security\PrivacyAuditService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Events\Dispatcher;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

final class SecurityEventSubscriber
{
    public function __construct(
        private readonly PrivacyAuditService $audit,
        private readonly AdminSessionService $sessions,
        private readonly AdministratorMfaSession $mfaSession,
    ) {}

    public function onLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        if (request()->hasSession()) {
            $this->sessions->stamp(request(), $event->user);
        }

        // A Login event establishes identity only. Administrator login success is
        // recorded after the separate MFA challenge has completed.
    }

    public function onFailed(Failed $event): void
    {
        if (! $event->user instanceof User || ! $event->user->hasRole('admin')) {
            return;
        }

        $this->audit->record(
            event: 'admin_login_failed',
            resource: $event->user,
            subjectUserId: $event->user->id,
            outcome: PrivacyAuditService::OUTCOME_DENIED,
            reasonCode: 'invalid_credentials',
            metadata: ['auth_method' => 'password'],
        );
    }

    public function onMfaEnrollmentStarted(TwoFactorAuthenticationEnabled $event): void
    {
        $this->auditMfaEvent('admin_mfa_enrollment_started', $event->user, 'enrollment_started');
    }

    public function onMfaConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->auditMfaLifecycleEvent('admin_mfa_enabled', $event->user, 'enrollment_confirmed');
    }

    public function onRecoveryCodesGenerated(RecoveryCodesGenerated $event): void
    {
        $this->auditMfaLifecycleEvent('admin_mfa_recovery_codes_regenerated', $event->user, 'recovery_codes_rotated');
    }

    public function onMfaFailed(TwoFactorAuthenticationFailed $event): void
    {
        if (! $event->user instanceof User || ! $event->user->hasRole('admin')) {
            return;
        }

        $this->audit->record(
            event: 'admin_mfa_challenge_failed',
            resource: $event->user,
            subjectUserId: $event->user->id,
            outcome: PrivacyAuditService::OUTCOME_DENIED,
            reasonCode: 'invalid_mfa_challenge',
            metadata: [
                'mfa_method' => request()->filled('recovery_code')
                    ? AdministratorMfaSession::METHOD_RECOVERY_CODE
                    : AdministratorMfaSession::METHOD_TOTP,
            ],
        );
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'onLogin',
            Failed::class => 'onFailed',
            TwoFactorAuthenticationEnabled::class => 'onMfaEnrollmentStarted',
            TwoFactorAuthenticationConfirmed::class => 'onMfaConfirmed',
            RecoveryCodesGenerated::class => 'onRecoveryCodesGenerated',
            TwoFactorAuthenticationFailed::class => 'onMfaFailed',
        ];
    }

    private function auditMfaEvent(string $event, mixed $user, string $reasonCode): void
    {
        if (! $user instanceof User || ! $user->hasRole('admin')) {
            return;
        }

        $this->audit->record(
            event: $event,
            actor: auth()->user(),
            resource: $user,
            subjectUserId: $user->id,
            reasonCode: $reasonCode,
            metadata: ['mfa_method' => 'totp'],
        );
    }

    private function auditMfaLifecycleEvent(string $event, mixed $user, string $reasonCode): void
    {
        if (! $user instanceof User || ! $user->hasRole('admin')) {
            return;
        }

        $revoked = request()->hasSession() && auth()->id() === $user->id
            ? $this->sessions->invalidateOthers($user, request())
            : $this->sessions->invalidateAll($user);

        if (request()->hasSession()) {
            $this->mfaSession->clear(request());
        }

        $this->audit->record(
            event: $event,
            actor: auth()->user(),
            resource: $user,
            subjectUserId: $user->id,
            reasonCode: $reasonCode,
            metadata: [
                'mfa_method' => AdministratorMfaSession::METHOD_TOTP,
                'session_revocation_count' => $revoked,
            ],
        );
    }
}
