<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;

final class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function jobSeeker(): HasOne
    {
        return $this->hasOne(JobSeeker::class);
    }

    public function employer(): HasOne
    {
        return $this->hasOne(Employer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_user_id');
    }

    public function hasActiveEntitlement(string $type): bool
    {
        return $this->entitlements()
            ->where('type', $type)
            ->where('status', Entitlement::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->exists();
    }

    public function primaryRoleLabel(): string
    {
        $role = $this->roles->first()?->name;

        return match ($role) {
            'admin' => 'Admin',
            'employer' => 'Employer',
            'job_seeker' => 'Job Seeker',
            default => $role ? ucfirst(str_replace('_', ' ', $role)) : 'No Role',
        };
    }

    public function activeEntitlementsSummary(): array
    {
        return $this->entitlements
            ->filter(fn (Entitlement $entitlement) => $entitlement->isActive())
            ->pluck('type')
            ->unique()
            ->values()
            ->all();
    }

    public function accessSummaryLabel(): string
    {
        $activeTypes = $this->activeEntitlementsSummary();

        if (empty($activeTypes)) {
            return 'No Active Access';
        }

        if (
            in_array(Entitlement::TYPE_JOB_SEEKER_ACCESS, $activeTypes, true) &&
            in_array(Entitlement::TYPE_EMPLOYER_POSTING_ACCESS, $activeTypes, true)
        ) {
            return 'Seeker + Employer Access Active';
        }

        if (in_array(Entitlement::TYPE_JOB_SEEKER_ACCESS, $activeTypes, true)) {
            return 'Job Seeker Access Active';
        }

        if (in_array(Entitlement::TYPE_EMPLOYER_POSTING_ACCESS, $activeTypes, true)) {
            return 'Employer Access Active';
        }

        return 'Access Active';
    }

    public function accessSummaryTone(): string
    {
        return empty($this->activeEntitlementsSummary()) ? 'warning' : 'success';
    }

    public function latestPaymentRecord(): ?Payment
    {
        return $this->payments
            ->sortByDesc(fn (Payment $payment) => $payment->created_at?->timestamp ?? 0)
            ->first();
    }

    public function latestPaymentLabel(): string
    {
        $payment = $this->latestPaymentRecord();

        if (!$payment) {
            return 'No Payments';
        }

        return Payment::labelFor($payment->status);
    }

    public function latestPaymentTone(): string
    {
        $payment = $this->latestPaymentRecord();

        if (!$payment) {
            return 'neutral';
        }

        return Payment::toneFor($payment->status);
    }
}
