<?php

namespace App\Actions\Payments;

use App\Models\Entitlement;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ActivateEntitlementFromPayment
{
    public function handle(Payment $payment, string $source = 'payment_gateway'): void
    {
        DB::transaction(function () use ($payment, $source): void {
            $locked = Payment::query()->lockForUpdate()->find($payment->id);

            if ($locked === null || $locked->entitlement_activated_at !== null) {
                Log::info('Skipped entitlement activation because payment was already activated', [
                    'payment_id' => $payment->id,
                    'user_id' => $payment->user_id,
                    'entitlement_type' => $payment->entitlement_type,
                    'source' => $source,
                ]);

                return;
            }

            $durationDays = $locked->plan?->duration_days;
            $expiresAt = $durationDays ? now()->addDays($durationDays) : now()->addMonth();

            Entitlement::updateOrCreate(
                [
                    'user_id' => $locked->user_id,
                    'type' => $locked->entitlement_type,
                ],
                [
                    'status' => Entitlement::STATUS_ACTIVE,
                    'starts_at' => now(),
                    'expires_at' => $expiresAt,
                    'source' => $source,
                    'notes' => 'Activated from ' . $locked->gateway . ' payment #' . $locked->id,
                ]
            );

            $locked->update([
                'entitlement_activated_at' => now(),
            ]);

            Log::info('Entitlement activated from payment', [
                'payment_id' => $locked->id,
                'user_id' => $locked->user_id,
                'entitlement_type' => $locked->entitlement_type,
                'gateway' => $locked->gateway,
                'source' => $source,
                'expires_at' => $expiresAt->toDateTimeString(),
            ]);
        });
    }
}