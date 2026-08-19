<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class AdministratorMfaSession
{
    public const VERIFIED_AT_KEY = 'auth.admin_mfa_verified_at';

    public const METHOD_KEY = 'auth.admin_mfa_method';

    public const SECURITY_VERSION_KEY = 'auth.admin_mfa_security_version';

    public const METHOD_TOTP = 'totp';

    public const METHOD_RECOVERY_CODE = 'recovery_code';

    public function markVerified(Request $request, User $user, string $method): void
    {
        if (! $user->hasRole('admin') || ! $user->hasEnabledTwoFactorAuthentication()) {
            throw new InvalidArgumentException('Administrator MFA assurance requires completed MFA enrollment.');
        }

        if (! in_array($method, [self::METHOD_TOTP, self::METHOD_RECOVERY_CODE], true)) {
            throw new InvalidArgumentException('Unsupported administrator MFA method.');
        }

        $request->session()->put([
            self::VERIFIED_AT_KEY => now()->getTimestamp(),
            self::METHOD_KEY => $method,
            self::SECURITY_VERSION_KEY => (int) $user->security_version,
        ]);
    }

    public function clear(Request $request): void
    {
        $request->session()->forget([
            self::VERIFIED_AT_KEY,
            self::METHOD_KEY,
            self::SECURITY_VERSION_KEY,
        ]);
    }

    public function isVerified(Request $request, User $user): bool
    {
        return is_numeric($request->session()->get(self::VERIFIED_AT_KEY))
            && in_array($request->session()->get(self::METHOD_KEY), [self::METHOD_TOTP, self::METHOD_RECOVERY_CODE], true)
            && (int) $request->session()->get(self::SECURITY_VERSION_KEY) === (int) $user->security_version;
    }
}
