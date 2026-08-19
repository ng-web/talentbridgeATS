<?php

namespace App\Http\Middleware;

use App\Services\Security\AdministratorMfaSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdministratorMfa
{
    public function __construct(
        private readonly AdministratorMfaSession $mfaSession,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->hasRole('admin')) {
            return $next($request);
        }

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            $this->mfaSession->clear($request);

            return redirect()
                ->route('admin.security.mfa.show')
                ->with('error', 'Administrator multi-factor authentication must be completed before continuing.');
        }

        if ($this->mfaSession->isVerified($request, $user)) {
            return $next($request);
        }

        $authMethod = Auth::guard('web')->viaRemember() ? 'remember' : 'password';

        $this->mfaSession->clear($request);
        Auth::guard('web')->logout();
        $request->session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => false,
            'login.auth_method' => $authMethod,
        ]);
        $request->session()->regenerate();
        TwoFactorAuthenticationChallenged::dispatch($user);

        return redirect()
            ->guest(route('two-factor.login'))
            ->with('error', 'Complete multi-factor authentication to continue.');
    }
}
