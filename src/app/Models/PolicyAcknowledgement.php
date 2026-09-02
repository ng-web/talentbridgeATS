<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PolicyAcknowledgement extends Model
{
    public const TYPE_NOTICE_ACKNOWLEDGED = 'notice_acknowledged';

    public const TYPE_TERMS_ACCEPTED = 'terms_accepted';

    public const TYPES = [self::TYPE_NOTICE_ACKNOWLEDGED, self::TYPE_TERMS_ACCEPTED];

    protected $fillable = [
        'user_id',
        'policy_document_id',
        'acknowledgement_type',
        'source_context',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return ['acknowledged_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function policyDocument(): BelongsTo
    {
        return $this->belongsTo(PolicyDocument::class);
    }
}
