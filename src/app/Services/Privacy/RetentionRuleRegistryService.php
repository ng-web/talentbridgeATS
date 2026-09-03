<?php

namespace App\Services\Privacy;

use App\Models\RetentionRule;
use App\Models\User;
use App\Services\Security\PrivacyAuditService;
use App\Support\PrivacySecurityPermissions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RetentionRuleRegistryService
{
    public function __construct(private readonly PrivacyAuditService $audit) {}

    public function createDraft(User $actor, array $attributes): RetentionRule
    {
        $this->assertActor($actor, PrivacySecurityPermissions::RETENTION_MANAGE);
        if (! in_array($attributes['data_category'], RetentionDataCategories::all(), true)
            || ! in_array($attributes['trigger_type'], RetentionDataCategories::triggersFor($attributes['data_category']), true)
            || ! in_array($attributes['disposition_method'], RetentionDataCategories::methodsFor($attributes['data_category']), true)
            || ! in_array($attributes['retention_unit'], RetentionRule::UNITS, true)
            || ! is_int($attributes['retention_value']) || $attributes['retention_value'] < 0) {
            throw ValidationException::withMessages(['data_category' => 'This category, trigger, or disposition combination is unsupported and remains fail-closed.']);
        }

        $canonical = $this->canonical($attributes);

        return DB::transaction(function () use ($actor, $attributes, $canonical): RetentionRule {
            $rule = RetentionRule::query()->create([
                ...$attributes,
                'status' => RetentionRule::STATUS_DRAFT,
                'canonical_configuration_hash' => hash('sha256', $canonical),
            ]);
            $this->audit->record('retention_rule_created', $actor, $rule, metadata: ['data_category' => $rule->data_category, 'rule_version' => $rule->version, 'rule_status' => $rule->status]);

            return $rule;
        });
    }

    public function approve(User $actor, RetentionRule $rule): RetentionRule
    {
        $controller = $this->assertActor($actor, PrivacySecurityPermissions::RETENTION_APPROVE);

        return DB::transaction(function () use ($controller, $rule): RetentionRule {
            $this->lockCategory($rule->data_category);
            $controller = $this->lockActor($controller->id, PrivacySecurityPermissions::RETENTION_APPROVE);
            $rules = RetentionRule::query()->where('data_category', $rule->data_category)->orderBy('id')->lockForUpdate()->get();
            $current = $rules->firstWhere('id', $rule->id);
            if (! $current || $current->status !== RetentionRule::STATUS_DRAFT || ! hash_equals($current->canonical_configuration_hash, hash('sha256', $this->canonical($current->only(['data_category', 'version', 'trigger_type', 'retention_value', 'retention_unit', 'disposition_method', 'effective_at']))))) {
                throw ValidationException::withMessages(['rule' => 'Only an unchanged draft rule can be approved.']);
            }
            $historical = $rules->where('id', '!=', $current->id);
            $highestVersion = $historical->max('version');
            $latestEffective = $historical->sortByDesc('effective_at')->first()?->effective_at;
            if (($highestVersion !== null && $current->version <= $highestVersion)
                || ($latestEffective && ! $current->effective_at->gt($latestEffective))) {
                throw ValidationException::withMessages(['rule' => 'A replacement must advance both version and effective timeline.']);
            }

            RetentionRule::query()->where('data_category', $current->data_category)
                ->where('status', RetentionRule::STATUS_APPROVED)->whereNull('retired_at')
                ->whereKeyNot($current->id)->update(['retired_at' => $current->effective_at, 'updated_at' => now()]);
            RetentionRule::query()->whereKey($current->id)->update([
                'status' => RetentionRule::STATUS_APPROVED,
                'approved_by_user_id' => $controller->id,
                'approved_at' => now(),
                'updated_at' => now(),
            ]);
            $approved = RetentionRule::query()->findOrFail($current->id);
            $this->audit->record('retention_rule_approved', $controller, $approved, metadata: ['data_category' => $approved->data_category, 'rule_version' => $approved->version, 'rule_status' => $approved->status]);

            return $approved;
        });
    }

    public function retire(User $actor, RetentionRule $rule): RetentionRule
    {
        $controller = $this->assertActor($actor, PrivacySecurityPermissions::RETENTION_APPROVE);

        return DB::transaction(function () use ($controller, $rule): RetentionRule {
            $this->lockCategory($rule->data_category);
            $controller = $this->lockActor($controller->id, PrivacySecurityPermissions::RETENTION_APPROVE);
            $rules = RetentionRule::query()->where('data_category', $rule->data_category)->orderBy('id')->lockForUpdate()->get();
            $current = $rules->firstWhere('id', $rule->id);
            if (! $current) {
                throw ValidationException::withMessages(['rule' => 'The rule no longer exists.']);
            }
            if ($current->status !== RetentionRule::STATUS_APPROVED) {
                throw ValidationException::withMessages(['rule' => 'Only an approved rule can be retired.']);
            }
            RetentionRule::query()->whereKey($current->id)->update(['status' => RetentionRule::STATUS_RETIRED, 'retired_at' => now(), 'updated_at' => now()]);
            if ($current->effective_at->isFuture()) {
                $predecessor = $rules->where('status', RetentionRule::STATUS_APPROVED)
                    ->where('id', '!=', $current->id)
                    ->filter(fn (RetentionRule $candidate): bool => $candidate->retired_at?->equalTo($current->effective_at) === true)
                    ->sortByDesc('effective_at')->first();
                if ($predecessor) {
                    RetentionRule::query()->whereKey($predecessor->id)->update(['retired_at' => null, 'updated_at' => now()]);
                }
            }
            $retired = RetentionRule::query()->findOrFail($current->id);
            $this->audit->record('retention_rule_retired', $controller, $retired, metadata: ['data_category' => $retired->data_category, 'rule_version' => $retired->version, 'rule_status' => $retired->status]);

            return $retired;
        });
    }

    public function governing(string $category, mixed $at = null): ?RetentionRule
    {
        $at ??= now();

        return RetentionRule::query()->where('data_category', $category)->where('status', RetentionRule::STATUS_APPROVED)
            ->where('effective_at', '<=', $at)->where(fn ($q) => $q->whereNull('retired_at')->orWhere('retired_at', '>', $at))
            ->orderByDesc('effective_at')->orderByDesc('version')->first();
    }

    /** Must be called inside a transaction before authoritative rule reads. */
    public function lockCategory(string $category): void
    {
        DB::table('retention_registry_locks')->insertOrIgnore([
            'data_category' => $category,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('retention_registry_locks')->where('data_category', $category)->lockForUpdate()->first();
    }

    /** Must be called after lockCategory(); this bypasses any older consistent snapshot. */
    public function governingCurrent(string $category, mixed $at = null): ?RetentionRule
    {
        $at ??= now();

        return RetentionRule::query()->where('data_category', $category)->where('status', RetentionRule::STATUS_APPROVED)
            ->where('effective_at', '<=', $at)->where(fn ($q) => $q->whereNull('retired_at')->orWhere('retired_at', '>', $at))
            ->orderByDesc('effective_at')->orderByDesc('version')->lockForUpdate()->first();
    }

    private function canonical(array $values): string
    {
        $effective = CarbonImmutable::parse($values['effective_at'])->utc()->toIso8601String();

        return implode("\n", [(string) $values['data_category'], (string) $values['version'], (string) $values['trigger_type'], (string) $values['retention_value'], (string) $values['retention_unit'], (string) $values['disposition_method'], $effective]);
    }

    private function assertActor(User $actor, string $permission): User
    {
        $current = User::query()->find($actor->id);
        if (! $current || ! $current->hasRole('admin') || ! $current->hasDirectPermission($permission)) {
            throw ValidationException::withMessages(['authorization' => 'A current specifically authorized administrator is required.']);
        }

        return $current;
    }

    private function lockActor(int $userId, string $permission): User
    {
        $current = User::withTrashed()->lockForUpdate()->find($userId);
        if (! $current || $current->trashed() || ! $current->hasRole('admin') || ! $current->hasDirectPermission($permission)) {
            throw ValidationException::withMessages(['authorization' => 'A current specifically authorized administrator is required.']);
        }

        return $current;
    }
}
