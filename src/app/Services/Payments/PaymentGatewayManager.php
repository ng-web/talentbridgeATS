<?php

namespace App\Services\Payments;

use App\Services\Payments\Contracts\PaymentServiceInterface;
use InvalidArgumentException;

final class PaymentGatewayManager
{
    /**
     * @var array<string, PaymentServiceInterface>
     */
    private array $gateways = [];

    /**
     * @param iterable<PaymentServiceInterface> $gateways
     */
    public function __construct(iterable $gateways)
    {
        foreach ($gateways as $gateway) {
            $this->gateways[$gateway->gateway()] = $gateway;
        }
    }

    public function for(string $gateway): PaymentServiceInterface
    {
        if (!isset($this->gateways[$gateway])) {
            throw new InvalidArgumentException("Unsupported payment gateway [{$gateway}].");
        }

        return $this->gateways[$gateway];
    }
}