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

final class AccountSetupController extends Controller
{
    public function __construct(
        private readonly AdminSessionService $sessions,
        private readonly PrivacyAuditService $audit,
    ) {}

    public function create(Request $request, string $token): Response
    {
        return response(view('auth.account-setup', [
            'email' => (string) $request->query('email'),
            'token' => $token,
        ]))->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user) use ($validated): void {
                $user->forceFill([
                    'password' => Hash::make($validated['password']),
                    'must_change_password' => false,
                    'remember_token' => Str::random(60),
                ])->save();

                $revoked = $this->sessions->invalidateAll($user);

                $this->audit->record(
                    event: 'account_password_established',
                    resource: $user,
                    subjectUserId: $user->id,
                    reasonCode: 'single_use_setup_completed',
                    metadata: [
                        'account_role' => $user->getRoleNames()->first() ?? 'unassigned',
                        'session_revocation_count' => $revoked,
                    ],
                );

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This account setup link is invalid or has expired.']);
        }

        return redirect()->route('login')->with('status', 'Your password was established. You may now sign in.');
    }
}
