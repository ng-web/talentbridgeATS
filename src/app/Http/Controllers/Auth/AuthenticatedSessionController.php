<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\LoginRateLimiter;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(
        LoginRequest $request,
        RedirectIfTwoFactorAuthenticatable $redirectIfTwoFactor,
        LoginRateLimiter $limiter,
    ): RedirectResponse {
        $request->ensureIsNotRateLimited();

        $twoFactorResponse = $redirectIfTwoFactor->handle(
            $request,
            function (LoginRequest $request): void {
                $request->authenticate();
            },
        );

        if ($twoFactorResponse instanceof RedirectResponse) {
            $request->session()->put('login.auth_method', 'password');
            $limiter->clear($request);

            return $twoFactorResponse;
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
