<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalHold;
use App\Models\PrivacyIncident;
use App\Models\User;
use App\Services\Privacy\IncidentActorEvidence;
use App\Services\Privacy\IncidentService;
use App\Services\Privacy\IncidentTaxonomy;
use App\Services\Privacy\RetentionDataCategories;
use App\Support\PrivacySecurityPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class PrivacyIncidentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(IncidentTaxonomy::STATUSES)],
            'severity' => ['nullable', Rule::in(IncidentTaxonomy::SEVERITIES)],
            'escalation' => ['nullable', Rule::in(IncidentTaxonomy::ESCALATION_STATUSES)],
            'owner' => ['nullable', 'integer', 'min:1'],
            'detected_from' => ['nullable', 'date'], 'detected_to' => ['nullable', 'date', 'after_or_equal:detected_from'],
        ]);
        $query = PrivacyIncident::query()->with(['owner', 'decisions' => fn ($q) => $q->orderByDesc('version')->limit(1)])->orderByDesc('detected_at');
        foreach (['status' => 'status', 'severity' => 'technical_severity', 'escalation' => 'escalation_status', 'owner' => 'owner_user_id'] as $input => $column) {
            if (isset($filters[$input])) {
                $query->where($column, $filters[$input]);
            }
        }
        if (isset($filters['detected_from'])) {
            $query->where('detected_at', '>=', $filters['detected_from']);
        }
        if (isset($filters['detected_to'])) {
            $query->where('detected_at', '<=', $filters['detected_to'].' 23:59:59');
        }

        return view('admin.incidents.index', [
            'incidents' => $query->paginate(50)->withQueryString(), 'taxonomy' => IncidentTaxonomy::class,
            'categories' => RetentionDataCategories::all(), 'owners' => $this->owners(),
            'canManage' => $request->user()->hasDirectPermission(PrivacySecurityPermissions::INCIDENTS_MANAGE),
        ]);
    }

    public function show(Request $request, PrivacyIncident $privacyIncident): View
    {
        $privacyIncident->load(['owner', 'events.actor', 'scopeVersions', 'decisions.decidedBy', 'holdLinks.legalHold']);

        return view('admin.incidents.show', [
            'incident' => $privacyIncident, 'taxonomy' => IncidentTaxonomy::class,
            'categories' => RetentionDataCategories::all(), 'owners' => $this->owners(),
            'permissions' => collect(PrivacySecurityPermissions::introducedInPass4())->mapWithKeys(fn ($p) => [$p => $request->user()->hasDirectPermission($p)]),
            'canHolds' => $request->user()->hasDirectPermission(PrivacySecurityPermissions::LEGAL_HOLDS_MANAGE),
        ]);
    }

    public function store(Request $request, IncidentService $service): RedirectResponse
    {
        $values = $request->validate([
            'idempotency_key' => ['required', 'uuid'], 'technical_severity' => ['required', Rule::in(IncidentTaxonomy::SEVERITIES)],
            'classification_code' => ['required', Rule::in(IncidentTaxonomy::CLASSIFICATIONS)], 'technical_summary' => ['required', 'string', 'max:500'],
            'detected_at' => ['required', 'date', 'before_or_equal:now'], 'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'data_categories' => ['array', 'max:20'], 'data_categories.*' => [Rule::in(RetentionDataCategories::all())],
            'system_codes' => ['array', 'max:20'], 'system_codes.*' => [Rule::in(IncidentTaxonomy::SYSTEMS)],
            'potentially_affected_subject_count' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
        ]);
        $values['potentially_affected_subject_count'] = $this->subjectCount($values['potentially_affected_subject_count'] ?? null);
        $incident = $service->create(IncidentActorEvidence::fromAuthenticatedRequest($request), $values, $values['idempotency_key']);

        return redirect()->route('admin.incidents.show', $incident)->with('success', 'Suspected incident recorded. No legal breach conclusion was made.');
    }

    public function assign(Request $r, PrivacyIncident $privacyIncident, IncidentService $s): RedirectResponse
    {
        $v = $r->validate(['idempotency_key' => ['required', 'uuid'], 'owner_user_id' => ['required', 'integer', 'exists:users,id']]);
        $s->assign(IncidentActorEvidence::fromAuthenticatedRequest($r), $privacyIncident, (int) $v['owner_user_id'], $v['idempotency_key']);

        return back()->with('success', 'Incident owner updated.');
    }

    public function severity(Request $r, PrivacyIncident $privacyIncident, IncidentService $s): RedirectResponse
    {
        $v = $r->validate(['idempotency_key' => ['required', 'uuid'], 'technical_severity' => ['required', Rule::in(IncidentTaxonomy::SEVERITIES)]]);
        $s->changeSeverity(IncidentActorEvidence::fromAuthenticatedRequest($r), $privacyIncident, $v['technical_severity'], $v['idempotency_key']);

        return back()->with('success', 'Technical severity updated; controller decisions were unchanged.');
    }

    public function scope(Request $r, PrivacyIncident $privacyIncident, IncidentService $s): RedirectResponse
    {
        $v = $r->validate(['idempotency_key' => ['required', 'uuid'], 'data_categories' => ['array', 'max:20'], 'data_categories.*' => [Rule::in(RetentionDataCategories::all())], 'system_codes' => ['array', 'max:20'], 'system_codes.*' => [Rule::in(IncidentTaxonomy::SYSTEMS)], 'potentially_affected_subject_count' => ['nullable', 'integer', 'min:0', 'max:4294967295']]);
        $v['potentially_affected_subject_count'] = $this->subjectCount($v['potentially_affected_subject_count'] ?? null);
        $s->updateScope(IncidentActorEvidence::fromAuthenticatedRequest($r), $privacyIncident, $v, $v['idempotency_key']);

        return back()->with('success', 'Potential technical scope snapshot appended.');
    }

    public function transition(Request $r, PrivacyIncident $privacyIncident, IncidentService $s): RedirectResponse
    {
        $v = $r->validate(['idempotency_key' => ['required', 'uuid'], 'status' => ['required', Rule::in(IncidentTaxonomy::STATUSES)], 'action_code' => ['required', Rule::in(IncidentTaxonomy::ACTION_CODES)], 'action_result' => ['required', Rule::in(IncidentTaxonomy::ACTION_RESULTS)]]);
        $s->transition(IncidentActorEvidence::fromAuthenticatedRequest($r), $privacyIncident, $v['status'], $v['action_code'], $v['action_result'], $v['idempotency_key']);

        return back()->with('success', 'Controlled technical state evidence recorded.');
    }

    public function escalate(Request $r, PrivacyIncident $privacyIncident, IncidentService $s): RedirectResponse
    {
        $v = $r->validate(['idempotency_key' => ['required', 'uuid']]);
        $s->escalate(IncidentActorEvidence::fromAuthenticatedRequest($r), $privacyIncident, $v['idempotency_key']);

        return back()->with('success', 'Processor escalation recorded. This is not controller acknowledgement.');
    }

    public function acknowledge(Request $r, PrivacyIncident $privacyIncident, IncidentService $s): RedirectResponse
    {
        $v = $r->validate(['idempotency_key' => ['required', 'uuid']]);
        $s->acknowledge(IncidentActorEvidence::fromAuthenticatedRequest($r), $privacyIncident, $v['idempotency_key']);

        return back()->with('success', 'Explicit controller acknowledgement recorded.');
    }

    public function decision(Request $r, PrivacyIncident $privacyIncident, IncidentService $s): RedirectResponse
    {
        $v = $r->validate(['idempotency_key' => ['required', 'uuid'], 'breach_assessment' => ['required', Rule::in(IncidentTaxonomy::BREACH_ASSESSMENTS)], 'authority_notification_decision' => ['required', Rule::in(IncidentTaxonomy::NOTIFICATION_DECISIONS)], 'subject_notification_decision' => ['required', Rule::in(IncidentTaxonomy::NOTIFICATION_DECISIONS)], 'reason_code' => ['required', Rule::in(IncidentTaxonomy::DECISION_REASONS)], 'rationale' => ['nullable', 'string', 'max:500']]);
        $s->recordDecision(IncidentActorEvidence::fromAuthenticatedRequest($r), $privacyIncident, $v, $v['idempotency_key']);

        return back()->with('success', 'Controller-provided decision evidence appended; the software made no legal determination.');
    }

    public function hold(Request $r, PrivacyIncident $privacyIncident, IncidentService $s): RedirectResponse
    {
        $v = $r->validate(['idempotency_key' => ['required', 'uuid'], 'subject_user_id' => ['required', 'integer', 'exists:users,id'], 'scope_type' => ['required', Rule::in(LegalHold::SCOPES)], 'data_category' => ['nullable', Rule::in(RetentionDataCategories::all())], 'resource_id' => ['nullable', 'integer', 'min:1'], 'hold_code' => ['required', Rule::in(LegalHold::HOLD_CODES)], 'effective_at' => ['required', 'date']]);
        $key = $v['idempotency_key'];
        unset($v['idempotency_key']);
        $s->issueHold(IncidentActorEvidence::fromAuthenticatedRequest($r), $privacyIncident, $v, $key);

        return back()->with('success', 'Explicit preservation hold issued through the existing legal-hold service.');
    }

    public function close(Request $r, PrivacyIncident $privacyIncident, IncidentService $s): RedirectResponse
    {
        $v = $r->validate(['idempotency_key' => ['required', 'uuid']]);
        $s->close(IncidentActorEvidence::fromAuthenticatedRequest($r), $privacyIncident, $v['idempotency_key']);

        return back()->with('success', 'Incident technically closed. Existing holds remain authoritative and active.');
    }

    public function reopen(Request $r, PrivacyIncident $privacyIncident, IncidentService $s): RedirectResponse
    {
        $v = $r->validate(['idempotency_key' => ['required', 'uuid'], 'reason_code' => ['required', Rule::in(IncidentTaxonomy::REOPEN_REASONS)]]);
        $s->reopen(IncidentActorEvidence::fromAuthenticatedRequest($r), $privacyIncident, $v['reason_code'], $v['idempotency_key']);

        return back()->with('success', 'Incident reopened with prior closure evidence preserved.');
    }

    private function owners()
    {
        return User::role('admin')->orderBy('id')->get()->filter(fn (User $user) => $user->hasDirectPermission(PrivacySecurityPermissions::INCIDENTS_MANAGE));
    }

    private function subjectCount(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_int($value) && (! is_string($value) || preg_match('/^(0|[1-9][0-9]*)$/D', $value) !== 1)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'potentially_affected_subject_count' => 'Potentially affected count must be a non-negative whole number.',
            ]);
        }
        $count = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 4_294_967_295]]);
        if ($count === false) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'potentially_affected_subject_count' => 'Potentially affected count must be within the supported range.',
            ]);
        }

        return $count;
    }
}
