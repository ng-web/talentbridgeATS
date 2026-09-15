<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

final class PrivacyIncidentScopeVersion extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['data_categories' => 'array', 'system_codes' => 'array', 'potentially_affected_subject_count' => 'integer', 'recorded_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::creating(function (self $scope): void {
            $scope->uuid ??= (string) Str::uuid();
            $scope->created_at ??= now();
        });
        self::updating(fn () => throw new LogicException('Incident scope evidence is versioned and immutable.'));
        self::deleting(fn () => throw new LogicException('Incident scope evidence is versioned and immutable.'));
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(PrivacyIncident::class, 'privacy_incident_id');
    }
}
