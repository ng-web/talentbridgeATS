<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class LegalHold extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_RELEASED = 'released';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_RELEASED];

    public const SCOPE_SUBJECT = 'subject';

    public const SCOPE_CATEGORY = 'category';

    public const SCOPE_RESOURCE = 'resource';

    public const SCOPES = [self::SCOPE_SUBJECT, self::SCOPE_CATEGORY, self::SCOPE_RESOURCE];

    public const HOLD_CODES = ['controller_direction', 'litigation_preservation', 'investigation_preservation', 'regulatory_preservation'];

    protected $fillable = [
        'uuid', 'subject_user_id', 'scope_type', 'data_category', 'resource_id', 'hold_code',
        'status', 'effective_at', 'released_at', 'issued_by_user_id', 'released_by_user_id', 'correlation_id',
    ];

    protected function casts(): array
    {
        return ['resource_id' => 'integer', 'effective_at' => 'datetime', 'released_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::creating(function (self $hold): void {
            $hold->uuid ??= (string) Str::uuid();
            $hold->correlation_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id')->withTrashed();
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id')->withTrashed();
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user_id')->withTrashed();
    }
}
