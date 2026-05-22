<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Job extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_REVIEW,
        self::STATUS_PUBLISHED,
        self::STATUS_ARCHIVED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PENDING_REVIEW => 'Pending Review',
        self::STATUS_PUBLISHED => 'Published',
        self::STATUS_ARCHIVED => 'Archived',
    ];

    public const LISTING_TYPE_JOB = 'job';
    public const LISTING_TYPE_WORK_STUDY = 'work_study';

    public const LISTING_TYPES = [
        self::LISTING_TYPE_JOB,
        self::LISTING_TYPE_WORK_STUDY,
    ];

    public const LISTING_TYPE_LABELS = [
        self::LISTING_TYPE_JOB => 'Job',
        self::LISTING_TYPE_WORK_STUDY => 'Work Study',
    ];

    protected $fillable = [
        'employer_id',
        'program_id',
        'title',
        'slug',
        'description',
        'listing_type',
        'category',
        'employment_type',
        'location',
        'country',
        'status',
        'is_approved',
        'remote_flag',
        'application_deadline',
        'duration',
        'eligibility',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'remote_flag' => 'boolean',
        'application_deadline' => 'date',
    ];

    public function employer(): BelongsTo
    {
        return $this->belongsTo(Employer::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public static function labelFor(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function toneFor(string $status): string
    {
        return match ($status) {
            self::STATUS_PUBLISHED => 'success',
            self::STATUS_PENDING_REVIEW => 'warning',
            self::STATUS_ARCHIVED => 'danger',
            default => 'neutral',
        };
    }

    public static function listingTypeLabelFor(string $type): string
    {
        return self::LISTING_TYPE_LABELS[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }
}