<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class PrivacyRequestEvent extends Model
{
    protected $fillable = [
        'privacy_request_id',
        'actor_user_id',
        'event',
        'from_state',
        'to_state',
        'reason_code',
        'safe_metadata',
    ];

    protected function casts(): array
    {
        return ['safe_metadata' => 'array'];
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Privacy request history is append-only.'));
        self::deleting(fn () => throw new LogicException('Privacy request history is append-only.'));
    }

    public function privacyRequest(): BelongsTo
    {
        return $this->belongsTo(PrivacyRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
