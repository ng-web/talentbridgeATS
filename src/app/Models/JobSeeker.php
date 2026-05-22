<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class JobSeeker extends Model
{
    protected $fillable = [
        'user_id',
        'date_of_birth',
        'location',
        'phone',
        'education',
        'experience_summary',
        'skills',
        'profile_completeness',
        'work_study_interest_flag',
        'resume_path',
        'cover_letter_path',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'work_study_interest_flag' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(JobSeekerDocument::class);
    }

    public function documentsByType(): Collection
    {
        return $this->documents->keyBy('document_type');
    }
}