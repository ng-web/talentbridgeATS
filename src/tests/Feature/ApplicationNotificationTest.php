<?php

namespace Tests\Feature;

use App\Mail\AdminNewApplicationMail;
use App\Mail\EmployerNewApplicantMail;
use App\Mail\JobSeekerApplicationSubmittedMail;
use App\Models\Employer;
use App\Models\Entitlement;
use App\Models\Job;
use App\Models\JobSeeker;
use App\Models\Program;
use App\Models\User;
use App\Notifications\ApplicationSubmittedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class ApplicationNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
        config()->set('mail.admin_address', 'operations@example.test');
    }

    public function test_application_sends_applicant_employer_and_configured_admin_email(): void
    {
        Mail::fake();
        Notification::fake();
        [$applicant, $employerUser, $employer, $job] = $this->applicationScenario();

        $this->actingAs($applicant)
            ->post(route('jobseeker.jobs.apply.store', $job), [
                'cover_letter' => UploadedFile::fake()->create('cover-letter.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('jobseeker.applications.index'));

        $this->assertDatabaseCount('applications', 1);
        Notification::assertSentTo($employerUser, ApplicationSubmittedNotification::class);
        Mail::assertSent(JobSeekerApplicationSubmittedMail::class, fn ($mail) => $mail->hasTo($applicant->email));
        Mail::assertSent(EmployerNewApplicantMail::class, fn ($mail) => $mail->hasTo($employer->notificationEmail()));
        Mail::assertSent(AdminNewApplicationMail::class, function ($mail) {
            $html = $mail->render();

            return $mail->hasTo('operations@example.test')
                && str_contains($html, '+1 876 555 0110')
                && str_contains($html, 'Summer Work &amp; Travel')
                && str_contains($html, 'Hospitality Associate')
                && str_contains($html, 'Test Sponsor');
        });
    }

    public function test_applicant_mail_failure_does_not_block_other_recipients_or_application_creation(): void
    {
        Notification::fake();
        [$applicant, $employerUser, $employer, $job] = $this->applicationScenario();

        $employerPending = Mockery::mock(PendingMail::class);
        $employerPending->shouldReceive('send')->once()->with(Mockery::type(EmployerNewApplicantMail::class));

        $adminPending = Mockery::mock(PendingMail::class);
        $adminPending->shouldReceive('send')->once()->with(Mockery::type(AdminNewApplicationMail::class));

        Mail::shouldReceive('to')->times(3)->andReturnUsing(
            function (string $recipient) use ($applicant, $employer, $employerPending, $adminPending) {
                if ($recipient === $applicant->email) {
                    throw new RuntimeException('Simulated applicant mail failure');
                }

                return $recipient === $employer->notificationEmail() ? $employerPending : $adminPending;
            }
        );

        $this->actingAs($applicant)
            ->post(route('jobseeker.jobs.apply.store', $job), [
                'cover_letter' => UploadedFile::fake()->create('cover-letter.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('jobseeker.applications.index'));

        $this->assertDatabaseCount('applications', 1);
        Notification::assertSentTo($employerUser, ApplicationSubmittedNotification::class);
    }

    public function test_missing_effective_admin_recipient_does_not_block_other_notification_attempts(): void
    {
        Mail::fake();
        Notification::fake();
        config()->set('mail.admin_address');
        [$applicant, $employerUser, $employer, $job] = $this->applicationScenario();

        $this->actingAs($applicant)
            ->post(route('jobseeker.jobs.apply.store', $job), [
                'cover_letter' => UploadedFile::fake()->create('cover-letter.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('jobseeker.applications.index'));

        $this->assertDatabaseCount('applications', 1);
        Notification::assertSentTo($employerUser, ApplicationSubmittedNotification::class);
        Mail::assertSent(JobSeekerApplicationSubmittedMail::class, fn ($mail) => $mail->hasTo($applicant->email));
        Mail::assertSent(EmployerNewApplicantMail::class, fn ($mail) => $mail->hasTo($employer->notificationEmail()));
        Mail::assertNotSent(AdminNewApplicationMail::class);
    }

    private function applicationScenario(): array
    {
        $program = Program::create([
            'name' => 'Summer Work & Travel',
            'slug' => 'summer-work-travel',
            'is_active' => true,
        ]);

        $applicant = User::factory()->create([
            'name' => 'Application Applicant',
            'email' => 'applicant@example.test',
        ]);
        $applicant->assignRole('job_seeker');
        JobSeeker::create([
            'user_id' => $applicant->id,
            'program_id' => $program->id,
            'phone' => '+1 876 555 0110',
            'resume_path' => 'profiles/resume.pdf',
        ]);
        Entitlement::create([
            'user_id' => $applicant->id,
            'type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'status' => Entitlement::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'source' => 'test',
        ]);

        $employerUser = User::factory()->create(['email' => 'employer-user@example.test']);
        $employerUser->assignRole('employer');
        $employer = Employer::create([
            'user_id' => $employerUser->id,
            'company_name' => 'Test Sponsor',
            'notification_email' => 'sponsor-notifications@example.test',
        ]);
        $job = Job::create([
            'employer_id' => $employer->id,
            'program_id' => $program->id,
            'title' => 'Hospitality Associate',
            'description' => 'A published test opportunity.',
            'status' => Job::STATUS_PUBLISHED,
            'is_approved' => true,
            'application_deadline' => now()->addMonth(),
        ]);

        return [$applicant->fresh(), $employerUser->fresh(), $employer->fresh(), $job];
    }
}
