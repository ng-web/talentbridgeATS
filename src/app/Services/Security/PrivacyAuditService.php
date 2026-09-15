<?php

namespace App\Services\Security;

use App\Models\AuditLog;
use App\Models\Entitlement;
use App\Models\JobSeekerDocument;
use App\Models\User;
use App\Services\Documents\ApplicantDocumentStorage;
use App\Support\PrivacySecurityPermissions;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PrivacyAuditService
{
    public const OUTCOME_SUCCESS = 'success';

    public const OUTCOME_DENIED = 'denied';

    public const OUTCOME_FAILURE = 'failure';

    public const OUTCOME_PENDING = 'pending';

    /** @var array<string, list<string>> */
    private const ALLOWED_METADATA = [
        'applicant_document_migrated_private' => ['document_category', 'source_disk', 'destination_disk'],
        'sensitive_document_downloaded' => ['actor_role', 'document_type', 'applicant_user_id'],
        'entitlement.deleted' => ['user_id', 'type', 'status', 'expires_at'],
        'user_force_delete_attempted' => ['blocked_by'],
        'user_soft_deleted' => ['session_revocation_count'],
        'user_force_deleted' => ['session_revocation_count'],
        'password_change_required' => ['session_revocation_count'],
        'password_change_requirement_cleared' => [],
        'job_seeker_program_assigned' => ['old_program_id', 'new_program_id'],
        'job_seeker_program_changed' => ['old_program_id', 'new_program_id'],
        'job_seeker_program_cleared' => ['old_program_id', 'new_program_id'],
        'access_granted' => ['type', 'expires_at'],
        'access_revoked' => ['type'],
        'admin_login_succeeded' => ['auth_method', 'mfa_method'],
        'admin_login_failed' => ['auth_method'],
        'admin_mfa_enrollment_started' => ['mfa_method'],
        'admin_mfa_enabled' => ['mfa_method', 'session_revocation_count'],
        'admin_mfa_recovery_codes_regenerated' => ['mfa_method', 'session_revocation_count'],
        'admin_mfa_challenge_failed' => ['mfa_method'],
        'admin_mfa_reset' => ['mfa_method', 'session_revocation_count'],
        'account_setup_link_issued' => ['account_role', 'delivery_status', 'session_revocation_count'],
        'account_setup_link_delivery_succeeded' => ['account_role', 'delivery_status', 'session_revocation_count'],
        'account_setup_link_delivery_failed' => ['account_role', 'delivery_status', 'session_revocation_count'],
        'employer_account_provisioned' => ['account_role', 'access_granted'],
        'account_password_established' => ['account_role', 'session_revocation_count'],
        'password_reset_completed' => ['account_role', 'session_revocation_count'],
        'password_updated' => ['session_revocation_count'],
        'session_revoked' => ['session_revocation_count', 'revocation_scope'],
        'account_suspended' => ['session_revocation_count'],
        'privileged_reauthentication_succeeded' => ['operation'],
        'privileged_reauthentication_failed' => ['operation'],
        'admin_permission_changed' => ['permission', 'change'],
        'admin_role_changed' => ['role', 'change'],
        'policy_document_created' => ['policy_type', 'policy_version'],
        'policy_document_activated' => ['policy_type', 'policy_version'],
        'policy_acknowledged' => ['policy_type', 'policy_version'],
        'sensitive_processing_evidence_recorded' => ['document_type', 'purpose_code', 'evidence_type'],
        'privacy_request_submitted' => ['request_type', 'request_state'],
        'privacy_request_assigned' => ['request_type', 'request_state'],
        'privacy_request_identity_verified' => ['request_type', 'request_state'],
        'privacy_request_state_changed' => ['request_type', 'request_state'],
        'privacy_request_decision_recorded' => ['request_type', 'request_state'],
        'privacy_request_completed' => ['request_type', 'request_state'],
        'data_export_authorized' => ['export_status', 'scope_count'],
        'data_export_reauthorized' => ['export_status', 'scope_count'],
        'data_export_generation_queued' => ['export_status', 'scope_count'],
        'data_export_generation_retried' => ['export_status', 'scope_count'],
        'data_export_generation_reconciled' => ['export_status'],
        'data_export_generated' => ['export_status', 'scope_count'],
        'data_export_downloaded' => ['export_status'],
        'data_export_expired' => ['export_status'],
        'data_export_purged' => ['export_status'],
        'data_export_purge_completed' => ['record_count'],
        'retention_rule_created' => ['data_category', 'rule_version', 'rule_status'],
        'retention_rule_approved' => ['data_category', 'rule_version', 'rule_status'],
        'retention_rule_retired' => ['data_category', 'rule_version', 'rule_status'],
        'legal_hold_created' => ['hold_scope', 'hold_code', 'data_category'],
        'legal_hold_released' => ['hold_scope', 'hold_code', 'data_category'],
        'disposition_plan_created' => ['data_category', 'item_count', 'plan_status'],
        'disposition_plan_authorized' => ['data_category', 'item_count', 'plan_status'],
        'disposition_plan_revoked' => ['data_category', 'plan_status'],
        'disposition_execution_started' => ['data_category', 'item_count', 'plan_status'],
        'disposition_succeeded' => ['data_category', 'disposition_method', 'outcome_code'],
        'disposition_skipped' => ['data_category', 'disposition_method', 'outcome_code'],
        'disposition_failed' => ['data_category', 'disposition_method', 'outcome_code'],
        'disposition_reconciled' => ['repair_count', 'report_count'],
        'incident.created' => ['incident_status', 'technical_severity', 'classification_code'],
        'incident.assigned' => ['incident_status'],
        'incident.state_changed' => ['from_status', 'incident_status', 'action_code', 'action_result'],
        'incident.severity_changed' => ['technical_severity'],
        'incident.scope_changed' => ['scope_count', 'system_count', 'subject_count_recorded'],
        'incident.escalated' => ['escalation_status'],
        'incident.controller_acknowledged' => ['escalation_status'],
        'incident.controller_decision_recorded' => ['breach_assessment', 'authority_decision', 'subject_decision', 'decision_version', 'reason_code'],
        'incident.hold_issued' => ['hold_scope', 'hold_code', 'data_category'],
        'incident.closed' => ['incident_status'],
        'incident.reopened' => ['incident_status', 'reason_code'],
        'incident.reconciliation_review' => ['incident_status', 'repair_count', 'report_count'],
    ];

    /**
     * @param  array<string, scalar|null|list<scalar|null>>  $metadata
     */
    public function record(
        string $event,
        ?User $actor = null,
        ?Model $resource = null,
        ?int $subjectUserId = null,
        string $outcome = self::OUTCOME_SUCCESS,
        ?string $reasonCode = null,
        array $metadata = [],
        ?string $correlationId = null,
    ): AuditLog {
        $this->assertCode($event, 'event');

        if (! in_array($outcome, [self::OUTCOME_SUCCESS, self::OUTCOME_DENIED, self::OUTCOME_FAILURE, self::OUTCOME_PENDING], true)) {
            throw new InvalidArgumentException('Unsupported audit outcome.');
        }

        if ($reasonCode !== null) {
            $this->assertCode($reasonCode, 'reason code');
        }

        $correlationId ??= (string) Str::uuid();

        if (! Str::isUuid($correlationId)) {
            throw new InvalidArgumentException('Audit correlation ID must be a UUID.');
        }

        $this->assertMetadataAllowed($event, $metadata);

        return AuditLog::query()->create([
            'actor_user_id' => $actor?->getKey(),
            'subject_user_id' => $subjectUserId,
            'action' => $event,
            'entity_type' => $resource?->getMorphClass(),
            'entity_id' => $resource?->getKey(),
            'correlation_id' => $correlationId,
            'outcome' => $outcome,
            'reason_code' => $reasonCode,
            'occurred_at' => now(),
            'meta' => $metadata === [] ? null : $metadata,
        ]);
    }

    private function assertCode(string $value, string $label): void
    {
        if (! preg_match('/^[a-z0-9][a-z0-9_.:-]{0,99}$/', $value)) {
            throw new InvalidArgumentException("Invalid audit {$label}.");
        }
    }

    /** @param array<string, mixed> $metadata */
    private function assertMetadataAllowed(string $event, array $metadata): void
    {
        $allowed = self::ALLOWED_METADATA[$event] ?? [];

        foreach ($metadata as $key => $value) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException("Audit metadata key [{$key}] is not allowed for [{$event}].");
            }

            $values = is_array($value) ? $value : [$value];

            if (is_array($value) && ! array_is_list($value)) {
                throw new InvalidArgumentException('Nested audit metadata is not allowed.');
            }

            foreach ($values as $item) {
                if (! is_scalar($item) && $item !== null) {
                    throw new InvalidArgumentException('Audit metadata values must be scalar.');
                }

                if (is_string($item) && (mb_strlen($item) > 255 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $item))) {
                    throw new InvalidArgumentException('Audit metadata contains an unsafe value.');
                }

                $this->assertMetadataValueAllowed($event, $key, $item);
            }
        }
    }

    private function assertMetadataValueAllowed(string $event, string $key, mixed $value): void
    {
        $allowedValues = match ($key) {
            'account_role', 'actor_role' => ['admin', 'employer', 'job_seeker', 'unassigned'],
            'auth_method' => ['password', 'remember'],
            'mfa_method' => [AdministratorMfaSession::METHOD_TOTP, AdministratorMfaSession::METHOD_RECOVERY_CODE],
            'delivery_status' => ['pending', 'sent', 'failed'],
            'operation' => ['password_confirmation'],
            'permission' => PrivacySecurityPermissions::all(),
            'change' => ['granted', 'revoked'],
            'role' => ['admin', 'employer', 'job_seeker'],
            'revocation_scope' => ['all_sessions', 'other_sessions'],
            'type' => Entitlement::TYPES,
            'status' => Entitlement::STATUSES,
            'document_type' => JobSeekerDocument::TYPES,
            'source_disk' => [ApplicantDocumentStorage::LEGACY_PUBLIC_DISK],
            'destination_disk' => [ApplicantDocumentStorage::PRIVATE_DISK],
            'blocked_by' => [
                'payments',
                'entitlements',
                'applications',
                'applicant documents',
                'payment assistance or contact history',
                'employer jobs',
                'admin overrides',
                'audit logs',
                'privacy evidence or requests',
            ],
            'policy_type' => \App\Models\PolicyDocument::TYPES,
            'request_type' => \App\Models\PrivacyRequest::TYPES,
            'request_state' => \App\Models\PrivacyRequest::STATES,
            'export_status' => \App\Models\DataExport::STATUSES,
            'purpose_code' => config('privacy.sensitive_processing.purpose_codes', []),
            'evidence_type' => config('privacy.sensitive_processing.evidence_types', []),
            'data_category' => \App\Services\Privacy\RetentionDataCategories::all(),
            'incident_status', 'from_status' => \App\Services\Privacy\IncidentTaxonomy::STATUSES,
            'technical_severity' => \App\Services\Privacy\IncidentTaxonomy::SEVERITIES,
            'classification_code' => \App\Services\Privacy\IncidentTaxonomy::CLASSIFICATIONS,
            'action_code' => \App\Services\Privacy\IncidentTaxonomy::ACTION_CODES,
            'action_result' => \App\Services\Privacy\IncidentTaxonomy::ACTION_RESULTS,
            'escalation_status' => \App\Services\Privacy\IncidentTaxonomy::ESCALATION_STATUSES,
            'breach_assessment' => \App\Services\Privacy\IncidentTaxonomy::BREACH_ASSESSMENTS,
            'authority_decision', 'subject_decision' => \App\Services\Privacy\IncidentTaxonomy::NOTIFICATION_DECISIONS,
            'rule_status' => \App\Models\RetentionRule::STATUSES,
            'hold_scope' => \App\Models\LegalHold::SCOPES,
            'hold_code' => \App\Models\LegalHold::HOLD_CODES,
            'plan_status' => \App\Models\DispositionPlan::STATUSES,
            'disposition_method' => [\App\Services\Privacy\RetentionDataCategories::METHOD_DETACH_AND_DELETE_FILE],
            'outcome_code' => [
                'disposed', 'no_rule', 'legal_hold', 'unsupported', 'ambiguous',
                'already_disposed', 'not_due', 'requires_authorization', 'stale_claim',
                'stale_authority', 'execution_failed',
            ],
            default => null,
        };

        if ($allowedValues !== null && ! in_array($value, $allowedValues, true)) {
            throw new InvalidArgumentException("Audit metadata value for [{$event}.{$key}] is not allowed.");
        }

        if (in_array($key, ['applicant_user_id', 'user_id', 'old_program_id', 'new_program_id', 'session_revocation_count'], true)
            && $value !== null
            && (! is_int($value) || $value < 0)) {
            throw new InvalidArgumentException("Audit metadata value for [{$event}.{$key}] must be a non-negative integer.");
        }

        if (in_array($key, ['scope_count', 'system_count', 'record_count'], true)
            && (! is_int($value) || $value < 0)) {
            throw new InvalidArgumentException("Audit metadata value for [{$event}.{$key}] must be a non-negative integer.");
        }

        if (in_array($key, ['rule_version', 'decision_version', 'item_count', 'repair_count', 'report_count'], true)
            && (! is_int($value) || $value < 0)) {
            throw new InvalidArgumentException("Audit metadata value for [{$event}.{$key}] must be a non-negative integer.");
        }

        if ($key === 'policy_version'
            && (! is_string($value) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,49}$/', $value))) {
            throw new InvalidArgumentException("Audit metadata value for [{$event}.{$key}] is not a policy version.");
        }

        if ($key === 'access_granted' && ! is_bool($value)) {
            throw new InvalidArgumentException("Audit metadata value for [{$event}.{$key}] must be boolean.");
        }

        if ($key === 'subject_count_recorded' && ! is_bool($value)) {
            throw new InvalidArgumentException("Audit metadata value for [{$event}.{$key}] must be boolean.");
        }

        if ($key === 'document_category'
            && (! is_string($value) || ! preg_match('/^[a-z0-9_.:-]{1,100}$/', $value))) {
            throw new InvalidArgumentException("Audit metadata value for [{$event}.{$key}] is not a document category.");
        }

        if ($key === 'expires_at' && $value !== null) {
            if (! is_string($value)) {
                throw new InvalidArgumentException("Audit metadata value for [{$event}.{$key}] must be a date-time string.");
            }

            try {
                new DateTimeImmutable($value);
            } catch (\Exception) {
                throw new InvalidArgumentException("Audit metadata value for [{$event}.{$key}] must be a valid date-time.");
            }
        }
    }
}
