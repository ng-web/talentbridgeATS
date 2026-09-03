<?php

namespace App\Console\Commands;

use App\Models\DispositionPlan;
use App\Models\DispositionPlanItem;
use App\Models\User;
use App\Services\Documents\ApplicantDocumentLifecycle;
use App\Services\Privacy\RetentionDataCategories;
use App\Services\Privacy\RetentionRuleRegistryService;
use App\Services\Security\PrivacyAuditService;
use App\Support\PrivacySecurityPermissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ReconcileDisposition extends Command
{
    protected $signature = 'privacy:reconcile-disposition {--user_id=} {--execute : Repair only provably stale execution claims}';

    protected $description = 'Aggregate-only disposition recovery report (dry-run by default)';

    public function handle(PrivacyAuditService $audit, ApplicantDocumentLifecycle $lifecycle, RetentionRuleRegistryService $rules): int
    {
        $actor = User::query()->find((int) $this->option('user_id'));
        if (! $actor || ! $actor->hasRole('admin') || ! $actor->hasDirectPermission(PrivacySecurityPermissions::DISPOSITION_RECONCILE)) {
            $this->error('A current specifically authorized administrator ID is required.');

            return self::FAILURE;
        }
        $cutoff = now()->subMinutes(30);
        $query = DispositionPlan::query()->where('status', DispositionPlan::STATUS_EXECUTING)->where('execution_started_at', '<=', $cutoff);
        $count = (clone $query)->count();
        $cleanupPending = DispositionPlanItem::query()->where('file_cleanup_status', DispositionPlanItem::CLEANUP_PENDING)->count();
        $cleanupFailed = DispositionPlanItem::query()->where('file_cleanup_status', DispositionPlanItem::CLEANUP_FAILED)->count();
        $this->line('Stale execution claims: '.$count);
        $this->line('Pending file cleanups: '.$cleanupPending);
        $this->line('Failed file cleanups requiring queue/hold review: '.$cleanupFailed);
        if (! $this->option('execute')) {
            $this->info('DRY RUN: no state was modified.');

            return self::SUCCESS;
        }
        $result = DB::transaction(function () use ($query, $audit, $actor, $count, $cleanupPending, $cleanupFailed, $lifecycle, $rules): ?array {
            $rules->lockCategory(RetentionDataCategories::APPLICANT_DOCUMENT);
            $currentRule = $rules->governingCurrent(RetentionDataCategories::APPLICANT_DOCUMENT);
            $currentActor = User::withTrashed()->lockForUpdate()->find($actor->id);
            if (! $currentActor || $currentActor->trashed() || ! $currentActor->hasRole('admin')
                || ! $currentActor->hasDirectPermission(PrivacySecurityPermissions::DISPOSITION_RECONCILE)) {
                return null;
            }

            $plans = $query->lockForUpdate()->get();
            foreach ($plans as $plan) {
                $plan->forceFill(['status' => DispositionPlan::STATUS_REVIEW_REQUIRED, 'execution_token' => null])->save();
            }

            $cleanupItems = DispositionPlanItem::query()
                ->with('plan')
                ->where('status', DispositionPlanItem::STATUS_DISPOSED)
                ->whereIn('file_cleanup_status', [DispositionPlanItem::CLEANUP_PENDING, DispositionPlanItem::CLEANUP_FAILED])
                ->whereNotNull('cleanup_path')
                ->lockForUpdate()
                ->get()
                ->filter(fn (DispositionPlanItem $item): bool => $item->plan?->data_category === RetentionDataCategories::APPLICANT_DOCUMENT
                    && $currentRule
                    && (int) $item->plan->retention_rule_id === (int) $currentRule->id
                    && (int) $item->plan->retention_rule_version === (int) $currentRule->version
                    && hash_equals((string) $item->plan->retention_rule_hash, (string) $currentRule->canonical_configuration_hash)
                    && is_string($item->cleanup_path)
                    && preg_match('/^[a-f0-9]{64}$/', (string) $item->artifact_fingerprint) === 1
                    && hash_equals((string) $item->artifact_fingerprint, hash('sha256', $item->cleanup_path))
                    && Str::isUuid((string) $item->artifact_revision));

            foreach ($cleanupItems as $item) {
                $item->forceFill(['file_cleanup_status' => DispositionPlanItem::CLEANUP_PENDING])->save();
                $lifecycle->deleteAfterCommit($item->cleanup_path, [
                    'subject_user_id' => (int) $item->subject_user_id,
                    'data_category' => RetentionDataCategories::APPLICANT_DOCUMENT,
                    'resource_id' => (int) $item->resource_id,
                    'plan_item_id' => (int) $item->id,
                    'artifact_revision' => (string) $item->artifact_revision,
                    'artifact_fingerprint' => (string) $item->artifact_fingerprint,
                ]);
            }

            $repairCount = $plans->count() + $cleanupItems->count();
            $audit->record('disposition_reconciled', $currentActor, metadata: ['repair_count' => $repairCount, 'report_count' => $count + $cleanupPending + $cleanupFailed]);

            return [$plans->count(), $cleanupItems->count()];
        });
        if ($result === null) {
            $this->error('Current reconciliation authority is required; no state was modified.');

            return self::FAILURE;
        }
        [$repaired, $redispatched] = $result;
        $this->info('Repaired stale claims: '.$repaired.'. Redispatched safe cleanup intents: '.$redispatched.'. No subject data was disposed.');

        return self::SUCCESS;
    }
}
