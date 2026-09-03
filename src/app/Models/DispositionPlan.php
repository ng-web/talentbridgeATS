<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class DispositionPlan extends Model
{
    public const STATUS_PLANNED = 'planned';

    public const STATUS_AUTHORIZED = 'authorized';

    public const STATUS_EXECUTING = 'executing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REVIEW_REQUIRED = 'review_required';

    public const STATUS_REVOKED = 'revoked';

    public const STATUSES = [self::STATUS_PLANNED, self::STATUS_AUTHORIZED, self::STATUS_EXECUTING, self::STATUS_COMPLETED, self::STATUS_REVIEW_REQUIRED, self::STATUS_REVOKED];

    protected $fillable = [
        'uuid', 'retention_rule_id', 'data_category', 'retention_rule_version', 'retention_rule_hash', 'status', 'planned_at', 'expires_at',
        'planned_by_user_id', 'authorized_by_user_id', 'authorized_at',
        'authorizer_security_version', 'execution_token', 'execution_started_at', 'completed_at',
    ];

    protected $hidden = ['execution_token'];

    protected function casts(): array
    {
        return ['planned_at' => 'datetime', 'expires_at' => 'datetime', 'authorized_at' => 'datetime', 'execution_started_at' => 'datetime', 'completed_at' => 'datetime', 'authorizer_security_version' => 'integer', 'retention_rule_version' => 'integer'];
    }

    protected static function booted(): void
    {
        self::creating(fn (self $plan) => $plan->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(RetentionRule::class, 'retention_rule_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DispositionPlanItem::class);
    }

    public function plannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'planned_by_user_id')->withTrashed();
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id')->withTrashed();
    }
}
