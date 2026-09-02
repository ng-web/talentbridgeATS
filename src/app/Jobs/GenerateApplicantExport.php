<?php

namespace App\Jobs;

use App\Models\DataExport;
use App\Services\Privacy\ApplicantExportService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;

final class GenerateApplicantExport implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $dataExportId) {}

    public function handle(ApplicantExportService $service): void
    {
        $export = $service->generate(DataExport::query()->findOrFail($this->dataExportId));

        if ($export->status === DataExport::STATUS_GENERATING && $this->job !== null) {
            $staleAt = $export->generation_started_at?->copy()->addMinutes(
                max(1, (int) config('privacy.exports.generation_stale_minutes', 10))
            );
            $delay = $staleAt ? max(1, now()->diffInSeconds($staleAt, false) + 1) : 60;
            $this->release($delay);
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->dataExportId;
    }
}
