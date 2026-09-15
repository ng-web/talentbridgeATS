<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Privacy\IncidentService;
use Illuminate\Console\Command;

final class ReconcileIncidents extends Command
{
    protected $signature = 'privacy:reconcile-incidents {user_id : Numeric ID of the authorized administrator} {--execute : Mark deterministic inconsistencies for review}';

    protected $description = 'Dry-run incident consistency checks; execute can only add review-required evidence';

    public function handle(IncidentService $incidents): int
    {
        $id = filter_var($this->argument('user_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $user = $id ? User::query()->find($id) : null;
        if (! $user) {
            $this->error('A valid active administrator ID is required.');

            return self::FAILURE;
        }
        try {
            $result = $incidents->reconcile($user, (bool) $this->option('execute'));
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->first() ?? 'Reconciliation denied.');

            return self::FAILURE;
        }
        $mode = $this->option('execute') ? 'execute' : 'dry-run';
        $this->info("Incident reconciliation {$mode}: {$result['report_count']} report(s), {$result['repair_count']} review marker(s).");

        return self::SUCCESS;
    }
}
