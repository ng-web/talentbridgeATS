<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->must_change_password) {
            return $next($request);
        }

        if (
            $request->routeIs('forced-password.edit') ||
            $request->routeIs('forced-password.update') ||
            $request->routeIs('logout')
        ) {
            return $next($request);
        }

        return redirect()
            ->route('forced-password.edit')
            ->with('error', 'Please change your password before continuing.');
    }
}