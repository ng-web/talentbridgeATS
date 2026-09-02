<?php

namespace App\Console\Commands;

use App\Models\DataExport;
use App\Services\Privacy\ApplicantExportService;
use App\Services\Security\PrivacyAuditService;
use Illuminate\Console\Command;

final class PurgeExpiredPrivacyExports extends Command
{
    protected $signature = 'privacy:exports:purge-expired';

    protected $description = 'Idempotently purge expired private applicant export artifacts';

    public function handle(ApplicantExportService $service, PrivacyAuditService $audit): int
    {
        $purged = 0;

        DataExport::query()
            ->where('status', DataExport::STATUS_READY)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($exports) use ($service): void {
                foreach ($exports as $export) {
                    $service->expire($export);
                }
            });

        DataExport::query()
            ->whereIn('status', [DataExport::STATUS_EXPIRED, DataExport::STATUS_PURGING])
            ->orderBy('id')
            ->chunkById(100, function ($exports) use ($service, &$purged): void {
                foreach ($exports as $export) {
                    if ($service->purge($export)) {
                        $purged++;
                    }
                }
            });

        $this->info("Expired private exports purged: {$purged}");
        $audit->record(event: 'data_export_purge_completed', metadata: ['record_count' => $purged]);

        return self::SUCCESS;
    }
}
