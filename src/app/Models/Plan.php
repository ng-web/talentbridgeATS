<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'entitlement_type',
        'amount',
        'currency',
        'duration_days',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];
}