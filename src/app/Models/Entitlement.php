<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Entitlement extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_EXPIRED,
        self::STATUS_REVOKED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_INACTIVE => 'Inactive',
        self::STATUS_EXPIRED => 'Expired',
        self::STATUS_REVOKED => 'Revoked',
    ];

    public const TYPE_JOB_SEEKER_ACCESS = 'job_seeker_access';
    public const TYPE_EMPLOYER_POSTING_ACCESS = 'employer_posting_access';

    public const TYPES = [
        self::TYPE_JOB_SEEKER_ACCESS,
        self::TYPE_EMPLOYER_POSTING_ACCESS,
    ];

    public const TYPE_LABELS = [
        self::TYPE_JOB_SEEKER_ACCESS => 'Job Seeker Access',
        self::TYPE_EMPLOYER_POSTING_ACCESS => 'Employer Posting Access',
    ];

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'starts_at',
        'expires_at',
        'source',
        'notes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function labelFor(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function toneFor(string $status): string
    {
        return match ($status) {
            self::STATUS_ACTIVE => 'success',
            self::STATUS_EXPIRED => 'warning', 
            self::STATUS_REVOKED => 'danger',
            default => 'neutral',
        };
    }

    public static function typeLabelFor(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}