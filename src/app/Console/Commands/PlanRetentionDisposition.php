<?php

namespace App\Console\Commands;

use App\Models\JobSeekerDocument;
use App\Models\User;
use App\Services\Privacy\DispositionService;
use App\Services\Privacy\RetentionDataCategories;
use App\Services\Privacy\RetentionEligibility;
use App\Services\Privacy\RetentionEligibilityService;
use App\Services\Privacy\RetentionRuleRegistryService;
use App\Support\PrivacySecurityPermissions;
use Illuminate\Console\Command;

final class PlanRetentionDisposition extends Command
{
    protected $signature = 'privacy:retention-plan {category} {--user_id=} {--execute : Persist a reviewable plan; never disposes data}';

    protected $description = 'Dry-run technical retention eligibility, or explicitly persist a non-destructive disposition plan';

    public function handle(RetentionRuleRegistryService $rules, RetentionEligibilityService $eligibility, DispositionService $disposition): int
    {
        $actor = User::query()->find((int) $this->option('user_id'));
        if (! $actor || ! $actor->hasRole('admin') || ! $actor->hasDirectPermission(PrivacySecurityPermissions::DISPOSITION_PLAN)) {
            $this->error('A current specifically authorized administrator ID is required.');

            return self::FAILURE;
        }
        $category = (string) $this->argument('category');
        if ($category !== RetentionDataCategories::APPLICANT_DOCUMENT) {
            $this->error('UNSUPPORTED: no action taken.');

            return self::FAILURE;
        }
        $rule = $rules->governing($category);
        if (! $rule) {
            $this->warn('NO_RULE: 0 technically eligible; no action taken.');

            return self::SUCCESS;
        }
        $resources = JobSeekerDocument::query()->with('jobSeeker')->orderBy('id')->get();
        $eligible = $resources->filter(fn ($resource) => $eligibility->evaluate($category, $resource, boundRule: $rule)->status === RetentionEligibility::ELIGIBLE)->values();
        $this->line('Category: '.$category);
        $this->line('Technically eligible under configured rule: '.$eligible->count());
        $this->line('Evaluated: '.$resources->count());
        if (! $this->option('execute')) {
            $this->info('DRY RUN: no plan or subject data was modified.');

            return self::SUCCESS;
        }
        $plan = $disposition->createPlan($actor, $rule, $eligible);
        $this->info('Reviewable plan created: '.$plan->uuid.' ('.$plan->items->count().' items). No subject data was modified.');

        return self::SUCCESS;
    }
}
