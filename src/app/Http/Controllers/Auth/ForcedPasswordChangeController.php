<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\AdminSessionService;
use App\Services\Security\PrivacyAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

final class ForcedPasswordChangeController extends Controller
{
    public function __construct(
        private readonly AdminSessionService $sessions,
        private readonly PrivacyAuditService $audit,
    ) {}

    public function edit(): View
    {
        return view('auth.force-change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user, 403);

        $validated = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors([
                'current_password' => 'The current password you entered is incorrect.',
            ]);
        }

        $user->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        $revoked = $this->sessions->invalidateOthers($user, $request);
        $request->session()->regenerate();

        $this->audit->record(
            event: 'password_updated',
            actor: $user,
            resource: $user,
            subjectUserId: $user->id,
            reasonCode: 'forced_password_change_completed',
            metadata: ['session_revocation_count' => $revoked],
        );

        return redirect()
            ->route('dashboard')
            ->with('success', 'Password changed successfully.');
    }
}
