<?php

namespace App\Services\Privacy;

use App\Models\JobSeekerDocument;
use App\Models\RetentionRule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

final class RetentionEligibilityService
{
    public function __construct(
        private readonly RetentionRuleRegistryService $rules,
        private readonly LegalHoldService $holds,
    ) {}

    public function evaluate(
        string $category,
        Model $resource,
        mixed $at = null,
        ?RetentionRule $boundRule = null,
        ?RetentionRule $governingRule = null,
        bool $currentHoldRead = false,
    ): RetentionEligibility {
        $at = CarbonImmutable::parse($at ?? now());
        if (! in_array($category, RetentionDataCategories::supported(), true) || RetentionDataCategories::modelFor($category) !== $resource::class) {
            return new RetentionEligibility(RetentionEligibility::UNSUPPORTED);
        }

        $rule = $governingRule ?? $this->rules->governing($category, $at);
        if (! $rule || ($boundRule && $rule->id !== $boundRule->id)) {
            return new RetentionEligibility($rule ? RetentionEligibility::AMBIGUOUS : RetentionEligibility::NO_RULE, $rule);
        }

        if (! $resource instanceof JobSeekerDocument || $rule->trigger_type !== RetentionDataCategories::TRIGGER_RECORD_CREATED) {
            return new RetentionEligibility(RetentionEligibility::AMBIGUOUS, $rule);
        }

        $resource->loadMissing('jobSeeker');
        $subjectId = $resource->jobSeeker?->user_id;
        $triggeredAt = $resource->created_at ? CarbonImmutable::parse($resource->created_at) : null;
        $artifactRevision = $resource->artifact_revision;
        $artifactFingerprint = is_string($resource->file_path) ? hash('sha256', $resource->file_path) : null;
        if (! $subjectId || ! $triggeredAt || ! is_string($artifactRevision) || ! \Illuminate\Support\Str::isUuid($artifactRevision) || ! $artifactFingerprint) {
            return new RetentionEligibility(RetentionEligibility::AMBIGUOUS, $rule);
        }

        $eligibleAt = match ($rule->retention_unit) {
            RetentionRule::UNIT_DAYS => $triggeredAt->addDays($rule->retention_value),
            RetentionRule::UNIT_MONTHS => $triggeredAt->addMonthsNoOverflow($rule->retention_value),
            RetentionRule::UNIT_YEARS => $triggeredAt->addYearsNoOverflow($rule->retention_value),
            default => null,
        };
        if (! $eligibleAt) {
            return new RetentionEligibility(RetentionEligibility::AMBIGUOUS, $rule);
        }

        $base = [$rule, $triggeredAt, $eligibleAt, (int) $subjectId, (int) $resource->id, $rule->disposition_method, $artifactRevision, $artifactFingerprint];
        $held = $currentHoldRead
            ? $this->holds->activeAppliesCurrent((int) $subjectId, $category, (int) $resource->id)
            : $this->holds->activeApplies((int) $subjectId, $category, (int) $resource->id);
        if ($held) {
            return new RetentionEligibility(RetentionEligibility::LEGAL_HOLD, ...$base);
        }
        if ($eligibleAt->isAfter($at)) {
            return new RetentionEligibility(RetentionEligibility::NOT_DUE, ...$base);
        }

        return new RetentionEligibility(RetentionEligibility::ELIGIBLE, ...$base);
    }
}
