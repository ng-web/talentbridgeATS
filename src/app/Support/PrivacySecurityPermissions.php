<?php

namespace App\Support;

final class PrivacySecurityPermissions
{
    public const PRIVACY_REQUESTS_VIEW = 'privacy.requests.view';

    public const PRIVACY_REQUESTS_MANAGE = 'privacy.requests.manage';

    public const PRIVACY_REQUESTS_DECIDE = 'privacy.requests.decide';

    public const PRIVACY_EXPORTS_GENERATE = 'privacy.exports.generate';

    public const PRIVACY_EXPORTS_AUTHORIZE = 'privacy.exports.authorize';

    public const PRIVACY_EXPORTS_DOWNLOAD = 'privacy.exports.download';

    public const PRIVACY_EXPORTS_MANAGE = 'privacy.exports.manage';

    public const PRIVACY_POLICY_VIEW = 'privacy.policy.view';

    public const PRIVACY_POLICY_MANAGE = 'privacy.policy.manage';

    public const PRIVACY_EVIDENCE_VIEW = 'privacy.evidence.view';

    public const PRIVACY_EVIDENCE_MANAGE = 'privacy.evidence.manage';

    public const RETENTION_VIEW = 'privacy.retention.view';

    public const RETENTION_MANAGE = 'privacy.retention.manage';

    public const RETENTION_APPROVE = 'privacy.retention.approve';

    public const LEGAL_HOLDS_VIEW = 'privacy.holds.view';

    public const LEGAL_HOLDS_MANAGE = 'privacy.holds.manage';

    public const DISPOSITION_PLAN = 'privacy.disposition.plan';

    public const DISPOSITION_AUTHORIZE = 'privacy.disposition.authorize';

    public const DISPOSITION_EXECUTE = 'privacy.disposition.execute';

    /** @deprecated Pass 1 compatibility alias. */
    public const RETENTION_EXECUTE = self::DISPOSITION_EXECUTE;

    public const DISPOSITION_RECONCILE = 'privacy.disposition.reconcile';

    public const INCIDENTS_MANAGE = 'privacy.incidents.manage';

    public const ADMIN_SECURITY_SELF = 'admin.security.self';

    public const ADMIN_SECURITY_MANAGE = 'admin.security.manage';

    public const ACCESS_REVIEWS_MANAGE = 'admin.access-reviews.manage';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::PRIVACY_REQUESTS_VIEW,
            self::PRIVACY_REQUESTS_MANAGE,
            self::PRIVACY_REQUESTS_DECIDE,
            self::PRIVACY_EXPORTS_GENERATE,
            self::PRIVACY_EXPORTS_AUTHORIZE,
            self::PRIVACY_EXPORTS_DOWNLOAD,
            self::PRIVACY_EXPORTS_MANAGE,
            self::PRIVACY_POLICY_VIEW,
            self::PRIVACY_POLICY_MANAGE,
            self::PRIVACY_EVIDENCE_VIEW,
            self::PRIVACY_EVIDENCE_MANAGE,
            self::RETENTION_VIEW,
            self::RETENTION_MANAGE,
            self::RETENTION_APPROVE,
            self::LEGAL_HOLDS_VIEW,
            self::LEGAL_HOLDS_MANAGE,
            self::DISPOSITION_PLAN,
            self::DISPOSITION_AUTHORIZE,
            self::DISPOSITION_EXECUTE,
            self::DISPOSITION_RECONCILE,
            self::INCIDENTS_MANAGE,
            self::ADMIN_SECURITY_SELF,
            self::ADMIN_SECURITY_MANAGE,
            self::ACCESS_REVIEWS_MANAGE,
        ];
    }

    /** @return list<string> */
    public static function controllerManager(): array
    {
        return [
            self::PRIVACY_REQUESTS_VIEW,
            self::PRIVACY_REQUESTS_MANAGE,
            self::PRIVACY_REQUESTS_DECIDE,
            self::PRIVACY_EXPORTS_GENERATE,
            self::PRIVACY_EXPORTS_AUTHORIZE,
            self::PRIVACY_EXPORTS_DOWNLOAD,
            self::PRIVACY_POLICY_VIEW,
            self::PRIVACY_POLICY_MANAGE,
            self::PRIVACY_EVIDENCE_VIEW,
            self::PRIVACY_EVIDENCE_MANAGE,
        ];
    }

    /** @return list<string> */
    public static function introducedInPass2(): array
    {
        return [
            self::PRIVACY_REQUESTS_VIEW,
            self::PRIVACY_REQUESTS_DECIDE,
            self::PRIVACY_EXPORTS_GENERATE,
            self::PRIVACY_EXPORTS_AUTHORIZE,
            self::PRIVACY_EXPORTS_DOWNLOAD,
            self::PRIVACY_POLICY_VIEW,
            self::PRIVACY_POLICY_MANAGE,
            self::PRIVACY_EVIDENCE_VIEW,
            self::PRIVACY_EVIDENCE_MANAGE,
        ];
    }

    /** @return list<string> */
    public static function introducedInPass3(): array
    {
        return [
            self::RETENTION_VIEW, self::RETENTION_MANAGE, self::RETENTION_APPROVE,
            self::LEGAL_HOLDS_VIEW, self::LEGAL_HOLDS_MANAGE,
            self::DISPOSITION_PLAN, self::DISPOSITION_AUTHORIZE,
            self::DISPOSITION_EXECUTE, self::DISPOSITION_RECONCILE,
        ];
    }

    /** @return list<string> */
    public static function retentionManager(): array
    {
        return self::introducedInPass3();
    }
}
