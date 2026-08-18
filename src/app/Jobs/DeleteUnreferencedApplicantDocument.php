<?php

namespace App\Jobs;

use App\Services\Documents\ApplicantDocumentLifecycle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class DeleteUnreferencedApplicantDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly string $path) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(ApplicantDocumentLifecycle $lifecycle): void
    {
        if (! $lifecycle->deleteIfUnreferenced($this->path)) {
            throw new RuntimeException('An unreferenced applicant document could not be physically removed.');
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Applicant document cleanup exhausted its retries', [
            'path_fingerprint' => hash('sha256', $this->path),
            'exception_class' => $exception ? $exception::class : null,
        ]);
    }
}
