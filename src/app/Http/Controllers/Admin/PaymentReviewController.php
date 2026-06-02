<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Payments\ActivateEntitlementFromPayment;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

final class PaymentReviewController extends Controller
{
    public function __construct(
        private readonly ActivateEntitlementFromPayment $activateEntitlement,
    ) {
    }

    public function confirm(Payment $payment): RedirectResponse
    {
        $previousStatus = $payment->status;

        $payment->update([
            'status' => Payment::STATUS_PAID,
            'paid_at' => $payment->paid_at ?? now(),
            'raw_payload' => array_merge(
                is_array($payment->raw_payload) ? $payment->raw_payload : [],
                [
                    'manual_review' => [
                        'confirmed_at' => now()->toDateTimeString(),
                        'confirmed_by' => auth()->id(),
                        'previous_status' => $previousStatus,
                        'reason' => 'Manually confirmed after payment review.',
                    ],
                ]
            ),
        ]);

        $payment = $payment->fresh();

        if ($previousStatus !== Payment::STATUS_PAID) {
            $this->activateEntitlement->handle($payment);
        }

        Log::warning('Payment manually confirmed', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'external_ref' => $payment->external_ref,
            'confirmed_by' => auth()->id(),
        ]);

        return back()->with('success', 'Payment manually confirmed and entitlement activated.');
    }

    public function activate(Payment $payment): RedirectResponse
    {
        abort_unless($payment->status === Payment::STATUS_PAID, 422);

        $this->activateEntitlement->handle($payment, 'admin_manual_activate');

        Log::info('Payment entitlement manually activated', [
            'payment_id'   => $payment->id,
            'order_id'     => $payment->order_id,
            'activated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Entitlement activated for ' . ($payment->user?->name ?? 'user') . '.');
    }
}