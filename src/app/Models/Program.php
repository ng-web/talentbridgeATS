<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'age_range',
        'description',
        'benefits',
        'typical_roles',
        'fields_available',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'benefits' => 'array',
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function jobSeekers(): HasMany
    {
        return $this->hasMany(JobSeeker::class);
    }
}
