<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SensitiveProcessingEvidence extends Model
{
    protected $table = 'sensitive_processing_evidence';

    public const CATEGORIES = [
        JobSeekerDocument::TYPE_PASSPORT,
        JobSeekerDocument::TYPE_DRIVERS_LICENSE,
        JobSeekerDocument::TYPE_POLICE_RECORD,
        JobSeekerDocument::TYPE_MEDICAL_RECORD,
    ];

    protected $fillable = [
        'user_id',
        'category',
        'purpose_code',
        'policy_document_id',
        'evidence_type',
        'recorded_at',
        'withdrawn_at',
        'source_context',
        'controller_reference',
    ];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime', 'withdrawn_at' => 'datetime'];
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
