<?php

namespace App\Providers;

use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\WiPayPaymentService;
use Illuminate\Support\ServiceProvider;

final class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WiPayPaymentService::class);

        $this->app->singleton(PaymentGatewayManager::class, function ($app) {
            return new PaymentGatewayManager([
                $app->make(WiPayPaymentService::class),
            ]);
        });
    }
}