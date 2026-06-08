<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Payment extends Model
{
    public const GATEWAY_WIPAY = 'wipay';
    public const GATEWAY_STRIPE = 'stripe';
    public const GATEWAY_PAYPAL = 'paypal';

    public const GATEWAYS = [
        self::GATEWAY_WIPAY,
        self::GATEWAY_STRIPE,
        self::GATEWAY_PAYPAL,
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_REVIEW_REQUIRED = 'review_required';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_FAILED,
        self::STATUS_REFUNDED,
        self::STATUS_REVIEW_REQUIRED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_PAID => 'Paid',
        self::STATUS_FAILED => 'Failed',
        self::STATUS_REFUNDED => 'Refunded',
        self::STATUS_REVIEW_REQUIRED => 'Review Required',
    ];

    protected $fillable = [
        'user_id',
        'plan_id',
        'gateway',
        'entitlement_type',
        'order_id',
        'external_ref',
        'currency',
        'amount',
        'status',
        'raw_payload',
        'paid_at',
        'entitlement_activated_at',
        'is_test',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'raw_payload' => 'array',
        'paid_at' => 'datetime',
        'entitlement_activated_at' => 'datetime',
        'is_test' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function labelFor(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function toneFor(string $status): string
    {
        return match ($status) {
            self::STATUS_PAID => 'success',
            self::STATUS_FAILED, self::STATUS_REFUNDED => 'danger',
            self::STATUS_REVIEW_REQUIRED => 'warning',
            default => 'neutral',
        };
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function requiresReview(): bool
    {
        return $this->status === self::STATUS_REVIEW_REQUIRED;
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}