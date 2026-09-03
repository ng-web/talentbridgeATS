<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class DispositionPlanItem extends Model
{
    public const STATUS_PLANNED = 'planned';

    public const STATUS_DISPOSED = 'disposed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    public const CLEANUP_PENDING = 'pending';

    public const CLEANUP_COMPLETED = 'completed';

    public const CLEANUP_FAILED = 'failed';

    protected $fillable = [
        'uuid', 'disposition_plan_id', 'subject_user_id', 'resource_id', 'status', 'trigger_type',
        'triggered_at', 'eligible_at', 'disposition_method', 'artifact_revision', 'artifact_fingerprint',
        'eligibility_snapshot_hash', 'outcome_code', 'file_cleanup_status', 'cleanup_path', 'disposed_at',
    ];

    protected $hidden = ['artifact_revision', 'artifact_fingerprint', 'cleanup_path'];

    protected function casts(): array
    {
        return [
            'resource_id' => 'integer',
            'triggered_at' => 'datetime',
            'eligible_at' => 'datetime',
            'disposed_at' => 'datetime',
            'cleanup_path' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        self::creating(fn (self $item) => $item->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(DispositionPlan::class, 'disposition_plan_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id')->withTrashed();
    }
}
