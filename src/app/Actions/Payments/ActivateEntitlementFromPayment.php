<?php

namespace App\Actions\Payments;

use App\Models\Entitlement;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

final class ActivateEntitlementFromPayment
{
    public function handle(Payment $payment, string $source = 'payment_gateway'): void
    {
        if ($payment->entitlement_activated_at !== null) {
            Log::info('Skipped entitlement activation because payment was already activated', [
                'payment_id' => $payment->id,
                'user_id' => $payment->user_id,
                'entitlement_type' => $payment->entitlement_type,
                'source' => $source,
            ]);

            return;
        }

        Entitlement::updateOrCreate(
            [
                'user_id' => $payment->user_id,
                'type' => $payment->entitlement_type,
            ],
            [
                'status' => Entitlement::STATUS_ACTIVE,
                'starts_at' => now(),
                'expires_at' => now()->addMonth(),
                'source' => $source,
                'notes' => 'Activated from ' . $payment->gateway . ' payment #' . $payment->id,
            ]
        );

        $payment->update([
            'entitlement_activated_at' => now(),
        ]);

        Log::info('Entitlement activated from payment', [
            'payment_id' => $payment->id,
            'user_id' => $payment->user_id,
            'entitlement_type' => $payment->entitlement_type,
            'gateway' => $payment->gateway,
            'source' => $source,
            'activated_at' => $payment->fresh()->entitlement_activated_at?->toDateTimeString(),
        ]);
    }
}