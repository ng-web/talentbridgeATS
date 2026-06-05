<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentAssistanceRequest extends Model
{
    public const STATUS_NEW          = 'new';
    public const STATUS_CONTACTED    = 'contacted';
    public const STATUS_PAYMENT_SENT = 'payment_sent';
    public const STATUS_PAID         = 'paid';
    public const STATUS_CLOSED       = 'closed';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_CONTACTED,
        self::STATUS_PAYMENT_SENT,
        self::STATUS_PAID,
        self::STATUS_CLOSED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_NEW          => 'New',
        self::STATUS_CONTACTED    => 'Contacted',
        self::STATUS_PAYMENT_SENT => 'Payment Sent',
        self::STATUS_PAID         => 'Paid',
        self::STATUS_CLOSED       => 'Closed',
    ];

    protected $fillable = [
        'user_id',
        'plan_id',
        'full_name',
        'email',
        'phone',
        'whatsapp',
        'program_name',
        'amount',
        'currency',
        'message',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public static function labelFor(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function toneFor(string $status): string
    {
        return match ($status) {
            self::STATUS_NEW          => 'warning',
            self::STATUS_CONTACTED    => 'info',
            self::STATUS_PAYMENT_SENT => 'brand',
            self::STATUS_PAID         => 'success',
            self::STATUS_CLOSED       => 'neutral',
            default                   => 'neutral',
        };
    }
}
