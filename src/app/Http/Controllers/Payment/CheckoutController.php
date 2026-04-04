<?php

namespace App\Http\Controllers\Payment;

use App\Actions\Payments\ActivateEntitlementFromPayment;
use App\Http\Controllers\Controller;
use App\Models\Entitlement;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayManager;
use App\Support\Pricing\PlanResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
    ) {
    }

    public function seeker(): RedirectResponse
    {
        return $this->startCheckout(Entitlement::TYPE_JOB_SEEKER_ACCESS);
    }

    public function employer(): RedirectResponse
    {
        return $this->startCheckout(Entitlement::TYPE_EMPLOYER_POSTING_ACCESS);
    }

    public function callback(Request $request): View
    {
        $orderId = (string) $request->query('order_id', '');

        abort_if($orderId === '', 404);

        $payment = Payment::query()
            ->where('order_id', $orderId)
            ->firstOrFail();

        Log::info('WiPay callback raw query', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'query' => $request->query(),
        ]);

        $gateway = $this->gateways->for($payment->gateway);
        $parsed = $gateway->parseRedirectPayload($request->query());

        $normalizedStatus = strtolower(trim((string) $parsed['status']));
        $isSuccessLike = in_array($normalizedStatus, ['success', 'approved', 'completed'], true);
        $isFailureLike = in_array($normalizedStatus, ['failed', 'error', 'timeout', 'cancelled', 'declined'], true);

        $verified = false;

        if ($isSuccessLike) {
            try {
                $verified = $gateway->verifySuccessfulRedirect($payment, $request->query());
            } catch (Throwable $e) {
                Log::error('Payment redirect verification failed', [
                    'gateway' => $payment->gateway,
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $newStatus = match (true) {
            $isSuccessLike && $verified => Payment::STATUS_PAID,
            $isSuccessLike && !$verified => Payment::STATUS_REVIEW_REQUIRED,
            $isFailureLike => Payment::STATUS_FAILED,
            default => Payment::STATUS_PENDING,
        };

        $previousStatus = $payment->status;

        $payment->update([
            'status' => $newStatus,
            'external_ref' => $parsed['transaction_id'] !== '' ? $parsed['transaction_id'] : $payment->external_ref,
            'paid_at' => $newStatus === Payment::STATUS_PAID ? ($payment->paid_at ?? now()) : $payment->paid_at,
            'raw_payload' => array_merge(
                is_array($payment->raw_payload) ? $payment->raw_payload : [],
                [
                    'callback' => $parsed['raw'],
                    'callback_total' => $parsed['total'],
                    'callback_status' => $parsed['status'],
                    'callback_status_normalized' => $normalizedStatus,
                    'callback_verified' => $verified,
                    'callback_received_at' => now()->toDateTimeString(),
                    'callback_review_reason' => $isSuccessLike && !$verified
                        ? 'Success-like callback received but verification did not match.'
                        : null,
                ]
            ),
        ]);

        $payment = $payment->fresh(['plan']);

        if (
            $payment->status === Payment::STATUS_PAID &&
            $previousStatus !== Payment::STATUS_PAID
        ) {
            $this->activateEntitlement->handle($payment);
        }

        return view('payments.callback', [
            'payment' => $payment,
            'gatewayStatus' => $parsed['status'],
            'verified' => $verified,
            'message' => $parsed['message'],
        ]);
    }

    private function startCheckout(string $entitlementType): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user, 403);

        $plan = $this->plans->forEntitlement($entitlementType);

        $payment = Payment::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'gateway' => Payment::GATEWAY_WIPAY,
            'entitlement_type' => $entitlementType,
            'order_id' => 'KX-' . Str::upper(Str::random(8)) . '-' . now()->format('YmdHis'),
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
                ],
            ],
            'paid_at' => null,
        ]);

        try {
            $gateway = $this->gateways->for($payment->gateway);

            $session = $gateway->createCheckoutSession($payment, $user, [
                'response_url' => route('payments.wipay.callback'),
                'origin' => config('services.wipay.origin', 'KairoxExchange'),
            ]);

            $payment->update([
                'external_ref' => $session['transaction_id'] ?? null,
                'raw_payload' => array_merge(
                    is_array($payment->raw_payload) ? $payment->raw_payload : [],
                    ['bootstrap' => $session['raw'] ?? []]
                ),
            ]);

            return redirect()->away($session['url']);
        } catch (Throwable $e) {
            $payment->update([
                'status' => Payment::STATUS_FAILED,
                'raw_payload' => array_merge(
                    is_array($payment->raw_payload) ? $payment->raw_payload : [],
                    [
                        'checkout_start_error' => $e->getMessage(),
                        'checkout_start_failed_at' => now()->toDateTimeString(),
                    ]
                ),
            ]);

            Log::error('Payment checkout start failed', [
                'gateway' => $payment->gateway,
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('pricing')
                ->withErrors([
                    'payment' => 'Unable to start payment checkout right now. Please try again shortly.',
                ]);
        }
    }
}