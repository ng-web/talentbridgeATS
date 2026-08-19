<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\AdminSessionService;
use App\Services\Security\PrivacyAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

final class AdminMfaRecoveryController extends Controller
{
    public function destroy(
        Request $request,
        User $user,
        DisableTwoFactorAuthentication $disable,
        AdminSessionService $sessions,
        PrivacyAuditService $audit,
    ): RedirectResponse {
        abort_unless($user->hasRole('admin'), 404);
        abort_if($request->user()->is($user), 422, 'Administrators cannot reset their own MFA. Use a recovery code or another authorized administrator.');

        DB::transaction(function () use ($audit, $disable, $request, $sessions, $user): void {
            $disable($user);
            $revoked = $sessions->invalidateAll($user);

            $audit->record(
                event: 'admin_mfa_reset',
                actor: $request->user(),
                resource: $user,
                subjectUserId: $user->id,
                reasonCode: 'authorized_admin_recovery',
                metadata: [
                    'mfa_method' => 'totp',
                    'session_revocation_count' => $revoked,
                ],
            );
        });

        return back()->with('success', 'Administrator MFA was reset and all sessions were revoked. The account must enroll again at next sign-in.');
    }
}
