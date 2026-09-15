<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

final class PrivacyIncidentHold extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::creating(function (self $link): void {
            $link->uuid ??= (string) Str::uuid();
            $link->created_at ??= now();
        });
        self::updating(fn () => throw new LogicException('Incident hold links are immutable.'));
        self::deleting(fn () => throw new LogicException('Incident hold links are immutable.'));
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(PrivacyIncident::class, 'privacy_incident_id');
    }

    public function legalHold(): BelongsTo
    {
        return $this->belongsTo(LegalHold::class);
    }
}
