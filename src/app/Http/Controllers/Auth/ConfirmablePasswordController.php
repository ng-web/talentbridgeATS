<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\PrivacyAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConfirmablePasswordController extends Controller
{
    public function __construct(private readonly PrivacyAuditService $audit) {}

    /**
     * Show the confirm password view.
     */
    public function show(): View
    {
        return view('auth.confirm-password');
    }

    /**
     * Confirm the user's password.
     */
    public function store(Request $request): RedirectResponse
    {
        if (! Auth::guard('web')->validate([
            'email' => $request->user()->email,
            'password' => $request->password,
        ])) {
            $this->audit->record(
                event: 'privileged_reauthentication_failed',
                actor: $request->user(),
                resource: $request->user(),
                subjectUserId: $request->user()->id,
                outcome: PrivacyAuditService::OUTCOME_DENIED,
                reasonCode: 'invalid_current_password',
                metadata: ['operation' => 'password_confirmation'],
            );

            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        $this->audit->record(
            event: 'privileged_reauthentication_succeeded',
            actor: $request->user(),
            resource: $request->user(),
            subjectUserId: $request->user()->id,
            reasonCode: 'current_password_confirmed',
            metadata: ['operation' => 'password_confirmation'],
        );

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
