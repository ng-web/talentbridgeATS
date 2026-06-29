<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Employer extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'company_description',
        'industry',
        'website',
        'logo_path',
        'contact_person',
        'contact_email',
        'notification_email',
        'phone_company',
        'phone_ext',
        'phone_direct',
        'billing_status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function notificationEmail(): ?string
    {
        return $this->notification_email ?: $this->user?->email;
    }
}
