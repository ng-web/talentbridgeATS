<?php

return [
    'registration' => [
        'evidence_enabled' => (bool) env('PRIVACY_REGISTRATION_EVIDENCE_ENABLED', false),
        'require_privacy_notice' => (bool) env('PRIVACY_REQUIRE_ACTIVE_NOTICE', false),
        'require_terms' => (bool) env('PRIVACY_REQUIRE_ACTIVE_TERMS', false),
    ],

    'sensitive_processing' => [
        'enforcement_enabled' => (bool) env('PRIVACY_SENSITIVE_EVIDENCE_REQUIRED', false),
        'purpose_codes' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('PRIVACY_SENSITIVE_PURPOSE_CODES', ''))
        ))),
        'evidence_types' => [
            'controller_recorded',
            'applicant_acknowledged',
        ],
        'require_current_policy' => (bool) env('PRIVACY_SENSITIVE_REQUIRE_CURRENT_POLICY', false),
        'current_policy_type' => env('PRIVACY_SENSITIVE_CURRENT_POLICY_TYPE'),
    ],

    'identity_verification_methods' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PRIVACY_IDENTITY_VERIFICATION_METHODS', ''))
    ))),

    'exports' => [
        'disk' => 'private',
        'directory' => 'privacy-exports',
        'expiry_hours' => (int) env('PRIVACY_EXPORT_EXPIRY_HOURS', 72),
        'generation_stale_minutes' => (int) env('PRIVACY_EXPORT_GENERATION_STALE_MINUTES', 10),
    ],

    // Technical authority windows only; these are not data-retention periods.
    'retention' => [
        'plan_expiry_hours' => (int) env('PRIVACY_DISPOSITION_PLAN_EXPIRY_HOURS', 168),
        'authorization_expiry_hours' => (int) env('PRIVACY_DISPOSITION_AUTHORIZATION_EXPIRY_HOURS', 24),
    ],
];
