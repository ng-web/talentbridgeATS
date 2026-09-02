<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class PrivacyRequest extends Model
{
    public const TYPE_ACCESS = 'access';

    public const TYPE_CORRECTION = 'correction';

    public const TYPE_DELETION = 'deletion';

    public const TYPE_RESTRICTION = 'restriction';

    public const TYPE_OBJECTION = 'objection';

    public const TYPE_WITHDRAWAL = 'withdrawal';

    public const TYPES = [
        self::TYPE_ACCESS,
        self::TYPE_CORRECTION,
        self::TYPE_DELETION,
        self::TYPE_RESTRICTION,
        self::TYPE_OBJECTION,
        self::TYPE_WITHDRAWAL,
    ];

    public const STATE_SUBMITTED = 'submitted';

    public const STATE_UNDER_REVIEW = 'under_review';

    public const STATE_IDENTITY_REQUIRED = 'identity_verification_required';

    public const STATE_VERIFIED = 'verified';

    public const STATE_DECISION_REQUIRED = 'decision_required';

    public const STATE_APPROVED = 'approved';

    public const STATE_PARTIALLY_APPROVED = 'partially_approved';

    public const STATE_REFUSED = 'refused';

    public const STATE_FULFILLED = 'fulfilled';

    public const STATE_CLOSED = 'closed';

    public const STATES = [
        self::STATE_SUBMITTED,
        self::STATE_UNDER_REVIEW,
        self::STATE_IDENTITY_REQUIRED,
        self::STATE_VERIFIED,
        self::STATE_DECISION_REQUIRED,
        self::STATE_APPROVED,
        self::STATE_PARTIALLY_APPROVED,
        self::STATE_REFUSED,
        self::STATE_FULFILLED,
        self::STATE_CLOSED,
    ];

    public const IDENTITY_UNVERIFIED = 'unverified';

    public const IDENTITY_REQUIRED = 'required';

    public const IDENTITY_VERIFIED = 'verified';

    public const IDENTITY_UNABLE_TO_VERIFY = 'unable_to_verify';

    public const IDENTITY_STATES = [
        self::IDENTITY_UNVERIFIED,
        self::IDENTITY_REQUIRED,
        self::IDENTITY_VERIFIED,
        self::IDENTITY_UNABLE_TO_VERIFY,
    ];

    protected $fillable = [
        'uuid',
        'subject_user_id',
        'request_type',
        'state',
        'identity_verification_state',
        'identity_verification_method',
        'identity_verified_by',
        'assigned_admin_id',
        'submitted_at',
        'assigned_at',
        'verified_at',
        'decision_at',
        'completed_at',
        'controller_decision_code',
        'closure_reason_code',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'assigned_at' => 'datetime',
            'verified_at' => 'datetime',
            'decision_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $request): void {
            $request->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function identityVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'identity_verified_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PrivacyRequestEvent::class)->orderBy('id');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(DataExport::class);
    }
}
