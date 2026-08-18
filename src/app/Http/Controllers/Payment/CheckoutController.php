<?php

namespace App\Http\Controllers\Payment;

use App\Actions\Payments\ActivateEntitlementFromPayment;
use App\Http\Controllers\Controller;
use App\Models\Entitlement;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\Payments\PaymentGatewayManager;
use App\Support\Pricing\PlanResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

final class CheckoutController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly ActivateEntitlementFromPayment $activateEntitlement,
        private readonly PlanResolver $plans,
    ) {}

    public function seeker(Plan $plan): RedirectResponse
    {
        abort_if($plan->entitlement_type !== Entitlement::TYPE_JOB_SEEKER_ACCESS, 404);

        return $this->startCheckout($plan);
    }

    public function callback(Request $request): View
    {
        $payload = $request->post();
        $orderId = (string) ($payload['order_id'] ?? '');

        abort_if($orderId === '', 404);

        $payment = Payment::query()
            ->where('order_id', $orderId)
            ->firstOrFail();

        Log::info('WiPay callback received', [
            'gateway' => $payment->gateway,
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
        ]);

        if ($payment->is_test) {
            Log::info('WiPay callback: test payment', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
            ]);
        }

        $gateway = $this->gateways->for($payment->gateway);
        $parsed = $gateway->parseRedirectPayload($payload);

        $normalizedStatus = strtolower(trim((string) $parsed['status']));
        $isSuccessLike = in_array($normalizedStatus, ['success', 'approved', 'completed'], true);
        $isFailureLike = in_array($normalizedStatus, ['failed', 'error', 'timeout', 'cancelled', 'declined'], true);
        abort_unless($isSuccessLike || $isFailureLike, 422);

        $verified = false;

        try {
            $verified = $gateway->verifyRedirect($payment, $payload);
        } catch (Throwable $e) {
            Log::error('Payment redirect verification failed', [
                'gateway' => $payment->gateway,
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'exception_class' => $e::class,
            ]);
        }

        if (! $verified) {
            Log::warning('Payment callback rejected because verification failed', [
                'gateway' => $payment->gateway,
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
            ]);

            abort(422);
        }

        $newStatus = match (true) {
            $isSuccessLike => Payment::STATUS_PAID,
            $isFailureLike => Payment::STATUS_FAILED,
            default => Payment::STATUS_PENDING,
        };

        $payment = DB::transaction(function () use ($payment, $newStatus, $parsed, $normalizedStatus): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->canTransitionTo($newStatus) && $locked->status !== $newStatus) {
                $locked->update([
                    'status' => $newStatus,
                    'external_ref' => $parsed['transaction_id'],
                    'paid_at' => $newStatus === Payment::STATUS_PAID ? ($locked->paid_at ?? now()) : $locked->paid_at,
                    'raw_payload' => array_merge(
                        is_array($locked->raw_payload) ? $locked->raw_payload : [],
                        [
                            'callback_total' => $parsed['total'],
                            'callback_status' => $parsed['status'],
                            'callback_status_normalized' => $normalizedStatus,
                            'callback_verified' => true,
                            'callback_received_at' => now()->toDateTimeString(),
                            'callback_review_reason' => null,
                        ]
                    ),
                ]);
            }

            return $locked->fresh(['plan']);
        });

        if ($payment->status === Payment::STATUS_PAID && $payment->entitlement_activated_at === null) {
            try {
                $this->activateEntitlement->handle($payment);
                $payment = $payment->fresh(['plan']);
            } catch (Throwable $e) {
                Log::error('Paid payment entitlement activation failed', [
                    'gateway' => $payment->gateway,
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'exception_class' => $e::class,
                ]);

                throw $e;
            }
        }

        return view('payments.callback', [
            'payment' => $payment,
            'gatewayStatus' => $parsed['status'],
            'verified' => $verified,
            'message' => $parsed['message'],
        ]);
    }

    private function startCheckout(Plan $plan): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user, 403);

        $payment = Payment::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'gateway' => Payment::GATEWAY_WIPAY,
            'entitlement_type' => $plan->entitlement_type,
            'order_id' => 'KX-'.Str::upper(Str::random(8)).'-'.now()->format('YmdHis'),
            'external_ref' => null,
            'currency' => $plan->currency,
            'amount' => $plan->amount,
            'status' => Payment::STATUS_PENDING,
            'raw_payload' => [
                'created_via' => 'checkout_start',
                'pricing_source' => 'plans_table',
                'plan' => [
                    'id' => $plan->id,
                    'slug' => $plan->slug,
                    'name' => $plan->name,
                    'currency' => $plan->currency,
                    'amount' => $plan->amount,
                ],
            ],
            'paid_at' => null,
        ]);

        try {
            $gateway = $this->gateways->for($payment->gateway);

            $session = $gateway->createCheckoutSession($payment, $user, [
                'response_url' => route('payments.wipay.callback'),
                'origin' => config('services.wipay.origin', 'KairoxExchange'),
                'currency' => $payment->currency,
            ]);

            $payment->update([
                'external_ref' => $session['transaction_id'] ?? null,
                'raw_payload' => array_merge(
                    is_array($payment->raw_payload) ? $payment->raw_payload : [],
                    ['bootstrap' => [
                        'status' => 'session_created',
                        'http_status' => $session['diagnostics']['http_status'] ?? null,
                        'created_at' => now()->toDateTimeString(),
                    ]]
                ),
            ]);

            return redirect()->away($session['url']);
        } catch (Throwable $e) {
            $payment->update([
                'status' => Payment::STATUS_FAILED,
                'raw_payload' => array_merge(
                    is_array($payment->raw_payload) ? $payment->raw_payload : [],
                    [
                        'checkout_start_error' => $e::class,
                        'checkout_start_failed_at' => now()->toDateTimeString(),
                    ]
                ),
            ]);

            Log::error('Payment checkout start failed', [
                'gateway' => $payment->gateway,
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'exception_class' => $e::class,
            ]);

            return redirect()
                ->route('pricing')
                ->withErrors([
                    'payment' => 'Unable to start payment checkout right now. Please try again shortly.',
                ]);
        }
    }
}
