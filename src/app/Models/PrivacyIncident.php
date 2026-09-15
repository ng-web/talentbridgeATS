<?php

namespace App\Models;

use App\Services\Privacy\IncidentTaxonomy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class PrivacyIncident extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['creation_key'];

    protected function casts(): array
    {
        return [
            'technical_summary' => 'encrypted',
            'detected_at' => 'datetime', 'opened_at' => 'datetime', 'escalated_at' => 'datetime',
            'acknowledged_at' => 'datetime', 'contained_at' => 'datetime', 'recovery_started_at' => 'datetime',
            'recovered_at' => 'datetime', 'resolved_at' => 'datetime', 'closed_at' => 'datetime',
            'reopened_at' => 'datetime', 'review_required_at' => 'datetime', 'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        self::creating(fn (self $incident) => $incident->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id')->withTrashed();
    }

    public function events(): HasMany
    {
        return $this->hasMany(PrivacyIncidentEvent::class)->orderBy('sequence');
    }

    public function scopeVersions(): HasMany
    {
        return $this->hasMany(PrivacyIncidentScopeVersion::class)->orderBy('version');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(PrivacyIncidentDecision::class)->orderBy('version');
    }

    public function holdLinks(): HasMany
    {
        return $this->hasMany(PrivacyIncidentHold::class);
    }

    public function latestDecision(): ?PrivacyIncidentDecision
    {
        return $this->decisions()->orderByDesc('version')->first();
    }

    public function latestScope(): ?PrivacyIncidentScopeVersion
    {
        return $this->scopeVersions()->orderByDesc('version')->first();
    }

    public function mayTransitionTo(string $status): bool
    {
        return in_array($status, IncidentTaxonomy::transitions()[$this->status] ?? [], true);
    }
}
