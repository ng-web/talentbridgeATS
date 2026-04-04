<?php

namespace App\Support\Pricing;

use RuntimeException;

final class EntitlementPricing
{
    /**
     * @return array{amount:string,currency:string}
     */
    public function for(string $entitlementType): array
    {
        $pricing = config("pricing.entitlements.{$entitlementType}");

        if (!is_array($pricing)) {
            throw new RuntimeException("Pricing is not configured for entitlement type [{$entitlementType}].");
        }

        $amount = (string) ($pricing['amount'] ?? '');
        $currency = (string) ($pricing['currency'] ?? '');

        if ($amount === '' || $currency === '') {
            throw new RuntimeException("Pricing is incomplete for entitlement type [{$entitlementType}].");
        }

        return [
            'amount' => $amount,
            'currency' => $currency,
        ];
    }
}