<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class CompanyController extends Controller
{
    public function edit(): View
    {
        $employer = Auth::user()->employer;

        abort_unless($employer, 404);

        return view('employer.company-edit', compact('employer'));
    }

    public function update(Request $request): RedirectResponse
    {
        $employer = Auth::user()->employer;

        abort_unless($employer, 404);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_description' => ['nullable', 'string'],
            'industry' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
        ]);

        $employer->update($validated);

        return redirect()
            ->route('employer.company.edit')
            ->with('status', 'company-updated');
    }
}