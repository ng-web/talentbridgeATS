<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PolicyDocument;
use App\Services\Privacy\PolicyRegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class PolicyDocumentController extends Controller
{
    public function index(): View
    {
        return view('admin.policy-documents.index', [
            'documents' => PolicyDocument::query()->latest('effective_at')->get(),
        ]);
    }

    public function store(Request $request, PolicyRegistryService $registry): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(PolicyDocument::TYPES)],
            'version' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', Rule::unique('policy_documents')->where(fn ($query) => $query->where('type', $request->string('type')->toString()))],
            'title' => ['required', 'string', 'max:255'],
            'content_reference' => ['required', 'url:http,https', 'max:2048'],
            'effective_at' => ['required', 'date'],
        ]);
        $registry->createVersion($request->user(), $validated);

        return back()->with('success', 'Immutable policy version registered. Activate it only after Kairox approval.');
    }

    public function activate(Request $request, PolicyDocument $policyDocument, PolicyRegistryService $registry): RedirectResponse
    {
        $registry->activate($request->user(), $policyDocument);

        return back()->with('success', 'Kairox-approved policy version activated.');
    }
}
