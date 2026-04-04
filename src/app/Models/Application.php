<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Application extends Model
{
    public const STATUS_APPLIED = 'applied';
    public const STATUS_REVIEWING = 'reviewing';
    public const STATUS_INTERVIEW = 'interview';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PLACED = 'placed';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_APPLIED,
        self::STATUS_REVIEWING,
        self::STATUS_INTERVIEW,
        self::STATUS_APPROVED,
        self::STATUS_PLACED,
        self::STATUS_REJECTED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_APPLIED => 'Applied',
        self::STATUS_REVIEWING => 'Reviewing',
        self::STATUS_INTERVIEW => 'Interview',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_PLACED => 'Placed',
        self::STATUS_REJECTED => 'Rejected',
    ];

    protected $fillable = [
        'job_id',
        'job_seeker_id',
        'status',
        'applied_at',
        'submitted_resume_path',
        'submitted_cover_letter_path',
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
            self::STATUS_REVIEWING, self::STATUS_INTERVIEW => 'info',
            self::STATUS_REJECTED => 'danger',
            default => 'neutral',
        };
    }
}