<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
