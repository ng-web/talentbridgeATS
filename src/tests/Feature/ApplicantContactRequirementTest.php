<?php

namespace Tests\Feature;

use App\Http\Controllers\JobSeeker\ApplicationController;
use App\Models\Employer;
use App\Models\Entitlement;
use App\Models\Job;
use App\Models\JobSeeker;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ApplicantContactRequirementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Mail::fake();
        Storage::fake('private');
        config()->set('mail.admin_address', 'operations@example.test');
    }

    public function test_applicant_with_valid_phone_and_email_can_submit(): void
    {
        [$applicant, , $job] = $this->applicationScenario();

        $this->submit($applicant, $job)
            ->assertRedirect(route('jobseeker.applications.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('applications', 1);
    }

    public function test_missing_phone_prevents_submission(): void
    {
        [$applicant, $jobSeeker, $job] = $this->applicationScenario();
        $jobSeeker->update(['phone' => null]);

        $this->actingAs($applicant)
            ->get(route('jobseeker.jobs.apply', $job))
            ->assertOk()
            ->assertSee('Complete your contact details before applying.')
            ->assertSee(route('jobseeker.profile.edit'))
            ->assertSee('disabled', false);

        $this->submit($applicant, $job)->assertSessionHasErrors('applicant_phone');

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_blank_phone_prevents_submission(): void
    {
        [$applicant, $jobSeeker, $job] = $this->applicationScenario();
        DB::table('job_seekers')->where('id', $jobSeeker->id)->update(['phone' => '   ']);

        $this->submit($applicant, $job)->assertSessionHasErrors('applicant_phone');

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_invalid_phone_prevents_submission(): void
    {
        [$applicant, $jobSeeker, $job] = $this->applicationScenario();
        DB::table('job_seekers')->where('id', $jobSeeker->id)->update(['phone' => 'not-a-phone']);

        $this->submit($applicant, $job)->assertSessionHasErrors('applicant_phone');

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_supported_phone_formats_pass_shared_profile_validation(): void
    {
        [$applicant, $jobSeeker] = $this->applicationScenario();
        $phones = [
            'digits only' => '3455550100',
            'Cayman with parentheses' => '+1 (345) 555-0100',
            'Jamaica with hyphens' => '+1 876-555-0100',
            'US with spaces and parentheses' => '+1 (212) 555 0100',
            'international' => '+44 20 7946 0958',
            'seven-digit base with extension' => '555-0100 x123',
            'x extension' => '345-555-0100 x123',
            'ext extension' => '345-555-0100 ext 123',
            'ext dot extension' => '345-555-0100 ext. 123',
        ];

        foreach ($phones as $description => $phone) {
            $this->actingAs($applicant)
                ->patch(route('jobseeker.profile.update'), [
                    'program_id' => $jobSeeker->program_id,
                    'phone' => $phone,
                ])
                ->assertSessionHasNoErrors();

            $this->assertSame($phone, $jobSeeker->fresh()->phone, $description);
        }
    }

    public function test_structurally_invalid_phone_formats_fail_profile_and_submission_validation(): void
    {
        [$applicant, $jobSeeker, $job] = $this->applicationScenario();
        $phones = [
            'multiple leading pluses' => '++1 345 555 0100',
            'trailing pluses' => '1234567++++',
            'repeated opening parentheses' => '((((1234567',
            'extension cannot supply minimum base digits' => '123 ext 4567',
            'misplaced plus' => '1+ 345 555 0100',
            'multiple separated pluses' => '+1 +345 555 0100',
            'unbalanced closing parenthesis' => '345) 555-0100',
            'unbalanced opening parenthesis' => '(345 555-0100',
            'alphabetic base number' => '345-CALL-NOW',
            'extension without digits' => '345-555-0100 ext',
            'fewer than seven base digits' => '123-456',
            'more than twenty base digits' => '123456789012345678901',
            'repeated punctuation' => '345--555--0100',
            'more than thirty characters overall' => '+1 345 555 0100 ext. 1234567890',
        ];

        foreach ($phones as $description => $phone) {
            $this->actingAs($applicant)
                ->patch(route('jobseeker.profile.update'), [
                    'program_id' => $jobSeeker->program_id,
                    'phone' => $phone,
                ])
                ->assertSessionHasErrors('phone');

            DB::table('job_seekers')->where('id', $jobSeeker->id)->update(['phone' => $phone]);

            $this->submit($applicant, $job)->assertSessionHasErrors('applicant_phone');
            $this->assertDatabaseCount('applications', 0);
        }
    }

    public function test_missing_or_invalid_canonical_email_prevents_submission(): void
    {
        foreach (['', 'not-an-email'] as $email) {
            [$applicant, , $job] = $this->applicationScenario();
            DB::table('users')->where('id', $applicant->id)->update(['email' => $email]);

            $this->submit($applicant->fresh(), $job)->assertSessionHasErrors('applicant_email');
        }

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_request_contact_fields_cannot_bypass_canonical_profile_validation(): void
    {
        [$applicant, $jobSeeker, $job] = $this->applicationScenario();
        $jobSeeker->update(['phone' => null]);

        $this->submit($applicant, $job, [
            'applicant_email' => 'substitute@example.test',
            'applicant_phone' => '+1 345 555 0199',
            'email' => 'substitute@example.test',
            'phone' => '+1 345 555 0199',
        ])->assertSessionHasErrors('applicant_phone');

        $this->assertDatabaseCount('applications', 0);
    }

    public function test_incomplete_applicant_can_access_and_edit_both_profile_sources(): void
    {
        [$applicant, $jobSeeker] = $this->applicationScenario();
        $jobSeeker->update(['phone' => null]);

        $this->actingAs($applicant)
            ->get(route('jobseeker.profile.edit'))
            ->assertOk()
            ->assertSee('A valid phone number is required to submit applications.');
        $this->get(route('profile.edit'))->assertOk();

        $this->patch(route('jobseeker.profile.update'), [
            'program_id' => $jobSeeker->program_id,
            'location' => 'George Town',
        ])->assertSessionHasNoErrors();

        $this->assertNull($jobSeeker->fresh()->phone);
        $this->assertSame('George Town', $jobSeeker->fresh()->location);
    }

    public function test_submission_succeeds_after_applicant_adds_missing_contact_details(): void
    {
        [$applicant, $jobSeeker, $job] = $this->applicationScenario();
        $jobSeeker->update(['phone' => null]);
        DB::table('users')->where('id', $applicant->id)->update(['email' => '']);
        $applicant = $applicant->fresh();

        $this->actingAs($applicant)->patch(route('profile.update'), [
            'name' => $applicant->name,
            'email' => 'restored@example.test',
        ])->assertSessionHasNoErrors();
        $this->patch(route('jobseeker.profile.update'), [
            'program_id' => $jobSeeker->program_id,
            'phone' => '  +1 (345) 555-0199  ',
        ])->assertSessionHasNoErrors();

        $this->assertSame('+1 (345) 555-0199', $jobSeeker->fresh()->phone);
        $this->submit($applicant->fresh(), $job)
            ->assertRedirect(route('jobseeker.applications.index'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('applications', 1);
    }

    public function test_there_is_only_one_application_submission_route_and_it_uses_the_guarded_controller(): void
    {
        $submissionRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('POST', $route->methods(), true))
            ->filter(fn ($route) => str_contains((string) $route->getName(), 'apply'))
            ->filter(fn ($route) => str_contains($route->uri(), 'jobs/{job}/apply'));

        $this->assertCount(1, $submissionRoutes);
        $this->assertSame(
            ApplicationController::class.'@store',
            $submissionRoutes->first()->getActionName(),
        );
    }

    private function submit(User $applicant, Job $job, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($applicant)->post(route('jobseeker.jobs.apply.store', $job), array_merge([
            'cover_letter' => UploadedFile::fake()->create('cover-letter.pdf', 100, 'application/pdf'),
        ], $overrides));
    }

    /** @return array{User, JobSeeker, Job} */
    private function applicationScenario(): array
    {
        $program = Program::create([
            'name' => 'Summer Work & Travel '.fake()->unique()->numberBetween(1, 1000000),
            'slug' => fake()->unique()->slug(),
            'is_active' => true,
        ]);
        $applicant = User::factory()->create(['email' => fake()->unique()->safeEmail()]);
        $applicant->assignRole('job_seeker');
        $jobSeeker = JobSeeker::create([
            'user_id' => $applicant->id,
            'program_id' => $program->id,
            'phone' => '+1 345 555 0100',
            'resume_path' => 'profiles/resume.pdf',
        ]);
        Storage::disk('private')->put('profiles/resume.pdf', 'synthetic resume');
        Entitlement::create([
            'user_id' => $applicant->id,
            'type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'status' => Entitlement::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'source' => 'test',
        ]);
        $employerUser = User::factory()->create();
        $employerUser->assignRole('employer');
        $employer = Employer::create([
            'user_id' => $employerUser->id,
            'company_name' => 'Test Employer',
        ]);
        $job = Job::create([
            'employer_id' => $employer->id,
            'program_id' => $program->id,
            'title' => 'Test Opportunity',
            'description' => 'A published test opportunity.',
            'status' => Job::STATUS_PUBLISHED,
            'is_approved' => true,
            'application_deadline' => now()->addMonth(),
        ]);

        return [$applicant->fresh(), $jobSeeker->fresh(), $job];
    }
}
