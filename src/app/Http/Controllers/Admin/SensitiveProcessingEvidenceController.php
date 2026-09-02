<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PolicyDocument;
use App\Models\SensitiveProcessingEvidence;
use App\Models\User;
use App\Services\Security\PrivacyAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class SensitiveProcessingEvidenceController extends Controller
{
    public function store(Request $request, PrivacyAuditService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'category' => ['required', Rule::in(SensitiveProcessingEvidence::CATEGORIES)],
            'purpose_code' => ['required', Rule::in(config('privacy.sensitive_processing.purpose_codes', []))],
            'policy_document_id' => ['nullable', 'integer', Rule::exists('policy_documents', 'id')],
            'evidence_type' => ['required', Rule::in(config('privacy.sensitive_processing.evidence_types', []))],
            'controller_reference' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:\/-]*$/'],
        ]);

        $subject = User::query()->findOrFail($validated['user_id']);
        abort_unless($subject->hasRole('job_seeker'), 422);

        if (isset($validated['policy_document_id'])) {
            $policy = PolicyDocument::query()->whereNotNull('approved_at')->findOrFail($validated['policy_document_id']);
            if (config('privacy.sensitive_processing.require_current_policy')) {
                $policyType = config('privacy.sensitive_processing.current_policy_type');
                $current = is_string($policyType) && in_array($policyType, PolicyDocument::TYPES, true)
                    ? PolicyDocument::current($policyType)
                    : null;
                abort_unless($current && $policy->is($current), 422);
            }
        } elseif (config('privacy.sensitive_processing.require_current_policy')) {
            abort(422, 'A current policy reference is required for this evidence rule.');
        }

        DB::transaction(function () use ($request, $validated, $subject, $audit): void {
            $evidence = SensitiveProcessingEvidence::query()->create([
                ...$validated,
                'recorded_at' => now(),
                'source_context' => 'controller_admin',
            ]);
            $audit->record(
                event: 'sensitive_processing_evidence_recorded',
                actor: $request->user(),
                resource: $evidence,
                subjectUserId: $subject->id,
                metadata: [
                    'document_type' => $evidence->category,
                    'purpose_code' => $evidence->purpose_code,
                    'evidence_type' => $evidence->evidence_type,
                ],
            );
        });

        return back()->with('success', 'Controller-approved sensitive-processing evidence recorded.');
    }
}
