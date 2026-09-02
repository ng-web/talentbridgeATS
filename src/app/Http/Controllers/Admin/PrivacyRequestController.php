<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Services\Privacy\PrivacyRequestInventoryService;
use App\Services\Privacy\PrivacyRequestWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class PrivacyRequestController extends Controller
{
    public function index(): View
    {
        return view('admin.privacy-requests.index', [
            'privacyRequests' => PrivacyRequest::query()->with('assignee:id,name')->latest('submitted_at')->paginate(25),
        ]);
    }

    public function show(PrivacyRequest $privacyRequest, PrivacyRequestInventoryService $inventory): View
    {
        return view('admin.privacy-requests.show', [
            'privacyRequest' => $privacyRequest->load(['subject.jobSeeker', 'assignee', 'events.actor', 'exports']),
            'inventory' => $inventory->forRequest($privacyRequest),
            'administrators' => User::query()->role('admin')->select(['id', 'name'])->orderBy('name')->get(),
        ]);
    }

    public function assign(Request $request, PrivacyRequest $privacyRequest, PrivacyRequestWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_admin_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ]);
        $assignee = User::query()->findOrFail($validated['assigned_admin_id']);
        abort_unless($assignee->hasRole('admin'), 422);
        $workflow->assign($request->user(), $privacyRequest, $assignee);

        return back()->with('success', 'Privacy request assigned.');
    }

    public function transition(Request $request, PrivacyRequest $privacyRequest, PrivacyRequestWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'state' => ['required', Rule::in([
                PrivacyRequest::STATE_UNDER_REVIEW,
                PrivacyRequest::STATE_IDENTITY_REQUIRED,
                PrivacyRequest::STATE_DECISION_REQUIRED,
                PrivacyRequest::STATE_FULFILLED,
                PrivacyRequest::STATE_CLOSED,
            ])],
            'reason_code' => ['nullable', Rule::in(['controller_process', 'request_fulfilled', 'controller_closed'])],
        ]);
        $workflow->transition($request->user(), $privacyRequest, $validated['state'], $validated['reason_code'] ?? null);

        return back()->with('success', 'Privacy request state updated.');
    }

    public function verifyIdentity(Request $request, PrivacyRequest $privacyRequest, PrivacyRequestWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'method' => ['required', Rule::in(config('privacy.identity_verification_methods', []))],
        ]);
        $workflow->verifyIdentity($request->user(), $privacyRequest, $validated['method']);

        return back()->with('success', 'Controller-completed identity verification recorded.');
    }

    public function decide(Request $request, PrivacyRequest $privacyRequest, PrivacyRequestWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'state' => ['required', Rule::in([
                PrivacyRequest::STATE_APPROVED,
                PrivacyRequest::STATE_PARTIALLY_APPROVED,
                PrivacyRequest::STATE_REFUSED,
            ])],
            'controller_decision_code' => ['required', Rule::in([
                'controller_approved',
                'controller_partially_approved',
                'controller_refused',
            ])],
        ]);

        $expectedCode = match ($validated['state']) {
            PrivacyRequest::STATE_APPROVED => 'controller_approved',
            PrivacyRequest::STATE_PARTIALLY_APPROVED => 'controller_partially_approved',
            PrivacyRequest::STATE_REFUSED => 'controller_refused',
        };

        if ($validated['controller_decision_code'] !== $expectedCode) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'controller_decision_code' => 'The controller decision code does not match the selected outcome.',
            ]);
        }

        $workflow->transition($request->user(), $privacyRequest, $validated['state'], $validated['controller_decision_code']);

        return back()->with('success', 'Controller decision recorded.');
    }
}
