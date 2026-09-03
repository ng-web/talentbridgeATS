<?php

namespace App\Services\Privacy;

use App\Models\RetentionRule;
use Carbon\CarbonImmutable;

final readonly class RetentionEligibility
{
    public const ELIGIBLE = 'eligible';

    public const NOT_DUE = 'not_due';

    public const NO_RULE = 'no_rule';

    public const LEGAL_HOLD = 'legal_hold';

    public const UNSUPPORTED = 'unsupported';

    public const AMBIGUOUS = 'ambiguous';

    public const ALREADY_DISPOSED = 'already_disposed';

    public const REQUIRES_AUTHORIZATION = 'requires_authorization';

    public function __construct(
        public string $status,
        public ?RetentionRule $rule = null,
        public ?CarbonImmutable $triggeredAt = null,
        public ?CarbonImmutable $eligibleAt = null,
        public ?int $subjectUserId = null,
        public ?int $resourceId = null,
        public ?string $dispositionMethod = null,
        public ?string $artifactRevision = null,
        public ?string $artifactFingerprint = null,
    ) {}

    public function snapshotHash(): string
    {
        return hash('sha256', json_encode([
            'rule' => $this->rule?->canonical_configuration_hash,
            'category' => $this->rule?->data_category,
            'resource_id' => $this->resourceId,
            'subject_user_id' => $this->subjectUserId,
            'triggered_at' => $this->triggeredAt?->toIso8601String(),
            'eligible_at' => $this->eligibleAt?->toIso8601String(),
            'method' => $this->dispositionMethod,
            'artifact_revision' => $this->artifactRevision,
            'artifact_fingerprint' => $this->artifactFingerprint,
        ], JSON_THROW_ON_ERROR));
    }
}
