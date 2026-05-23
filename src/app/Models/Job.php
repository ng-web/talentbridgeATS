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

    public const LISTING_TYPE_SUMMER_WORK_TRAVEL = 'summer_work_travel';
    public const LISTING_TYPE_INTERNSHIP_ABROAD = 'internship_abroad';
    public const LISTING_TYPE_CULTURAL_EXCHANGE = 'cultural_exchange_volunteer';
    public const LISTING_TYPE_AU_PAIR = 'au_pair';
    public const LISTING_TYPE_CAMP_COUNSELOR = 'camp_counselor';
    public const LISTING_TYPE_H2B = 'h2b';

    public const LISTING_TYPES = [
        self::LISTING_TYPE_SUMMER_WORK_TRAVEL,
        self::LISTING_TYPE_INTERNSHIP_ABROAD,
        self::LISTING_TYPE_CULTURAL_EXCHANGE,
        self::LISTING_TYPE_AU_PAIR,
        self::LISTING_TYPE_CAMP_COUNSELOR,
        self::LISTING_TYPE_H2B,
    ];

    public const LISTING_TYPE_LABELS = [
        self::LISTING_TYPE_SUMMER_WORK_TRAVEL => 'Summer Work & Travel',
        self::LISTING_TYPE_INTERNSHIP_ABROAD   => 'Internship Abroad',
        self::LISTING_TYPE_CULTURAL_EXCHANGE   => 'Cultural Exchange & Volunteer',
        self::LISTING_TYPE_AU_PAIR             => 'Au Pair',
        self::LISTING_TYPE_CAMP_COUNSELOR      => 'Camp Counselor',
        self::LISTING_TYPE_H2B                 => 'H-2B',
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