<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AuditLog extends Model
{
    protected $fillable = [
        'actor_user_id',
        'subject_user_id',
        'action',
        'entity_type',
        'entity_id',
        'correlation_id',
        'outcome',
        'reason_code',
        'occurred_at',
        'meta',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'meta' => 'array',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
