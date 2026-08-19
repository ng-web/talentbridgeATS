<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class AdminSessionService
{
    public const SESSION_VERSION_KEY = 'auth.security_version';

    public function invalidateAll(User $user): int
    {
        $user->forceFill([
            'security_version' => ((int) $user->security_version) + 1,
            'remember_token' => Str::random(60),
        ])->save();

        return $this->deleteStoredSessions($user);
    }

    public function invalidateOthers(User $user, Request $request): int
    {
        $user->forceFill([
            'security_version' => ((int) $user->security_version) + 1,
            'remember_token' => Str::random(60),
        ])->save();

        $deleted = $this->deleteStoredSessions($user, $request->session()->getId());
        $this->stamp($request, $user);

        return $deleted;
    }

    public function stamp(Request $request, User $user): void
    {
        $request->session()->put(self::SESSION_VERSION_KEY, (int) $user->security_version);
    }

    private function deleteStoredSessions(User $user, ?string $exceptSessionId = null): int
    {
        try {
            $query = DB::connection(config('session.connection'))
                ->table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->getKey());

            if ($exceptSessionId !== null) {
                $query->where('id', '!=', $exceptSessionId);
            }

            return $query->delete();
        } catch (Throwable $exception) {
            Log::error('Physical session revocation failed; logical security version remains active', [
                'exception_class' => $exception::class,
            ]);

            return 0;
        }
    }
}
