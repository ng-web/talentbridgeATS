<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\Contracts\PaymentServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class WiPayPaymentService implements PaymentServiceInterface
{
    public function gateway(): string
    {
        return Payment::GATEWAY_WIPAY;
    }

    public function createCheckoutSession(Payment $payment, User $user, array $meta = []): array
    {
        $baseUrl = rtrim((string) config('services.wipay.base_url'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('WiPay base URL is not configured.');
        }

        $responseUrl = (string) ($meta['response_url'] ?? '');

        if ($responseUrl === '') {
            throw new RuntimeException('WiPay response URL is required.');
        }

        $payload = [
            'account_number' => (string) config('services.wipay.account_number'),
            'country_code' => (string) config('services.wipay.country_code', 'JM'),
            'currency' => (string) ($meta['currency'] ?? $payment->currency ?? config('services.wipay.currency', 'JMD')),
            'environment' => (string) config('services.wipay.environment', 'live'),
            'fee_structure' => (string) config('services.wipay.fee_structure', 'customer_pay'),
            'method' => 'credit_card',
            'card_type' => 'mastercard', // temporary for sandbox consistency
            'order_id' => $payment->order_id,
            'origin' => (string) ($meta['origin'] ?? config('services.wipay.origin', 'KairoxExchange')),
            'response_url' => $responseUrl,
            'total' => number_format((float) $payment->amount, 2, '.', ''),
            'email' => $user->email,
        ];

        $nameParts = preg_split('/\s+/', trim($user->name)) ?: [];
        $firstName = $nameParts[0] ?? 'Customer';
        $lastName = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : '';

        $payload['fname'] = $firstName;

        if ($lastName !== '') {
            $payload['lname'] = $lastName;
        } else {
            $payload['name'] = $user->name ?: $firstName;
        }

        if (! empty($meta['phone'])) {
            $payload['phone'] = (string) $meta['phone'];
        }

        $data = $meta['data'] ?? json_encode([
            'payment_id' => $payment->id,
            'entitlement_type' => $payment->entitlement_type,
        ], JSON_UNESCAPED_SLASHES);

        if (! empty($data)) {
            $payload['data'] = $data;
        }

        if (! empty($meta['avs'])) {
            $payload['avs'] = (string) $meta['avs'];
        }

        Log::info('WiPay checkout request started', [
            'gateway' => $this->gateway(),
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
        ]);

        $response = Http::asForm()
            ->acceptJson()
            ->post($baseUrl.'/plugins/payments/request', $payload);

        $body = $response->json();

        Log::info('WiPay bootstrap response', [
            'gateway' => $this->gateway(),
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'http_status' => $response->status(),
            'transaction_reference_present' => is_array($body) && filled($body['transaction_id'] ?? null),
            'checkout_url_present' => is_array($body) && filled($body['url'] ?? null),
        ]);

        if ($response->failed()) {
            throw new RuntimeException('WiPay bootstrap request failed with HTTP '.$response->status().'.');
        }

        if (! is_array($body) || empty($body['url'])) {
            throw new RuntimeException('WiPay did not return a checkout URL.');
        }

        return [
            'url' => $body['url'],
            'transaction_id' => $body['transaction_id'] ?? null,
            'message' => $body['message'] ?? null,
            'diagnostics' => [
                'http_status' => $response->status(),
                'transaction_id' => $body['transaction_id'] ?? null,
            ],
        ];
    }

    public function parseRedirectPayload(array $payload): array
    {
        return [
            'status' => (string) (
                $payload['status']
                ?? $payload['transaction_status']
                ?? ''
            ),
            'transaction_id' => (string) (
                $payload['transaction_id']
                ?? $payload['transactionId']
                ?? $payload['transactionid']
                ?? $payload['txn_id']
                ?? ''
            ),
            'order_id' => (string) (
                $payload['order_id']
                ?? $payload['orderId']
                ?? ''
            ),
            'message' => (string) (
                $payload['message']
                ?? $payload['reasonDescription']
                ?? ''
            ),
            'hash' => (string) (
                $payload['hash']
                ?? $payload['response_hash']
                ?? $payload['responseHash']
                ?? $payload['signature']
                ?? ''
            ),
            'total' => (string) (
                $payload['total']
                ?? $payload['amount']
                ?? ''
            ),
            'date' => (string) (
                $payload['date']
                ?? $payload['transaction_date']
                ?? ''
            ),
        ];
    }

    public function verifyRedirect(Payment $payment, array $payload): bool
    {
        $parsed = $this->parseRedirectPayload($payload);

        if ($parsed['transaction_id'] === '' || $parsed['hash'] === '') {
            Log::warning('WiPay verify failed: missing transaction id or hash', [
                'payment_id' => $payment->id,
                'transaction_id' => $parsed['transaction_id'],
                'hash_present' => $parsed['hash'] !== '',
            ]);

            return false;
        }

        $expectedTransactionId = trim((string) $payment->external_ref);
        $transactionId = trim((string) $parsed['transaction_id']);
        $callbackOrderId = trim((string) $parsed['order_id']);
        $callbackMinorUnits = $this->minorUnits((string) $parsed['total']);
        $expectedMinorUnits = $this->minorUnits((string) $payment->amount);

        if ($payment->gateway !== $this->gateway()) {
            return $this->rejectBinding($payment, 'gateway_mismatch');
        }

        if ($callbackOrderId === '' || ! hash_equals((string) $payment->order_id, $callbackOrderId)) {
            return $this->rejectBinding($payment, 'order_mismatch');
        }

        if ($expectedTransactionId === '' || ! hash_equals($expectedTransactionId, $transactionId)) {
            return $this->rejectBinding($payment, 'transaction_mismatch');
        }

        if ($callbackMinorUnits === null || $expectedMinorUnits === null || ! hash_equals($expectedMinorUnits, $callbackMinorUnits)) {
            return $this->rejectBinding($payment, 'amount_mismatch');
        }

        $apiKey = trim((string) config('services.wipay.api_key'));

        if ($apiKey === '') {
            throw new RuntimeException('WiPay API key is not configured.');
        }

        $callbackTotalRaw = trim((string) $parsed['total']);
        $callbackTotal = $callbackTotalRaw !== ''
            ? number_format((float) $callbackTotalRaw, 2, '.', '')
            : '';

        $originalTotal = number_format((float) $payment->amount, 2, '.', '');
        $orderId = trim((string) ($parsed['order_id'] ?: $payment->order_id));
        $receivedHash = strtolower(trim((string) $parsed['hash']));

        $candidates = [
            // Most likely candidates
            'txn_callback_api_formatted' => $callbackTotal !== ''
                ? md5($transactionId.$callbackTotal.$apiKey)
                : null,

            'txn_original_api_formatted' => md5($transactionId.$originalTotal.$apiKey),

            'txn_callback_api_raw' => $callbackTotalRaw !== ''
                ? md5($transactionId.$callbackTotalRaw.$apiKey)
                : null,

            // The prior integration's callback verifier used transaction ID + total + API key.
            'txn_original_api_raw' => md5($transactionId.trim((string) $payment->amount).$apiKey),
        ];

        $filteredCandidates = array_filter(
            $candidates,
            static fn ($value) => is_string($value) && $value !== ''
        );

        Log::info('WiPay callback verification attempted', [
            'payment_id' => $payment->id,
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
            'environment' => config('services.wipay.environment'),
            'candidate_count' => count($filteredCandidates),
        ]);

        foreach ($filteredCandidates as $name => $candidate) {
            if (hash_equals($candidate, $receivedHash)) {
                Log::info('WiPay verification matched candidate', [
                    'payment_id' => $payment->id,
                    'matched_candidate' => $name,
                ]);

                return true;
            }
        }

        Log::warning('WiPay verification did not match any candidate', [
            'payment_id' => $payment->id,
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
        ]);

        return false;
    }

    private function minorUnits(string $amount): ?string
    {
        $amount = trim($amount);

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            return null;
        }

        $whole = ltrim($matches[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad($matches[2] ?? '', 2, '0');

        return ltrim($whole.$fraction, '0') ?: '0';
    }

    private function rejectBinding(Payment $payment, string $reason): bool
    {
        Log::warning('WiPay callback binding rejected', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'reason' => $reason,
        ]);

        return false;
    }
}
