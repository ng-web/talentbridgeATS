<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

final class PrivacyIncidentDecision extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['rationale' => 'encrypted', 'decided_at' => 'datetime', 'actor_security_version' => 'integer'];
    }

    protected static function booted(): void
    {
        self::creating(function (self $decision): void {
            $decision->uuid ??= (string) Str::uuid();
            $decision->created_at ??= now();
        });
        self::updating(fn () => throw new LogicException('Controller decision evidence is versioned and immutable.'));
        self::deleting(fn () => throw new LogicException('Controller decision evidence is versioned and immutable.'));
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(PrivacyIncident::class, 'privacy_incident_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id')->withTrashed();
    }
}
