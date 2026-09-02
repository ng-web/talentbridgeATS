<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class PolicyDocument extends Model
{
    public const TYPE_PRIVACY_NOTICE = 'privacy_notice';

    public const TYPE_TERMS_OF_SERVICE = 'terms_of_service';

    public const TYPES = [self::TYPE_PRIVACY_NOTICE, self::TYPE_TERMS_OF_SERVICE];

    protected $fillable = [
        'type',
        'version',
        'title',
        'content_reference',
        'canonical_reference_hash',
        'effective_at',
        'retired_at',
        'is_active',
        'approved_at',
        'approved_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'retired_at' => 'datetime',
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (self $document): void {
            if ($document->isDirty(['type', 'version', 'title', 'content_reference', 'canonical_reference_hash', 'effective_at'])) {
                throw new LogicException('Published policy version content is immutable. Create a new version instead.');
            }
        });

        self::deleting(function (self $document): void {
            if ($document->approved_at !== null || $document->acknowledgements()->exists()) {
                throw new LogicException('Approved or acknowledged policy versions cannot be deleted.');
            }
        });
    }

    public function scopeActive(Builder $query, ?string $type = null): Builder
    {
        return $query
            ->when($type, fn (Builder $builder) => $builder->where('type', $type))
            ->where('is_active', true)
            ->whereNotNull('approved_at')
            ->where('effective_at', '<=', now());
    }

    public static function current(string $type): ?self
    {
        return self::query()->active($type)->orderByDesc('effective_at')->first();
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(PolicyAcknowledgement::class);
    }
}
