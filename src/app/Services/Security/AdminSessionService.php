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
        return DB::transaction(function () use ($user): int {
            $current = User::withTrashed()->lockForUpdate()->findOrFail($user->id);
            $current->forceFill([
                'security_version' => ((int) $current->security_version) + 1,
                'remember_token' => Str::random(60),
            ])->save();
            $user->forceFill(['security_version' => $current->security_version, 'remember_token' => $current->remember_token]);

            return $this->deleteStoredSessions($current);
        });
    }

    public function invalidateOthers(User $user, Request $request): int
    {
        return DB::transaction(function () use ($user, $request): int {
            $current = User::withTrashed()->lockForUpdate()->findOrFail($user->id);
            $current->forceFill([
                'security_version' => ((int) $current->security_version) + 1,
                'remember_token' => Str::random(60),
            ])->save();
            $user->forceFill(['security_version' => $current->security_version, 'remember_token' => $current->remember_token]);
            $deleted = $this->deleteStoredSessions($current, $request->session()->getId());
            $this->stamp($request, $user);

            return $deleted;
        });
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
