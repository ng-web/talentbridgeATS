<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class DataExport extends Model
{
    public const STATUS_AUTHORIZED = 'authorized';

    public const STATUS_GENERATING = 'generating';

    public const STATUS_READY = 'ready';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_PURGING = 'purging';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PURGED = 'purged';

    public const STATUSES = [
        self::STATUS_AUTHORIZED,
        self::STATUS_GENERATING,
        self::STATUS_READY,
        self::STATUS_EXPIRED,
        self::STATUS_PURGING,
        self::STATUS_FAILED,
        self::STATUS_PURGED,
    ];

    public const ALLOWED_SCOPES = [
        'account',
        'profile',
        'applications',
        'payments',
        'entitlements',
        'policy_evidence',
        'privacy_requests',
    ];

    protected $fillable = [
        'uuid',
        'privacy_request_id',
        'subject_user_id',
        'requested_by_user_id',
        'approved_by_user_id',
        'generation_initiated_by_user_id',
        'status',
        'scope_manifest',
        'authorized_scope_hash',
        'artifact_scope_hash',
        'private_path',
        'sha256',
        'generation_token',
        'generation_attempt',
        'generation_started_at',
        'authorized_at',
        'generated_at',
        'expires_at',
        'downloaded_at',
        'purged_at',
        'failure_code',
    ];

    protected function casts(): array
    {
        return [
            'scope_manifest' => 'array',
            'generation_started_at' => 'datetime',
            'authorized_at' => 'datetime',
            'generation_attempt' => 'integer',
            'generated_at' => 'datetime',
            'expires_at' => 'datetime',
            'downloaded_at' => 'datetime',
            'purged_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $export): void {
            $export->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function privacyRequest(): BelongsTo
    {
        return $this->belongsTo(PrivacyRequest::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function generationInitiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generation_initiated_by_user_id');
    }
}
