<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

final class RetentionRule extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_RETIRED = 'retired';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_APPROVED, self::STATUS_RETIRED];

    public const UNIT_DAYS = 'days';

    public const UNIT_MONTHS = 'months';

    public const UNIT_YEARS = 'years';

    public const UNITS = [self::UNIT_DAYS, self::UNIT_MONTHS, self::UNIT_YEARS];

    protected $fillable = [
        'uuid', 'data_category', 'version', 'status', 'trigger_type', 'retention_value',
        'retention_unit', 'disposition_method', 'effective_at', 'retired_at',
        'approved_by_user_id', 'approved_at', 'canonical_configuration_hash',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer', 'retention_value' => 'integer', 'effective_at' => 'datetime',
            'retired_at' => 'datetime', 'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::creating(fn (self $rule) => $rule->uuid ??= (string) Str::uuid());
        self::updating(function (self $rule): void {
            if ($rule->getOriginal('status') !== self::STATUS_DRAFT) {
                throw new LogicException('Approved retention rules are immutable; create a replacement version.');
            }
        });
        self::deleting(function (self $rule): void {
            if ($rule->status !== self::STATUS_DRAFT) {
                throw new LogicException('Approved retention-rule history cannot be deleted.');
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
