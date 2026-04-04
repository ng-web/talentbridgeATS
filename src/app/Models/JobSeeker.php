<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class JobSeeker extends Model
{
    protected $fillable = [
        'user_id',
        'date_of_birth',
        'location',
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
}