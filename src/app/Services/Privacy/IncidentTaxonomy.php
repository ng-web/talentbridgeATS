<?php

namespace App\Services\Privacy;

final class IncidentTaxonomy
{
    public const STATUSES = ['open', 'triage', 'investigating', 'contained', 'recovering', 'resolved', 'closed'];

    public const SEVERITIES = ['informational', 'low', 'medium', 'high', 'critical'];

    public const CLASSIFICATIONS = ['suspected_security_event', 'confidentiality', 'integrity', 'availability', 'policy_control_failure', 'other_technical'];

    public const SYSTEMS = ['application', 'database', 'private_documents', 'email', 'identity_access', 'payments_metadata', 'infrastructure', 'third_party_processor'];

    public const ESCALATION_STATUSES = ['not_escalated', 'escalated', 'acknowledged'];

    public const BREACH_ASSESSMENTS = ['pending', 'controller_determined_breach', 'controller_determined_not_breach', 'requires_further_review'];

    public const NOTIFICATION_DECISIONS = ['pending', 'notify', 'do_not_notify', 'not_applicable', 'other'];

    public const DECISION_REASONS = ['initial_assessment', 'new_evidence', 'controller_review', 'corrective_record', 'other_controller_reason'];

    public const ACTION_CODES = ['triage_review', 'investigation_update', 'access_restricted', 'credential_rotation_requested', 'service_isolated', 'configuration_corrected', 'monitoring_increased', 'recovery_verified'];

    public const ACTION_RESULTS = ['requested', 'performed', 'verified', 'failed', 'not_applicable'];

    public const REOPEN_REASONS = ['new_evidence', 'recurrence', 'closure_correction', 'controller_review'];

    public const EVENT_TYPES = [
        'incident_created', 'incident_assigned', 'triage_started', 'severity_changed', 'scope_updated',
        'investigation_updated', 'containment_started', 'containment_completed', 'processor_escalated',
        'controller_acknowledged', 'controller_decision_recorded', 'recovery_started', 'recovery_completed',
        'incident_resolved', 'incident_closed', 'incident_reopened', 'evidence_preserved', 'review_required',
    ];

    /** @return array<string, list<string>> */
    public static function transitions(): array
    {
        return [
            'open' => ['triage'],
            'triage' => ['investigating'],
            'investigating' => ['contained'],
            'contained' => ['recovering'],
            'recovering' => ['resolved'],
            'resolved' => [],
            'closed' => [],
        ];
    }

    /** @return array<string, array<string, list<string>>> */
    public static function transitionEvidence(): array
    {
        return [
            'triage' => [
                'triage_review' => ['requested', 'performed', 'verified'],
            ],
            'investigating' => [
                'investigation_update' => ['requested', 'performed', 'verified'],
            ],
            'contained' => [
                'access_restricted' => ['performed', 'verified'],
                'service_isolated' => ['performed', 'verified'],
                'configuration_corrected' => ['performed', 'verified'],
                'monitoring_increased' => ['performed', 'verified'],
            ],
            'recovering' => [
                'configuration_corrected' => ['performed', 'verified'],
                'monitoring_increased' => ['performed', 'verified'],
                'recovery_verified' => ['performed', 'verified'],
            ],
            'resolved' => [
                'recovery_verified' => ['verified'],
            ],
        ];
    }

    public static function supportsTransitionEvidence(string $target, string $action, string $result): bool
    {
        return in_array($result, self::transitionEvidence()[$target][$action] ?? [], true);
    }
}
