<?php

namespace App\Services\Payments\Contracts;

use App\Models\Payment;
use App\Models\User;

interface PaymentServiceInterface
{
    public function gateway(): string;

    public function createCheckoutSession(Payment $payment, User $user, array $meta = []): array;

    public function parseRedirectPayload(array $payload): array;

    public function verifySuccessfulRedirect(Payment $payment, array $payload): bool;
}