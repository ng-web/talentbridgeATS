<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

final class PrivacyIncidentEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['safe_metadata' => 'array', 'occurred_at' => 'datetime', 'actor_security_version' => 'integer'];
    }

    protected static function booted(): void
    {
        self::creating(function (self $event): void {
            $event->uuid ??= (string) Str::uuid();
            $event->created_at ??= now();
        });
        self::updating(fn () => throw new LogicException('Incident timeline is append-only.'));
        self::deleting(fn () => throw new LogicException('Incident timeline is append-only.'));
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(PrivacyIncident::class, 'privacy_incident_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withTrashed();
    }
}
