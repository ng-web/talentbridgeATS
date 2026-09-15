<?php

namespace App\Services\Privacy;

use App\Services\Security\AdministratorMfaSession;
use App\Services\Security\AdminSessionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final readonly class IncidentActorEvidence
{
    public function __construct(public int $userId, public int $securityVersion)
    {
        if ($userId < 1 || $securityVersion < 1) {
            throw ValidationException::withMessages(['authorization' => 'Valid incident authority evidence is required.']);
        }
    }

    public static function fromAuthenticatedRequest(Request $request): self
    {
        $user = $request->user();
        $session = $request->session()->get(AdminSessionService::SESSION_VERSION_KEY);
        $mfa = $request->session()->get(AdministratorMfaSession::SECURITY_VERSION_KEY);
        if (! $user || ! is_numeric($session) || ! is_numeric($mfa)
            || (int) $session !== (int) $mfa || (int) $session !== (int) $user->security_version) {
            throw ValidationException::withMessages(['authorization' => 'The initiating administrator session is no longer valid.']);
        }

        return new self((int) $user->id, (int) $session);
    }
}
