<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\AdminSessionService;
use App\Services\Security\PrivacyAuditService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class NewPasswordController extends Controller
{
    public function __construct(
        private readonly AdminSessionService $sessions,
        private readonly PrivacyAuditService $audit,
    ) {}

    /**
     * Display the password reset view.
     */
    public function create(Request $request): Response
    {
        return response(view('auth.reset-password', ['request' => $request]))->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request): void {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'must_change_password' => false,
                    'remember_token' => Str::random(60),
                ])->save();

                $revoked = $this->sessions->invalidateAll($user);

                $this->audit->record(
                    event: 'password_reset_completed',
                    resource: $user,
                    subjectUserId: $user->id,
                    reasonCode: 'self_service_password_reset',
                    metadata: [
                        'account_role' => $user->getRoleNames()->first() ?? 'unassigned',
                        'session_revocation_count' => $revoked,
                    ],
                );

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        return $status == Password::PASSWORD_RESET
                    ? redirect()->route('login')->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
