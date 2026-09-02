<?php

namespace App\Http\Controllers;

use App\Models\PolicyDocument;
use App\Models\PrivacyRequest;
use App\Services\Privacy\PrivacyRequestWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class PrivacyCenterController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('privacy.index', [
            'policies' => PolicyDocument::query()->active()->orderBy('type')->get(),
            'acknowledgements' => $user->policyAcknowledgements()->with('policyDocument')->latest('acknowledged_at')->get(),
            'privacyRequests' => $user->privacyRequests()->latest('submitted_at')->get(),
        ]);
    }

    public function store(Request $request, PrivacyRequestWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'request_type' => ['required', Rule::in(PrivacyRequest::TYPES)],
        ]);

        $privacyRequest = $workflow->submit($request->user(), $validated['request_type']);

        return redirect()->route('privacy.show', $privacyRequest)->with('success', 'Your privacy request was submitted for review.');
    }

    public function show(Request $request, PrivacyRequest $privacyRequest): View
    {
        abort_unless($privacyRequest->subject_user_id === $request->user()->id, 404);

        return view('privacy.show', ['privacyRequest' => $privacyRequest->load('events')]);
    }
}
