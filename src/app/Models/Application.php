<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Application extends Model
{
    public const STATUS_APPLIED = 'applied';
    public const STATUS_REVIEWING = 'reviewing';
    public const STATUS_SHORTLISTED = 'shortlisted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PLACED = 'placed';
    public const STATUS_NOT_SELECTED = 'not_selected';
    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUSES = [
        self::STATUS_APPLIED,
        self::STATUS_REVIEWING,
        self::STATUS_SHORTLISTED,
        self::STATUS_APPROVED,
        self::STATUS_PLACED,
        self::STATUS_NOT_SELECTED,
        self::STATUS_WITHDRAWN,
    ];

    // Statuses visible in employer pipeline (excludes withdrawn)
    public const EMPLOYER_STATUSES = [
        self::STATUS_APPLIED,
        self::STATUS_REVIEWING,
        self::STATUS_SHORTLISTED,
        self::STATUS_APPROVED,
        self::STATUS_PLACED,
        self::STATUS_NOT_SELECTED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_APPLIED => 'Applied',
        self::STATUS_REVIEWING => 'Reviewing',
        self::STATUS_SHORTLISTED => 'Shortlisted',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_PLACED => 'Placed',
        self::STATUS_NOT_SELECTED => 'Not Selected',
        self::STATUS_WITHDRAWN => 'Withdrawn',
    ];

    protected $fillable = [
        'job_id',
        'job_seeker_id',
        'status',
        'applied_at',
        'submitted_resume_path',
        'submitted_cover_letter_path',
        'notes',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function jobSeeker(): BelongsTo
    {
        return $this->belongsTo(JobSeeker::class);
    }

    public static function labelFor(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function toneFor(string $status): string
    {
        return match ($status) {
            self::STATUS_APPROVED, self::STATUS_PLACED => 'success',
            self::STATUS_REVIEWING, self::STATUS_SHORTLISTED => 'info',
            self::STATUS_NOT_SELECTED => 'danger',
            self::STATUS_WITHDRAWN => 'neutral',
            default => 'neutral',
        };
    }
}