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

        if ($key === 'access_granted' && ! is_bool($value)) {
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
