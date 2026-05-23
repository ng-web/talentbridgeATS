<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class EmploymentType extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
