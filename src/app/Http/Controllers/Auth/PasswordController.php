<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\AdminSessionService;
use App\Services\Security\PrivacyAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function __construct(
        private readonly AdminSessionService $sessions,
        private readonly PrivacyAuditService $audit,
    ) {}

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $revoked = $this->sessions->invalidateOthers($request->user(), $request);
        $request->session()->regenerate();

        $this->audit->record(
            event: 'password_updated',
            actor: $request->user(),
            resource: $request->user(),
            subjectUserId: $request->user()->id,
            reasonCode: 'authenticated_password_change',
            metadata: ['session_revocation_count' => $revoked],
        );

        return back()->with('status', 'password-updated');
    }
}
