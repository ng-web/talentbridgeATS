<?php

namespace App\Console\Commands;

use App\Models\Entitlement;
use Illuminate\Console\Command;

final class ExpireEntitlements extends Command
{
    protected $signature = 'entitlements:expire';
    protected $description = 'Mark expired active entitlements as expired';

    public function handle(): int
    {
        $updated = Entitlement::query()
            ->where('status', Entitlement::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update([
                'status' => Entitlement::STATUS_EXPIRED,
            ]);

        $this->info("Expired entitlements updated: {$updated}");

        return self::SUCCESS;
    }
}