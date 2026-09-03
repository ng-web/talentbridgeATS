<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureDirectPermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();
        $allowed = $user && collect(explode('|', $permissions))
            ->contains(fn (string $permission): bool => $user->hasDirectPermission($permission));

        abort_unless($allowed, 403);

        return $next($request);
    }
}
