<?php

namespace Tests;

use App\Models\User;
use App\Services\Security\AdministratorMfaSession;
use App\Services\Security\AdminSessionService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(Authenticatable $user, $guard = null)
    {
        if ($user instanceof User) {
            $user->refresh();
        }

        parent::actingAs($user, $guard);

        return $this->withSession([
            AdminSessionService::SESSION_VERSION_KEY => (int) ($user->security_version ?? 1),
        ]);
    }

    protected function actingAsMfaVerified(User $user, bool $passwordConfirmed = true): static
    {
        $user->refresh();

        if (! $user->hasRole('admin') || ! $user->hasEnabledTwoFactorAuthentication()) {
            throw new RuntimeException('The test must explicitly enroll the administrator in MFA first.');
        }

        $this->actingAs($user)->withSession([
            AdministratorMfaSession::VERIFIED_AT_KEY => time(),
            AdministratorMfaSession::METHOD_KEY => AdministratorMfaSession::METHOD_TOTP,
            AdministratorMfaSession::SECURITY_VERSION_KEY => (int) $user->security_version,
        ]);

        if ($passwordConfirmed) {
            $this->withSession(['auth.password_confirmed_at' => time()]);
        }

        return $this;
    }

    protected function enrollAdministratorMfa(User $admin): User
    {
        if (! $admin->hasRole('admin')) {
            throw new RuntimeException('Only administrator test users may use the MFA enrollment helper.');
        }

        if (! $admin->hasEnabledTwoFactorAuthentication()) {
            app(EnableTwoFactorAuthentication::class)($admin);
            $secret = Fortify::currentEncrypter()->decrypt($admin->fresh()->two_factor_secret);
            app(ConfirmTwoFactorAuthentication::class)($admin, (new Google2FA)->getCurrentOtp($secret));
        }

        return $admin->fresh();
    }
}
