<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

final class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    public const ACCESS_ACTIVE = 'active';

    public const ACCESS_INACTIVE = 'inactive';

    public const ACCESS_EXPIRED = 'expired';

    public const ACCESS_REVOKED = 'revoked';

    public const ACCESS_NONE = 'no_access';

    protected $fillable = [
        'name',
        'email',
        'password',
        'must_change_password',
        'security_version',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'security_version' => 'integer',
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

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }

    public function currentEntitlement(): HasOne
    {
        return $this->hasOne(Entitlement::class)
            ->where('status', Entitlement::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latestOfMany();
    }

    public function latestEntitlement(): HasOne
    {
        return $this->hasOne(Entitlement::class)->latestOfMany();
    }

    public function scopeWhereAccessSummary(Builder $query, string $state): Builder
    {
        return match ($state) {
            self::ACCESS_ACTIVE => $query->whereHas('currentEntitlement'),
            self::ACCESS_INACTIVE => $query
                ->whereDoesntHave('currentEntitlement')
                ->whereHas('latestEntitlement', function (Builder $entitlementQuery) {
                    $entitlementQuery
                        ->where('status', Entitlement::STATUS_INACTIVE)
                        ->orWhere(function (Builder $futureQuery) {
                            $futureQuery
                                ->where('status', Entitlement::STATUS_ACTIVE)
                                ->where('starts_at', '>', now())
                                ->where(function (Builder $expiryQuery) {
                                    $expiryQuery->whereNull('expires_at')->orWhere('expires_at', '>', now());
                                });
                        });
                }),
            self::ACCESS_EXPIRED => $query
                ->whereDoesntHave('currentEntitlement')
                ->whereHas('latestEntitlement', function (Builder $entitlementQuery) {
                    $entitlementQuery
                        ->where('status', Entitlement::STATUS_EXPIRED)
                        ->orWhere(function (Builder $expiredActiveQuery) {
                            $expiredActiveQuery
                                ->where('status', Entitlement::STATUS_ACTIVE)
                                ->whereNotNull('expires_at')
                                ->where('expires_at', '<=', now());
                        });
                }),
            self::ACCESS_REVOKED => $query
                ->whereDoesntHave('currentEntitlement')
                ->whereHas('latestEntitlement', fn (Builder $entitlementQuery) => $entitlementQuery->where('status', Entitlement::STATUS_REVOKED)),
            self::ACCESS_NONE => $query->whereDoesntHave('entitlements'),
            default => $query,
        };
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

    public function accessSummaryState(): string
    {
        $now = now();

        if ($this->currentEntitlement) {
            return self::ACCESS_ACTIVE;
        }

        $latest = $this->latestEntitlement;

        if (! $latest) {
            return self::ACCESS_NONE;
        }

        if ($latest->status === Entitlement::STATUS_ACTIVE && $latest->expires_at?->lessThanOrEqualTo($now)) {
            return self::ACCESS_EXPIRED;
        }

        if ($latest->status === Entitlement::STATUS_ACTIVE && $latest->starts_at?->greaterThan($now)) {
            return self::ACCESS_INACTIVE;
        }

        return $latest->status;
    }

    public function accessSummaryLabel(): string
    {
        $state = $this->accessSummaryState();

        return match ($state) {
            self::ACCESS_NONE => 'No Access',
            default => Entitlement::labelFor($state),
        };
    }

    public function accessSummaryTone(): string
    {
        return match ($this->accessSummaryState()) {
            self::ACCESS_ACTIVE => 'success',
            self::ACCESS_EXPIRED => 'warning',
            self::ACCESS_REVOKED => 'danger',
            default => 'neutral',
        };
    }

    public function latestPaymentRecord(): ?Payment
    {
        return $this->latestPayment;
    }

    public function latestPaymentLabel(): string
    {
        $payment = $this->latestPaymentRecord();

        if (! $payment) {
            return 'No Payment';
        }

        return Payment::labelFor($payment->status);
    }

    public function latestPaymentTone(): string
    {
        $payment = $this->latestPaymentRecord();

        if (! $payment) {
            return 'neutral';
        }

        return Payment::toneFor($payment->status);
    }
}
