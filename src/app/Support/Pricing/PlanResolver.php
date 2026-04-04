<?php

namespace App\Support\Pricing;

use App\Models\Plan;
use RuntimeException;

final class PlanResolver
{
    public function forEntitlement(string $entitlementType): Plan
    {
        $plan = Plan::query()
            ->where('entitlement_type', $entitlementType)
            ->where('is_active', true)
            ->first();

        if (!$plan) {
            throw new RuntimeException("No active plan found for entitlement type [{$entitlementType}].");
        }

        return $plan;
    }
}