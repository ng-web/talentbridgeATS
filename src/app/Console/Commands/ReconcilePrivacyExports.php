<?php

namespace App\Console\Commands;

use App\Models\DataExport;
use App\Services\Privacy\ApplicantExportService;
use Illuminate\Console\Command;

final class ReconcilePrivacyExports extends Command
{
    protected $signature = 'privacy:exports:reconcile
        {--dry-run : Explicitly inspect without changing state or files}
        {--execute : Apply only deterministic cleanup and state reconciliation}';

    protected $description = 'Inspect recoverable private export lifecycle conditions without exposing applicant data';

    public function handle(ApplicantExportService $service): int
    {
        if ($this->option('dry-run') && $this->option('execute')) {
            $this->error('Choose either --dry-run or --execute, not both.');

            return self::FAILURE;
        }
        $execute = (bool) $this->option('execute');
        $counts = [
            'stale_generating_with_canonical' => 0,
            'stale_generating' => 0,
            'generating_canonical_pending' => 0,
            'ready_missing_artifact' => 0,
            'ready_past_expiry' => 0,
            'orphan_canonical_authorized' => 0,
            'orphan_canonical_failed' => 0,
            'orphan_canonical_purged' => 0,
            'expired_pending_purge' => 0,
            'purging_pending_cleanup' => 0,
            'failed_generation_debris' => 0,
            'orphan_staging' => 0,
        ];

        DataExport::query()->orderBy('id')->chunkById(100, function ($exports) use ($service, $execute, &$counts): void {
            foreach ($exports as $export) {
                $condition = $service->reconciliationCondition($export);
                if ($condition === null) {
                    continue;
                }
                $counts[$condition]++;
                if ($execute) {
                    $service->reconcileDeterministically($export, $condition);
                }
            }
        });

        $this->info($execute ? 'Private export reconciliation executed.' : 'Private export reconciliation dry run; no state or files changed.');
        foreach ($counts as $condition => $count) {
            $this->line(str_replace('_', ' ', $condition).": {$count}");
        }

        return self::SUCCESS;
    }
}
