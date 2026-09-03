<?php

namespace App\Services\Privacy;

use App\Services\Security\AdministratorMfaSession;
use App\Services\Security\AdminSessionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final readonly class DispositionExecutorEvidence
{
    public function __construct(
        public int $userId,
        public int $securityVersion,
    ) {
        if ($this->userId < 1 || $this->securityVersion < 1) {
            throw ValidationException::withMessages([
                'authorization' => 'Valid initiating disposition authority evidence is required.',
            ]);
        }
    }

    public static function fromAuthenticatedRequest(Request $request): self
    {
        $user = $request->user();
        $sessionVersion = $request->session()->get(AdminSessionService::SESSION_VERSION_KEY);
        $mfaVersion = $request->session()->get(AdministratorMfaSession::SECURITY_VERSION_KEY);

        if (! $user || ! is_numeric($sessionVersion) || ! is_numeric($mfaVersion)
            || (int) $sessionVersion !== (int) $mfaVersion
            || (int) $sessionVersion !== (int) $user->security_version) {
            throw ValidationException::withMessages([
                'authorization' => 'The initiating administrator session is no longer valid.',
            ]);
        }

        return new self((int) $user->id, (int) $sessionVersion);
    }
}
