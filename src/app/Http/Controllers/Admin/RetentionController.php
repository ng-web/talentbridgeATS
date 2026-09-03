<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DispositionPlan;
use App\Models\JobSeekerDocument;
use App\Models\LegalHold;
use App\Models\RetentionRule;
use App\Services\Privacy\DispositionExecutorEvidence;
use App\Services\Privacy\DispositionService;
use App\Services\Privacy\LegalHoldService;
use App\Services\Privacy\RetentionDataCategories;
use App\Services\Privacy\RetentionEligibility;
use App\Services\Privacy\RetentionEligibilityService;
use App\Services\Privacy\RetentionRuleRegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class RetentionController extends Controller
{
    public function index(Request $request, RetentionRuleRegistryService $registry, RetentionEligibilityService $eligibility): View
    {
        $direct = fn (string $permission): bool => $request->user()->hasDirectPermission($permission);
        $canRetention = $direct('privacy.retention.view');
        $canHolds = $direct('privacy.holds.view');
        $canDisposition = $direct('privacy.disposition.plan')
            || $direct('privacy.disposition.authorize')
            || $direct('privacy.disposition.execute');
        $governing = ($canRetention || $canDisposition) ? $registry->governing(RetentionDataCategories::APPLICANT_DOCUMENT) : null;
        $eligibleCount = 0;
        if ($governing) {
            JobSeekerDocument::query()->with('jobSeeker')->orderBy('id')->chunkById(200, function ($documents) use (&$eligibleCount, $eligibility, $governing): void {
                foreach ($documents as $document) {
                    if ($eligibility->evaluate(RetentionDataCategories::APPLICANT_DOCUMENT, $document, boundRule: $governing)->status === RetentionEligibility::ELIGIBLE) {
                        $eligibleCount++;
                    }
                }
            });
        }

        return view('admin.retention.index', [
            'rules' => $canRetention ? RetentionRule::query()->with('approvedBy')->orderByDesc('id')->get() : collect(),
            'holds' => $canHolds ? LegalHold::query()->with(['subject', 'issuedBy', 'releasedBy'])->orderByDesc('id')->limit(100)->get() : collect(),
            'plans' => $canDisposition ? DispositionPlan::query()->with('rule')->withCount([
                'items',
                'items as disposed_items_count' => fn ($query) => $query->where('status', 'disposed'),
                'items as skipped_items_count' => fn ($query) => $query->where('status', 'skipped'),
                'items as failed_items_count' => fn ($query) => $query->where('status', 'failed'),
                'items as pending_cleanup_count' => fn ($query) => $query->where('file_cleanup_status', 'pending'),
                'items as failed_cleanup_count' => fn ($query) => $query->where('file_cleanup_status', 'failed'),
            ])->orderByDesc('id')->limit(100)->get() : collect(),
            'categories' => RetentionDataCategories::all(),
            'supportedCategories' => RetentionDataCategories::supported(),
            'eligibleCount' => $eligibleCount,
            'governing' => $governing,
            'directPermissions' => collect(\App\Support\PrivacySecurityPermissions::introducedInPass3())
                ->mapWithKeys(fn (string $permission): array => [$permission => $direct($permission)])
                ->all(),
        ]);
    }

    public function storeRule(Request $request, RetentionRuleRegistryService $registry): RedirectResponse
    {
        $validated = $request->validate([
            'data_category' => ['required', Rule::in(RetentionDataCategories::all())],
            'version' => ['required', 'integer', 'min:1', Rule::unique('retention_rules')->where(fn ($q) => $q->where('data_category', $request->string('data_category')->toString()))],
            'trigger_type' => ['required', Rule::in([RetentionDataCategories::TRIGGER_RECORD_CREATED])],
            'retention_value' => ['required', 'integer', 'min:0', 'max:36500'],
            'retention_unit' => ['required', Rule::in(RetentionRule::UNITS)],
            'disposition_method' => ['required', Rule::in([RetentionDataCategories::METHOD_DETACH_AND_DELETE_FILE])],
            'effective_at' => ['required', 'date'],
        ]);
        $registry->createDraft($request->user(), $validated);

        return back()->with('success', 'Draft technical rule registered. It has no authority until separately approved.');
    }

    public function approveRule(Request $request, RetentionRule $retentionRule, RetentionRuleRegistryService $registry): RedirectResponse
    {
        $registry->approve($request->user(), $retentionRule);

        return back()->with('success', 'Controller-approved rule activated at its configured effective time.');
    }

    public function retireRule(Request $request, RetentionRule $retentionRule, RetentionRuleRegistryService $registry): RedirectResponse
    {
        $registry->retire($request->user(), $retentionRule);

        return back()->with('success', 'Rule retired. No replacement was inferred.');
    }

    public function storeHold(Request $request, LegalHoldService $holds): RedirectResponse
    {
        $validated = $request->validate([
            'subject_user_id' => ['required', 'integer', 'exists:users,id'],
            'scope_type' => ['required', Rule::in(LegalHold::SCOPES)],
            'data_category' => ['nullable', Rule::in(RetentionDataCategories::all())],
            'resource_id' => ['nullable', 'integer', 'min:1'],
            'hold_code' => ['required', Rule::in(LegalHold::HOLD_CODES)],
            'effective_at' => ['required', 'date'],
        ]);
        $holds->issue($request->user(), $validated);

        return back()->with('success', 'Legal hold issued and immediately enforced according to its effective time.');
    }

    public function releaseHold(Request $request, LegalHold $legalHold, LegalHoldService $holds): RedirectResponse
    {
        $holds->release($request->user(), $legalHold);

        return back()->with('success', 'Hold released. Historical evidence was retained.');
    }

    public function createPlan(Request $request, RetentionRuleRegistryService $registry, RetentionEligibilityService $eligibility, DispositionService $disposition): RedirectResponse
    {
        $category = $request->validate(['data_category' => ['required', Rule::in(RetentionDataCategories::supported())]])['data_category'];
        $rule = $registry->governing($category);
        if (! $rule) {
            return back()->withErrors(['rule' => 'No current approved rule exists. No plan was created.']);
        }
        $eligible = JobSeekerDocument::query()->with('jobSeeker')->orderBy('id')->get()->filter(fn ($resource) => $eligibility->evaluate($category, $resource, boundRule: $rule)->status === RetentionEligibility::ELIGIBLE);
        $plan = $disposition->createPlan($request->user(), $rule, $eligible);

        return back()->with('success', "Reviewable plan {$plan->uuid} created. Subject data was not modified.");
    }

    public function authorizePlan(Request $request, DispositionPlan $dispositionPlan, DispositionService $disposition): RedirectResponse
    {
        $disposition->authorize($request->user(), $dispositionPlan);

        return back()->with('success', 'Plan authorized for a limited technical window. Execution still requires revalidation.');
    }

    public function revokePlan(Request $request, DispositionPlan $dispositionPlan, DispositionService $disposition): RedirectResponse
    {
        $disposition->revoke($request->user(), $dispositionPlan);

        return back()->with('success', 'Plan revoked.');
    }

    public function executePlan(Request $request, DispositionPlan $dispositionPlan, DispositionService $disposition): RedirectResponse
    {
        $result = $disposition->execute(DispositionExecutorEvidence::fromAuthenticatedRequest($request), $dispositionPlan);

        return back()->with('success', 'Execution finished with status: '.$result->status.'. Review item outcomes before further action.');
    }
}
