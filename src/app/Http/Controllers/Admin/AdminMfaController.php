<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

final class AdminMfaController extends Controller
{
    public function show(Request $request): Response
    {
        return $this->noStore(view('admin.security.mfa', [
            'user' => $request->user(),
        ]));
    }

    public function start(Request $request, EnableTwoFactorAuthentication $enable): Response
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        if (! $request->user()->hasEnabledTwoFactorAuthentication()) {
            $enable($request->user());
        }

        return $this->noStore(view('admin.security.mfa', [
            'user' => $request->user()->fresh(),
        ]));
    }

    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm): Response
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:12'],
        ]);

        DB::transaction(function () use ($confirm, $request, $validated): void {
            $confirm($request->user(), $validated['code']);
        });

        return $this->recoveryCodesResponse($request);
    }

    public function regenerate(Request $request, GenerateNewRecoveryCodes $generate): Response
    {
        abort_unless($request->user()->hasEnabledTwoFactorAuthentication(), 409);
        DB::transaction(function () use ($generate, $request): void {
            $generate($request->user());
        });

        return $this->recoveryCodesResponse($request);
    }

    private function recoveryCodesResponse(Request $request): Response
    {
        return $this->noStore(view('admin.security.mfa-recovery', [
            'recoveryCodes' => $request->user()->fresh()->recoveryCodes(),
        ]));
    }

    private function noStore(mixed $view): Response
    {
        return response($view)->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
