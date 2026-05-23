<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Employer;
use App\Models\Entitlement;
use App\Models\Job;
use App\Models\JobSeeker;
use App\Models\Payment;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

final class PilotDemoSeeder extends Seeder
{
    public function run(): void
    {
        $program = Program::query()->first();

        if (!$program) {
            $this->command?->error('No Program records found. Run ProgramSeeder first.');
            return;
        }

        Storage::disk('public')->put(
            'demo/seeker-pro-resume.txt',
            "Demo Resume\n\nName: Seeker Pro\nExperience: Hospitality, Customer Service"
        );

        Storage::disk('public')->put(
            'demo/seeker-pro-cover-letter.txt',
            "Demo Cover Letter\n\nI am interested in the opportunity and available to travel."
        );

        Storage::disk('public')->put(
            'demo/seeker-new-resume.txt',
            "Demo Resume\n\nName: Seeker New\nExperience: Entry-level applicant"
        );

        Storage::disk('public')->put(
            'demo/seeker-new-cover-letter.txt',
            "Demo Cover Letter\n\nExcited to begin my journey through Kairox Exchange."
        );

        $admin = User::updateOrCreate(
            ['email' => 'admin@kairox.test'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
            ]
        );
        $admin->syncRoles(['admin']);

        $employerWithAccessUser = User::updateOrCreate(
            ['email' => 'employer.active@kairox.test'],
            [
                'name' => 'Employer Active',
                'password' => Hash::make('password'),
            ]
        );
        $employerWithAccessUser->syncRoles(['employer']);

        $employerNoAccessUser = User::updateOrCreate(
            ['email' => 'employer.locked@kairox.test'],
            [
                'name' => 'Employer Locked',
                'password' => Hash::make('password'),
            ]
        );
        $employerNoAccessUser->syncRoles(['employer']);

        $seekerWithAccessUser = User::updateOrCreate(
            ['email' => 'seeker.active@kairox.test'],
            [
                'name' => 'Seeker Active',
                'password' => Hash::make('password'),
            ]
        );
        $seekerWithAccessUser->syncRoles(['job_seeker']);

        $seekerNoAccessUser = User::updateOrCreate(
            ['email' => 'seeker.locked@kairox.test'],
            [
                'name' => 'Seeker Locked',
                'password' => Hash::make('password'),
            ]
        );
        $seekerNoAccessUser->syncRoles(['job_seeker']);

        $employerWithAccess = Employer::updateOrCreate(
            ['user_id' => $employerWithAccessUser->id],
            [
                'company_name' => 'Kairox Recruiting Group',
                'industry' => 'Recruitment & Mobility',
            ]
        );

        $employerNoAccess = Employer::updateOrCreate(
            ['user_id' => $employerNoAccessUser->id],
            [
                'company_name' => 'Northshore Global Placements',
                'industry' => 'International Placement',
            ]
        );

        $jobSeekerWithAccess = JobSeeker::updateOrCreate(
            ['user_id' => $seekerWithAccessUser->id],
            [
                'resume_path' => 'demo/seeker-pro-resume.txt',
                'cover_letter_path' => 'demo/seeker-pro-cover-letter.txt',
                'profile_completeness' => 85,
            ]
        );

        $jobSeekerNoAccess = JobSeeker::updateOrCreate(
            ['user_id' => $seekerNoAccessUser->id],
            [
                'resume_path' => 'demo/seeker-new-resume.txt',
                'cover_letter_path' => 'demo/seeker-new-cover-letter.txt',
                'profile_completeness' => 65,
            ]
        );

        Entitlement::updateOrCreate(
            [
                'user_id' => $employerWithAccessUser->id,
                'type' => 'employer_posting_access',
            ],
            [
                'status' => 'active',
                'starts_at' => now()->subDay(),
                'expires_at' => now()->addMonth(),
                'source' => 'pilot_seed',
                'notes' => 'Pilot employer access',
            ]
        );

        Entitlement::updateOrCreate(
            [
                'user_id' => $seekerWithAccessUser->id,
                'type' => 'job_seeker_access',
            ],
            [
                'status' => 'active',
                'starts_at' => now()->subDay(),
                'expires_at' => now()->addMonth(),
                'source' => 'pilot_seed',
                'notes' => 'Pilot seeker access',
            ]
        );

        Payment::updateOrCreate(
            ['order_id' => 'PILOT-EMPLOYER-001'],
            [
                'user_id' => $employerWithAccessUser->id,
                'gateway' => 'manual',
                'entitlement_type' => 'employer_posting_access',
                'external_ref' => 'MANUAL-EMP-001',
                'currency' => 'JMD',
                'amount' => 15000.00,
                'status' => 'paid',
                'raw_payload' => [
                    'notes' => 'Pilot seeded employer payment',
                ],
                'paid_at' => now()->subDay(),
            ]
        );

        Payment::updateOrCreate(
            ['order_id' => 'PILOT-SEEKER-001'],
            [
                'user_id' => $seekerWithAccessUser->id,
                'gateway' => 'manual',
                'entitlement_type' => 'job_seeker_access',
                'external_ref' => 'MANUAL-SEEKER-001',
                'currency' => 'JMD',
                'amount' => 7500.00,
                'status' => 'paid',
                'raw_payload' => [
                    'notes' => 'Pilot seeded seeker payment',
                ],
                'paid_at' => now()->subDay(),
            ]
        );

        $publishedJobOne = Job::updateOrCreate(
            ['slug' => 'customer-support-representative-pilot'],
            [
                'employer_id' => $employerWithAccess->id,
                'program_id' => $program->id,
                'title' => 'Customer Support Representative',
                'description' => 'Support customers, handle enquiries, and represent the employer professionally.',
                'listing_type' => Job::LISTING_TYPE_SUMMER_WORK_TRAVEL,
                'category' => 'Customer Service',
                'employment_type' => 'Full Time',
                'location' => 'Kingston',
                'country' => 'Jamaica',
                'status' => 'published',
                'is_approved' => true,
                'remote_flag' => false,
            ]
        );

        $publishedJobTwo = Job::updateOrCreate(
            ['slug' => 'summer-work-travel-coordinator-pilot'],
            [
                'employer_id' => $employerWithAccess->id,
                'program_id' => $program->id,
                'title' => 'Summer Work Travel Coordinator',
                'description' => 'Coordinate candidate readiness and support seasonal placement operations.',
                'listing_type' => Job::LISTING_TYPE_INTERNSHIP_ABROAD,
                'category' => 'Program Operations',
                'employment_type' => 'Seasonal',
                'location' => 'Montego Bay',
                'country' => 'Jamaica',
                'status' => 'published',
                'is_approved' => true,
                'remote_flag' => true,
            ]
        );

        Job::updateOrCreate(
            ['slug' => 'pending-hospitality-assistant-pilot'],
            [
                'employer_id' => $employerWithAccess->id,
                'program_id' => $program->id,
                'title' => 'Hospitality Assistant',
                'description' => 'Pending moderation example for admin review.',
                'listing_type' => Job::LISTING_TYPE_SUMMER_WORK_TRAVEL,
                'category' => 'Hospitality',
                'employment_type' => 'Full Time',
                'location' => 'Ocho Rios',
                'country' => 'Jamaica',
                'status' => \App\Models\Job::STATUS_PENDING_REVIEW,
                'is_approved' => false,
                'remote_flag' => false,
            ]
        );

        Job::updateOrCreate(
            ['slug' => 'archived-program-assistant-pilot'],
            [
                'employer_id' => $employerNoAccess->id,
                'program_id' => $program->id,
                'title' => 'Archived Program Assistant',
                'description' => 'Archived job example for testing visibility rules.',
                'listing_type' => Job::LISTING_TYPE_SUMMER_WORK_TRAVEL,
                'category' => 'Administration',
                'employment_type' => 'Contract',
                'location' => 'Spanish Town',
                'country' => 'Jamaica',
                'status' => 'archived',
                'is_approved' => true,
                'remote_flag' => false,
            ]
        );

        Application::updateOrCreate(
            [
                'job_id' => $publishedJobOne->id,
                'job_seeker_id' => $jobSeekerWithAccess->id,
            ],
            [
                'status' => Application::STATUS_APPLIED,
                'applied_at' => now()->subHours(12),
                'submitted_resume_path' => 'demo/seeker-pro-resume.txt',
                'submitted_cover_letter_path' => 'demo/seeker-pro-cover-letter.txt',
            ]
        );

        Application::updateOrCreate(
            [
                'job_id' => $publishedJobTwo->id,
                'job_seeker_id' => $jobSeekerNoAccess->id,
            ],
            [
                'status' => Application::STATUS_SHORTLISTED,
                'applied_at' => now()->subHours(6),
                'submitted_resume_path' => 'demo/seeker-new-resume.txt',
                'submitted_cover_letter_path' => 'demo/seeker-new-cover-letter.txt',
            ]
        );
    }
}