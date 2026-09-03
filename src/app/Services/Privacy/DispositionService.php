<?php

namespace App\Services\Privacy;

use App\Models\DispositionPlan;
use App\Models\DispositionPlanItem;
use App\Models\JobSeeker;
use App\Models\JobSeekerDocument;
use App\Models\RetentionRule;
use App\Models\User;
use App\Services\Security\PrivacyAuditService;
use App\Support\PrivacySecurityPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class DispositionService
{
    public function __construct(
        private readonly RetentionEligibilityService $eligibility,
        private readonly LegalHoldService $holds,
        private readonly RetentionRuleRegistryService $rules,
        private readonly PrivacyAuditService $audit,
        private readonly DispositionExecutionBarrier $barrier,
    ) {}

    /** @param iterable<JobSeekerDocument> $resources */
    public function createPlan(User $actor, RetentionRule $rule, iterable $resources): DispositionPlan
    {
        $planner = $this->actor($actor, PrivacySecurityPermissions::DISPOSITION_PLAN);
        $evaluated = [];
        foreach ($resources as $resource) {
            $result = $this->eligibility->evaluate($rule->data_category, $resource, boundRule: $rule);
            if ($result->status === RetentionEligibility::ELIGIBLE) {
                $evaluated[] = $result;
            }
        }

        return DB::transaction(function () use ($planner, $rule, $evaluated): DispositionPlan {
            $this->rules->lockCategory($rule->data_category);
            $planner = $this->lockCurrentActor($planner->id, PrivacySecurityPermissions::DISPOSITION_PLAN);
            $currentRule = $this->rules->governingCurrent($rule->data_category);
            if (! $currentRule || $currentRule->id !== $rule->id
                || ! hash_equals($currentRule->canonical_configuration_hash, $rule->canonical_configuration_hash)) {
                throw ValidationException::withMessages(['rule' => 'The rule is no longer approved.']);
            }
            $plan = DispositionPlan::query()->create([
                'retention_rule_id' => $currentRule->id,
                'data_category' => $currentRule->data_category,
                'retention_rule_version' => $currentRule->version,
                'retention_rule_hash' => $currentRule->canonical_configuration_hash,
                'status' => DispositionPlan::STATUS_PLANNED,
                'planned_at' => now(),
                'expires_at' => now()->addHours(max(1, (int) config('privacy.retention.plan_expiry_hours', 168))),
                'planned_by_user_id' => $planner->id,
            ]);
            foreach ($evaluated as $result) {
                DispositionPlanItem::query()->create([
                    'disposition_plan_id' => $plan->id,
                    'subject_user_id' => $result->subjectUserId,
                    'resource_id' => $result->resourceId,
                    'status' => DispositionPlanItem::STATUS_PLANNED,
                    'trigger_type' => $currentRule->trigger_type,
                    'triggered_at' => $result->triggeredAt,
                    'eligible_at' => $result->eligibleAt,
                    'disposition_method' => $result->dispositionMethod,
                    'artifact_revision' => $result->artifactRevision,
                    'artifact_fingerprint' => $result->artifactFingerprint,
                    'eligibility_snapshot_hash' => $result->snapshotHash(),
                ]);
            }
            $this->audit->record('disposition_plan_created', $planner, $plan, metadata: ['data_category' => $plan->data_category, 'item_count' => count($evaluated), 'plan_status' => $plan->status]);

            return $plan->load('items');
        });
    }

    public function authorize(User $actor, DispositionPlan $plan): DispositionPlan
    {
        $authorizer = $this->actor($actor, PrivacySecurityPermissions::DISPOSITION_AUTHORIZE);

        return DB::transaction(function () use ($authorizer, $plan): DispositionPlan {
            $authorizer = $this->lockCurrentActor($authorizer->id, PrivacySecurityPermissions::DISPOSITION_AUTHORIZE);
            $current = DispositionPlan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($current->status !== DispositionPlan::STATUS_PLANNED || $current->expires_at->isPast() || ! $current->items()->exists()) {
                throw ValidationException::withMessages(['plan' => 'Only a non-empty, current planned disposition can be authorized.']);
            }
            $current->forceFill([
                'status' => DispositionPlan::STATUS_AUTHORIZED,
                'authorized_by_user_id' => $authorizer->id,
                'authorized_at' => now(),
                'authorizer_security_version' => $authorizer->security_version,
            ])->save();
            $this->audit->record('disposition_plan_authorized', $authorizer, $current, metadata: ['data_category' => $current->data_category, 'item_count' => $current->items()->count(), 'plan_status' => $current->status]);

            return $current->fresh();
        });
    }

    public function revoke(User $actor, DispositionPlan $plan): DispositionPlan
    {
        $authorizer = $this->actor($actor, PrivacySecurityPermissions::DISPOSITION_AUTHORIZE);

        return DB::transaction(function () use ($authorizer, $plan): DispositionPlan {
            $authorizer = $this->lockCurrentActor($authorizer->id, PrivacySecurityPermissions::DISPOSITION_AUTHORIZE);
            $current = DispositionPlan::query()->lockForUpdate()->findOrFail($plan->id);
            if (! in_array($current->status, [DispositionPlan::STATUS_PLANNED, DispositionPlan::STATUS_AUTHORIZED], true)) {
                throw ValidationException::withMessages(['plan' => 'This plan can no longer be revoked.']);
            }
            $current->forceFill(['status' => DispositionPlan::STATUS_REVOKED, 'execution_token' => null])->save();
            $this->audit->record('disposition_plan_revoked', $authorizer, $current, metadata: ['data_category' => $current->data_category, 'plan_status' => $current->status]);

            return $current->fresh();
        });
    }

    public function execute(DispositionExecutorEvidence $evidence, DispositionPlan $plan): DispositionPlan
    {
        $executor = $this->executor($evidence);
        $token = (string) Str::uuid();
        $claimed = DB::transaction(function () use ($plan, $executor, $token): DispositionPlan {
            $current = DispositionPlan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($current->status === DispositionPlan::STATUS_COMPLETED) {
                return $current;
            }
            $this->assertAuthorization($current, $executor);
            $current->forceFill(['status' => DispositionPlan::STATUS_EXECUTING, 'execution_token' => $token, 'execution_started_at' => now()])->save();
            $this->audit->record('disposition_execution_started', $executor, $current, metadata: ['data_category' => $current->data_category, 'item_count' => $current->items()->count(), 'plan_status' => $current->status]);

            return $current->fresh();
        });

        foreach ($claimed->items()->where('status', DispositionPlanItem::STATUS_PLANNED)->orderBy('id')->get() as $item) {
            try {
                $this->executeItem($executor, $evidence, $claimed->id, $item->id, $token);
            } catch (Throwable) {
                $this->recordItemFailure($executor, $claimed->id, $item->id, $token);
            }
        }

        return DB::transaction(function () use ($claimed, $token): DispositionPlan {
            $current = DispositionPlan::query()->lockForUpdate()->findOrFail($claimed->id);
            if ($current->status !== DispositionPlan::STATUS_EXECUTING || ! hash_equals((string) $current->execution_token, $token)) {
                return $current;
            }
            $review = $current->items()->whereIn('status', [DispositionPlanItem::STATUS_SKIPPED, DispositionPlanItem::STATUS_FAILED, DispositionPlanItem::STATUS_PLANNED])->exists();
            $current->forceFill(['status' => $review ? DispositionPlan::STATUS_REVIEW_REQUIRED : DispositionPlan::STATUS_COMPLETED, 'execution_token' => null, 'completed_at' => now()])->save();

            return $current->fresh();
        });
    }

    private function executeItem(User $executor, DispositionExecutorEvidence $evidence, int $planId, int $itemId, string $token): void
    {
        $planHint = DispositionPlan::query()->findOrFail($planId);
        $itemHint = DispositionPlanItem::query()->findOrFail($itemId);
        $category = $planHint->data_category;
        $subjectUserId = (int) $itemHint->subject_user_id;
        $resourceId = (int) $itemHint->resource_id;
        $authorizerId = (int) $planHint->authorized_by_user_id;

        DB::transaction(function () use ($executor, $evidence, $planId, $itemId, $token, $category, $subjectUserId, $resourceId, $authorizerId): void {
            // Canonical destructive lock order: category, subject, resource,
            // authority users (ascending ID), then plan/item.
            $this->barrier->reached(DispositionExecutionBarrier::BEFORE_CATEGORY_FENCE);
            $this->rules->lockCategory($category);
            $this->barrier->reached(DispositionExecutionBarrier::BEFORE_SUBJECT_FENCE);
            $this->holds->lockSubject($subjectUserId);
            $resource = JobSeekerDocument::query()->lockForUpdate()->find($resourceId);
            if ($resource) {
                $profile = JobSeeker::query()->lockForUpdate()->find($resource->job_seeker_id);
                if ($profile) {
                    $resource->setRelation('jobSeeker', $profile);
                }
            }
            $this->barrier->reached(DispositionExecutionBarrier::BEFORE_AUTHORITY_FENCE);
            $authorities = $this->lockAuthorityUsers([$authorizerId, $evidence->userId]);
            $plan = DispositionPlan::query()->lockForUpdate()->findOrFail($planId);
            $item = DispositionPlanItem::query()->lockForUpdate()->findOrFail($itemId);
            if ($plan->status !== DispositionPlan::STATUS_EXECUTING || ! hash_equals((string) $plan->execution_token, $token)) {
                return;
            }
            if ($plan->data_category !== $category || (int) $plan->authorized_by_user_id !== $authorizerId
                || (int) $item->disposition_plan_id !== $planId || (int) $item->subject_user_id !== $subjectUserId
                || (int) $item->resource_id !== $resourceId) {
                return;
            }
            if (! $this->authorizationIsCurrent($plan, $evidence, $authorities)) {
                $this->skip($executor, $plan, $item, 'stale_authority');

                return;
            }
            if ($item->status !== DispositionPlanItem::STATUS_PLANNED) {
                return;
            }
            if (! $resource) {
                $this->skip($executor, $plan, $item, RetentionEligibility::ALREADY_DISPOSED);

                return;
            }
            $currentRule = $this->rules->governingCurrent($category);
            if (! $currentRule || (int) $plan->retention_rule_id !== (int) $currentRule->id
                || (int) $plan->retention_rule_version !== (int) $currentRule->version
                || ! hash_equals((string) $plan->retention_rule_hash, (string) $currentRule->canonical_configuration_hash)) {
                $this->skip($executor, $plan, $item, $currentRule ? 'stale_claim' : RetentionEligibility::NO_RULE);

                return;
            }
            $result = $this->eligibility->evaluate(
                $category,
                $resource,
                boundRule: $currentRule,
                governingRule: $currentRule,
                currentHoldRead: true,
            );
            if ($result->status !== RetentionEligibility::ELIGIBLE || ! hash_equals($item->eligibility_snapshot_hash, $result->snapshotHash())) {
                $this->skip($executor, $plan, $item, $result->status === RetentionEligibility::ELIGIBLE ? 'stale_claim' : $result->status);

                return;
            }
            if ($item->disposition_method !== RetentionDataCategories::METHOD_DETACH_AND_DELETE_FILE) {
                $this->skip($executor, $plan, $item, RetentionEligibility::UNSUPPORTED);

                return;
            }
            $resource->setRelation('__dispositionPlanItemId', $item->id);
            $item->forceFill([
                'file_cleanup_status' => DispositionPlanItem::CLEANUP_PENDING,
                'cleanup_path' => $resource->file_path,
            ])->save();
            $this->barrier->reached(DispositionExecutionBarrier::BEFORE_DESTRUCTIVE_MUTATION);
            $resource->delete();
            $item->forceFill(['status' => DispositionPlanItem::STATUS_DISPOSED, 'outcome_code' => 'disposed', 'disposed_at' => now()])->save();
            $this->audit->record('disposition_succeeded', $executor, $item, $item->subject_user_id, metadata: ['data_category' => $plan->data_category, 'disposition_method' => $item->disposition_method, 'outcome_code' => 'disposed']);
        });
    }

    private function skip(User $actor, DispositionPlan $plan, DispositionPlanItem $item, string $code): void
    {
        $item->forceFill(['status' => DispositionPlanItem::STATUS_SKIPPED, 'outcome_code' => $code])->save();
        $this->audit->record('disposition_skipped', $actor, $item, $item->subject_user_id, metadata: ['data_category' => $plan->data_category, 'disposition_method' => $item->disposition_method, 'outcome_code' => $code]);
    }

    private function recordItemFailure(User $actor, int $planId, int $itemId, string $token): void
    {
        DB::transaction(function () use ($actor, $planId, $itemId, $token): void {
            $plan = DispositionPlan::query()->lockForUpdate()->find($planId);
            $item = DispositionPlanItem::query()->lockForUpdate()->find($itemId);
            if (! $plan || ! $item || $plan->status !== DispositionPlan::STATUS_EXECUTING
                || ! hash_equals((string) $plan->execution_token, $token)
                || $item->status !== DispositionPlanItem::STATUS_PLANNED) {
                return;
            }
            $item->forceFill(['status' => DispositionPlanItem::STATUS_FAILED, 'outcome_code' => 'execution_failed'])->save();
            $this->audit->record('disposition_failed', $actor, $item, $item->subject_user_id, PrivacyAuditService::OUTCOME_FAILURE, 'technical_execution_failed', [
                'data_category' => $plan->data_category,
                'disposition_method' => $item->disposition_method,
                'outcome_code' => 'execution_failed',
            ]);
        });
    }

    private function assertAuthorization(DispositionPlan $plan, User $executor, bool $allowExecuting = false): void
    {
        if (! in_array($plan->status, $allowExecuting ? [DispositionPlan::STATUS_EXECUTING] : [DispositionPlan::STATUS_AUTHORIZED], true)
            || ! $plan->authorized_at || $plan->expires_at->isPast()
            || $plan->authorized_at->lt(now()->subHours(max(1, (int) config('privacy.retention.authorization_expiry_hours', 24))))) {
            throw ValidationException::withMessages(['authorization' => 'Disposition authorization is absent, stale, or the plan is no longer executable.']);
        }
        $authorizer = User::query()->find($plan->authorized_by_user_id);
        $currentExecutor = User::query()->find($executor->id);
        if (! $authorizer || ! $authorizer->hasRole('admin') || ! $authorizer->hasDirectPermission(PrivacySecurityPermissions::DISPOSITION_AUTHORIZE)
            || $authorizer->security_version !== $plan->authorizer_security_version
            || ! $currentExecutor || ! $currentExecutor->hasRole('admin') || ! $currentExecutor->hasDirectPermission(PrivacySecurityPermissions::DISPOSITION_EXECUTE)) {
            throw ValidationException::withMessages(['authorization' => 'Disposition authorization or executor authority is no longer valid.']);
        }
    }

    /** @param array<int, User> $authorities */
    private function authorizationIsCurrent(DispositionPlan $plan, DispositionExecutorEvidence $evidence, array $authorities): bool
    {
        if ($plan->status !== DispositionPlan::STATUS_EXECUTING
            || ! $plan->authorized_at || $plan->expires_at->isPast()
            || $plan->authorized_at->lt(now()->subHours(max(1, (int) config('privacy.retention.authorization_expiry_hours', 24))))) {
            return false;
        }
        $authorizer = $authorities[(int) $plan->authorized_by_user_id] ?? null;
        $currentExecutor = $authorities[$evidence->userId] ?? null;

        return $authorizer !== null
            && ! $authorizer->trashed()
            && $authorizer->hasRole('admin')
            && $authorizer->hasDirectPermission(PrivacySecurityPermissions::DISPOSITION_AUTHORIZE)
            && (int) $authorizer->security_version === (int) $plan->authorizer_security_version
            && $currentExecutor !== null
            && ! $currentExecutor->trashed()
            && (int) $currentExecutor->id === $evidence->userId
            && (int) $currentExecutor->security_version === $evidence->securityVersion
            && $currentExecutor->hasRole('admin')
            && $currentExecutor->hasDirectPermission(PrivacySecurityPermissions::DISPOSITION_EXECUTE);
    }

    /** @param list<int> $userIds
     * @return array<int, User>
     */
    private function lockAuthorityUsers(array $userIds): array
    {
        return User::withTrashed()->whereIn('id', array_values(array_unique($userIds)))
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id')->all();
    }

    private function actor(User $actor, string $permission): User
    {
        $current = User::query()->find($actor->id);
        if (! $current || ! $current->hasRole('admin') || ! $current->hasDirectPermission($permission)) {
            throw ValidationException::withMessages(['authorization' => 'A current specifically authorized administrator is required.']);
        }

        return $current;
    }

    private function executor(DispositionExecutorEvidence $evidence): User
    {
        $current = User::query()->find($evidence->userId);
        if (! $current || (int) $current->security_version !== $evidence->securityVersion
            || ! $current->hasRole('admin')
            || ! $current->hasDirectPermission(PrivacySecurityPermissions::DISPOSITION_EXECUTE)) {
            throw ValidationException::withMessages(['authorization' => 'The initiating disposition authority is no longer valid.']);
        }

        return $current;
    }

    private function lockCurrentActor(int $userId, string $permission): User
    {
        $current = User::withTrashed()->lockForUpdate()->find($userId);
        if (! $current || $current->trashed() || ! $current->hasRole('admin') || ! $current->hasDirectPermission($permission)) {
            throw ValidationException::withMessages(['authorization' => 'A current specifically authorized administrator is required.']);
        }

        return $current;
    }
}
