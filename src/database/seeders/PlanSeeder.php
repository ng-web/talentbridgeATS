<?php

namespace Database\Seeders;

use App\Models\Entitlement;
use App\Models\Plan;
use Illuminate\Database\Seeder;

final class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // ── Job Seeker Programs ───────────────────────────────────────────────

        Plan::updateOrCreate(
            ['slug' => 'au-pair'],
            [
                'name'             => 'Au Pair Program',
                'entitlement_type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
                'amount'           => '750.00',
                'currency'         => 'USD',
                'duration_days'    => 365,
                'is_active'        => true,
                'meta'             => [
                    'program'     => 'au_pair',
                    'description' => 'Live-in childcare placement with a host family abroad.',
                    'features'    => [
                        'Full program placement support',
                        'Host family matching assistance',
                        'Pre-departure orientation',
                        'Ongoing support throughout placement',
                    ],
                ],
            ]
        );

        Plan::updateOrCreate(
            ['slug' => 'internship-premium'],
            [
                'name'             => 'Internship & Trainee — Premium Package',
                'entitlement_type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
                'amount'           => '4030.00',
                'currency'         => 'USD',
                'duration_days'    => 365,
                'is_active'        => true,
                'meta'             => [
                    'program'     => 'internship_trainee',
                    'package'     => 'premium',
                    'description' => 'Full-service internship placement with comprehensive support.',
                    'features'    => [
                        'Guaranteed placement assistance',
                        'Visa sponsorship support',
                        'Housing placement coordination',
                        'Airport pickup and orientation',
                        'Dedicated programme coordinator',
                    ],
                ],
            ]
        );

        Plan::updateOrCreate(
            ['slug' => 'internship-independent'],
            [
                'name'             => 'Internship & Trainee — Independent Placement',
                'entitlement_type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
                'amount'           => '2630.00',
                'currency'         => 'USD',
                'duration_days'    => 365,
                'is_active'        => true,
                'meta'             => [
                    'program'     => 'internship_trainee',
                    'package'     => 'independent',
                    'description' => 'Self-directed placement with access to our employer network.',
                    'features'    => [
                        'Access to verified employer listings',
                        'Application and profile support',
                        'Visa documentation guidance',
                        'Community support network',
                    ],
                ],
            ]
        );

        // ── Deactivate legacy plans ───────────────────────────────────────────

        Plan::where('slug', 'job-seeker-access-monthly')->update(['is_active' => false]);
        Plan::where('slug', 'employer-posting-access-monthly')->update(['is_active' => false]);
    }
}
