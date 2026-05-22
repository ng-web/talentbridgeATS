<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class LogoController extends Controller
{
    public function upload(Request $request): RedirectResponse
    {
        $employer = Auth::user()->employer;

        abort_unless($employer, 404);

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $path = $request->file('logo')->store('employers/logos', 'public');

        $employer->update(['logo_path' => $path]);

        return redirect()
            ->route('employer.company.edit')
            ->with('status', 'Company logo uploaded successfully.');
    }

    public function remove(): RedirectResponse
    {
        $employer = Auth::user()->employer;

        abort_unless($employer, 404);

        $employer->update(['logo_path' => null]);

        return redirect()
            ->route('employer.company.edit')
            ->with('status', 'Company logo removed.');
    }
}
