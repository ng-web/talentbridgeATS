<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class WiPayHostedCheckoutService
{
    /**
     * @param array{
     *   order_id:string,
     *   amount:string,
     *   response_url:string,
     *   customer_first_name?:string,
     *   customer_last_name?:string,
     *   customer_email?:string,
     *   customer_phone?:string,
     *   fee_structure?:string,
     *   origin?:string,
     *   data?:string,
     *   avs?:string
     * } $payload
     */
    public function createCheckoutUrl(array $payload): array
    {
        $baseUrl = rtrim((string) config('services.wipay.base_url'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('WiPay base URL is not configured.');
        }

        $requestBody = [
            'account_number' => (string) config('services.wipay.account_number'),
            'country_code'   => (string) config('services.wipay.country_code', 'JM'),
            'currency'       => (string) config('services.wipay.currency', 'JMD'),
            'environment'    => (string) config('services.wipay.environment', 'live'),
            'fee_structure'  => (string) ($payload['fee_structure'] ?? config('services.wipay.fee_structure', 'customer_pay')),
            'method'         => 'credit_card',
            'order_id'       => $payload['order_id'],
            'origin'         => (string) ($payload['origin'] ?? config('services.wipay.origin', 'KairoxExchange')),
            'response_url'   => $payload['response_url'],
            'total'          => number_format((float) $payload['amount'], 2, '.', ''),
        ];

        if (!empty($payload['customer_first_name'])) {
            $requestBody['fname'] = $payload['customer_first_name'];
        }

        if (!empty($payload['customer_last_name'])) {
            $requestBody['lname'] = $payload['customer_last_name'];
        }

        if (empty($requestBody['fname']) && !empty($payload['customer_first_name'])) {
            $requestBody['name'] = trim(
                $payload['customer_first_name'] . ' ' . ($payload['customer_last_name'] ?? '')
            );
        }

        if (!empty($payload['customer_email'])) {
            $requestBody['email'] = $payload['customer_email'];
        }

        if (!empty($payload['customer_phone'])) {
            $requestBody['phone'] = $payload['customer_phone'];
        }

        if (!empty($payload['data'])) {
            $requestBody['data'] = $payload['data'];
        }

        if (array_key_exists('avs', $payload) && $payload['avs'] !== '') {
            $requestBody['avs'] = $payload['avs'];
        }

        Log::info('WiPay checkout request', [
            'url' => $baseUrl . '/plugins/payments/request',
            'payload' => array_merge($requestBody, [
                'account_number' => '***redacted***',
            ]),
        ]);

        $response = Http::asForm()
            ->acceptJson()
            ->post($baseUrl . '/plugins/payments/request', $requestBody);

        try {
            $response->throw();
        } catch (RequestException $e) {
            Log::error('WiPay checkout request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('WiPay checkout request failed: ' . $response->body(), previous: $e);
        }

        $json = $response->json();

        Log::info('WiPay checkout response', [
            'status' => $response->status(),
            'body' => $json ?: $response->body(),
        ]);

        if (!is_array($json) || empty($json['url'])) {
            throw new RuntimeException('WiPay checkout URL missing from response.');
        }

        return $json;
    }

    public function verifyCallbackHash(string $transactionId, string $total, string $hash): bool
    {
        $apiKey = (string) config('services.wipay.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('WiPay API key is not configured.');
        }

        $normalizedTotal = number_format((float) $total, 2, '.', '');
        $expected = md5($transactionId . $normalizedTotal . $apiKey);

        return hash_equals($expected, $hash);
    }
}