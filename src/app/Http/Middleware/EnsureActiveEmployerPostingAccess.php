<?php

namespace App\Http\Middleware;

use App\Models\Entitlement;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class EnsureActiveEmployerPostingAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403);
        }

        $hasAccess = Entitlement::query()
            ->where('user_id', $user->id)
            ->where('type', Entitlement::TYPE_EMPLOYER_POSTING_ACCESS)
            ->where('status', Entitlement::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->exists();

        if (!$hasAccess) {
            Log::warning('Employer posting access denied', [
                'user_id' => $user->id,
                'email' => $user->email,
                'route' => $request->path(),
            ]);

            return redirect()
                ->route('locked.employer')
                ->with('error', 'Your employer posting access is inactive or expired.');
        }

        return $next($request);
    }
}