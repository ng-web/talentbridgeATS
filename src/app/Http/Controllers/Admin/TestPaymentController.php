<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entitlement;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class TestPaymentController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
    ) {}

    public function store(): RedirectResponse
    {
        abort_unless(config('services.wipay.test_enabled'), 404);

        $user = Auth::user();
        $amount = (float) config('services.wipay.test_amount', 115);
        $currency = (string) config('services.wipay.test_currency', 'JMD');

        $payment = Payment::create([
            'user_id' => $user->id,
            'plan_id' => null,
            'gateway' => Payment::GATEWAY_WIPAY,
            'entitlement_type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'order_id' => 'KXTEST-'.Str::upper(Str::random(6)).'-'.now()->format('YmdHis'),
            'external_ref' => null,
            'currency' => $currency,
            'amount' => $amount,
            'status' => Payment::STATUS_PENDING,
            'paid_at' => null,
            'is_test' => true,
            'raw_payload' => [
                'created_via' => 'admin_test_payment',
                'test_amount' => $amount,
                'test_currency' => $currency,
                'initiated_by_user_id' => $user->id,
            ],
        ]);

        try {
            $gateway = $this->gateways->for(Payment::GATEWAY_WIPAY);

            $session = $gateway->createCheckoutSession($payment, $user, [
                'response_url' => route('payments.wipay.callback'),
                'currency' => $currency,
            ]);

            $payment->update([
                'external_ref' => $session['transaction_id'] ?? null,
                'raw_payload' => array_merge($payment->raw_payload ?? [], [
                    'bootstrap' => [
                        'status' => 'session_created',
                        'http_status' => $session['diagnostics']['http_status'] ?? null,
                        'created_at' => now()->toDateTimeString(),
                    ],
                ]),
            ]);

            Log::info('Admin test payment initiated', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'amount' => $amount,
                'currency' => $currency,
                'user_id' => $user->id,
            ]);

            return redirect()->away($session['url']);
        } catch (Throwable $e) {
            $payment->update([
                'status' => Payment::STATUS_FAILED,
                'raw_payload' => array_merge($payment->raw_payload ?? [], [
                    'checkout_start_error' => $e::class,
                    'checkout_start_failed_at' => now()->toDateTimeString(),
                ]),
            ]);

            Log::error('Admin test payment failed to start', [
                'payment_id' => $payment->id,
                'exception_class' => $e::class,
            ]);

            return redirect()
                ->route('admin.dashboard')
                ->withErrors(['payment' => 'WiPay test checkout failed. Review the safe application diagnostics.']);
        }
    }
}
