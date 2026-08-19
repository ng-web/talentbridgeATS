<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Services\Security\AdministratorMfaSession;
use App\Services\Security\AdminSessionService;
use App\Services\Security\PrivacyAuditService;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Routing\Controller;
use Laravel\Fortify\Contracts\FailedTwoFactorLoginResponse;
use Laravel\Fortify\Contracts\TwoFactorChallengeViewResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

final class TwoFactorAuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly StatefulGuard $guard,
        private readonly AdministratorMfaSession $mfaSession,
        private readonly AdminSessionService $sessions,
        private readonly PrivacyAuditService $audit,
    ) {}

    public function create(TwoFactorLoginRequest $request): TwoFactorChallengeViewResponse
    {
        if (! $request->hasChallengedUser()) {
            throw new HttpResponseException(redirect()->route('login'));
        }

        return app(TwoFactorChallengeViewResponse::class);
    }

    public function store(TwoFactorLoginRequest $request): mixed
    {
        $user = $request->challengedUser();
        $authMethod = (string) $request->session()->pull('login.auth_method', 'password');
        $mfaMethod = AdministratorMfaSession::METHOD_TOTP;

        if ($code = $request->validRecoveryCode()) {
            $user->replaceRecoveryCode($code);
            $mfaMethod = AdministratorMfaSession::METHOD_RECOVERY_CODE;
        } elseif (! $request->hasValidCode()) {
            event(new TwoFactorAuthenticationFailed($user));

            return app(FailedTwoFactorLoginResponse::class)->toResponse($request);
        }

        event(new ValidTwoFactorAuthenticationCodeProvided($user));

        $this->guard->login($user, $request->remember());
        $request->session()->regenerate();

        if ($user instanceof User) {
            $this->sessions->stamp($request, $user);

            if ($user->hasRole('admin')) {
                $this->mfaSession->markVerified($request, $user, $mfaMethod);
                $this->audit->record(
                    event: 'admin_login_succeeded',
                    actor: $user,
                    resource: $user,
                    subjectUserId: $user->id,
                    reasonCode: 'authentication_sequence_completed',
                    metadata: [
                        'auth_method' => in_array($authMethod, ['password', 'remember'], true) ? $authMethod : 'password',
                        'mfa_method' => $mfaMethod,
                    ],
                );
            }
        }

        return app(TwoFactorLoginResponse::class);
    }
}
