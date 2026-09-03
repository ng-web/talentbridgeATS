<?php

namespace App\Services\Privacy;

use App\Models\JobSeekerDocument;

final class RetentionDataCategories
{
    public const APPLICANT_ACCOUNT = 'applicant_account';

    public const APPLICANT_PROFILE = 'applicant_profile';

    public const APPLICANT_DOCUMENT = 'applicant_document';

    public const APPLICATION = 'application';

    public const APPLICATION_FILE = 'application_file';

    public const PAYMENT = 'payment';

    public const ENTITLEMENT = 'entitlement';

    public const ASSISTANCE_REQUEST = 'assistance_request';

    public const NOTIFICATION = 'notification';

    public const PRIVACY_REQUEST = 'privacy_request';

    public const PRIVACY_EXPORT = 'privacy_export';

    public const POLICY_ACKNOWLEDGEMENT = 'policy_acknowledgement';

    public const SENSITIVE_PROCESSING_EVIDENCE = 'sensitive_processing_evidence';

    public const SECURITY_AUDIT = 'security_audit';

    public const TRIGGER_RECORD_CREATED = 'record_created';

    public const METHOD_DELETE = 'delete';

    public const METHOD_DETACH_AND_DELETE_FILE = 'detach_and_delete_file';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::APPLICANT_ACCOUNT, self::APPLICANT_PROFILE, self::APPLICANT_DOCUMENT,
            self::APPLICATION, self::APPLICATION_FILE, self::PAYMENT, self::ENTITLEMENT,
            self::ASSISTANCE_REQUEST, self::NOTIFICATION, self::PRIVACY_REQUEST,
            self::PRIVACY_EXPORT, self::POLICY_ACKNOWLEDGEMENT,
            self::SENSITIVE_PROCESSING_EVIDENCE, self::SECURITY_AUDIT,
        ];
    }

    /** @return list<string> */
    public static function supported(): array
    {
        return [self::APPLICANT_DOCUMENT];
    }

    /** @return list<string> */
    public static function triggersFor(string $category): array
    {
        return $category === self::APPLICANT_DOCUMENT ? [self::TRIGGER_RECORD_CREATED] : [];
    }

    /** @return list<string> */
    public static function methodsFor(string $category): array
    {
        return $category === self::APPLICANT_DOCUMENT ? [self::METHOD_DETACH_AND_DELETE_FILE] : [];
    }

    public static function modelFor(string $category): ?string
    {
        return $category === self::APPLICANT_DOCUMENT ? JobSeekerDocument::class : null;
    }
}
