<?php

namespace App\Http\Middleware;

use App\Services\Security\AdminSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureValidSecuritySession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $sessionVersion = $request->session()->get(AdminSessionService::SESSION_VERSION_KEY);

        if (! is_numeric($sessionVersion) || (int) $sessionVersion !== (int) $user->security_version) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Your session expired after a security change. Please sign in again.');
        }

        return $next($request);
    }
}
