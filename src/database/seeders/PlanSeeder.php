<?php

namespace Database\Seeders;

use App\Models\Entitlement;
use App\Models\Plan;
use Illuminate\Database\Seeder;

final class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(
            ['slug' => 'job-seeker-access-monthly'],
            [
                'name' => 'Job Seeker Access',
                'entitlement_type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
                'amount' => '115.00',
                'currency' => 'JMD',
                'duration_days' => 30,
                'is_active' => true,
                'meta' => [
                    'source' => 'seed',
                ],
            ]
        );

        Plan::updateOrCreate(
            ['slug' => 'employer-posting-access-monthly'],
            [
                'name' => 'Employer Posting Access',
                'entitlement_type' => Entitlement::TYPE_EMPLOYER_POSTING_ACCESS,
                'amount' => '120.00',
                'currency' => 'JMD',
                'duration_days' => 30,
                'is_active' => true,
                'meta' => [
                    'source' => 'seed',
                ],
            ]
        );
    }
}