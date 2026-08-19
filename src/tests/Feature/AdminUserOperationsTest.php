<?php

namespace Tests\Feature;

use App\Models\AdminOverride;
use App\Models\Application;
use App\Models\ApplicationFile;
use App\Models\Employer;
use App\Models\Entitlement;
use App\Models\Job;
use App\Models\JobSeeker;
use App\Models\JobSeekerDocument;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Program;
use App\Models\User;
use App\Support\PrivacySecurityPermissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdminUserOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create(['name' => 'Operations Admin']);
        $this->admin->assignRole('admin');
        $this->admin = $this->enrollAdministratorMfa($this->admin);
        $this->admin->givePermissionTo(PrivacySecurityPermissions::ADMIN_SECURITY_MANAGE);
    }

    public function test_admin_detail_and_list_show_applicant_program_phone_and_fallbacks(): void
    {
        $program = $this->program('Summer Work & Travel', 'summer-work-travel');
        $applicant = $this->applicant('Applicant With Details', 'applicant@example.com', $program, '+1 876 555 0100');
        $legacy = $this->applicant('Legacy Applicant', 'legacy@example.com');

        $this->actingAsMfaVerified($this->admin)
            ->get(route('admin.users.show', $applicant))
            ->assertOk()
            ->assertSeeText('+1 876 555 0100')
            ->assertSee('Summer Work &amp; Travel', false)
            ->assertSeeText('Current Program')
            ->assertSeeText('Move to Recycle Bin');

        $this->actingAsMfaVerified($this->admin)
            ->get(route('admin.users.show', $legacy))
            ->assertOk()
            ->assertSee('Not provided')
            ->assertSee('Not selected');

        $this->actingAsMfaVerified($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSeeText('+1 876 555 0100')
            ->assertSee('Summer Work &amp; Travel', false)
            ->assertSeeText('No phone')
            ->assertSeeText('Not selected');
    }

    public function test_admin_can_assign_change_and_clear_current_program_with_audit_history(): void
    {
        $first = $this->program('Au Pair', 'au-pair');
        $second = $this->program('Camp Counselor', 'camp-counselor');
        $applicant = $this->applicant('Program Update Applicant', 'program-update@example.com');

        $this->actingAsMfaVerified($this->admin)
            ->patch(route('admin.users.update-program', $applicant), ['program_id' => $first->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('job_seekers', ['user_id' => $applicant->id, 'program_id' => $first->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'job_seeker_program_assigned', 'entity_id' => $applicant->id]);

        $this->patch(route('admin.users.update-program', $applicant), ['program_id' => $second->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'job_seeker_program_changed', 'entity_id' => $applicant->id]);

        $this->patch(route('admin.users.update-program', $applicant), ['program_id' => null]);
        $this->assertDatabaseHas('job_seekers', ['user_id' => $applicant->id, 'program_id' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'job_seeker_program_cleared', 'entity_id' => $applicant->id]);
    }

    public function test_inactive_current_program_is_preserved_and_validation_errors_are_visible(): void
    {
        $program = $this->program('Summer Work & Travel Program', 'review-inactive-current-program');
        $applicant = $this->applicant('Inactive Program Applicant', 'inactive-program@example.com', $program);
        $program->update(['is_active' => false]);

        $this->actingAsMfaVerified($this->admin)
            ->get(route('admin.users.show', $applicant))
            ->assertOk()
            ->assertSeeText('Summer Work & Travel Program (Inactive)')
            ->assertSeeText('Changing or clearing this association does not alter existing payments, access, or applications.');

        $this->patch(route('admin.users.update-program', $applicant), ['program_id' => $program->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('job_seekers', ['user_id' => $applicant->id, 'program_id' => $program->id]);

        $this->followingRedirects()
            ->patch(route('admin.users.update-program', $applicant), ['program_id' => 999999])
            ->assertSeeText('The selected program id is invalid.');

        $this->assertDatabaseHas('job_seekers', ['user_id' => $applicant->id, 'program_id' => $program->id]);
    }

    public function test_non_admin_cannot_correct_an_applicant_program(): void
    {
        $program = $this->program('Au Pair Authorization', 'au-pair-authorization');
        $target = $this->applicant('Program Target', 'program-target@example.com');
        $nonAdmin = $this->applicant('Unauthorized Applicant', 'unauthorized@example.com');

        $this->actingAs($nonAdmin)
            ->patch(route('admin.users.update-program', $target), ['program_id' => $program->id])
            ->assertForbidden();

        $this->assertDatabaseHas('job_seekers', ['user_id' => $target->id, 'program_id' => null]);
    }

    public function test_program_access_and_latest_payment_filters_use_authoritative_records(): void
    {
        $program = $this->program('Internship Abroad', 'internship-abroad');
        $matching = $this->applicant('Matching Applicant', 'matching@example.com', $program);
        $other = $this->applicant('Other Applicant', 'other@example.com');

        Entitlement::create([
            'user_id' => $matching->id,
            'type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'status' => Entitlement::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'source' => 'test',
        ]);

        Payment::create([
            'user_id' => $matching->id,
            'gateway' => Payment::GATEWAY_WIPAY,
            'entitlement_type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'order_id' => 'failed-earlier',
            'currency' => 'JMD',
            'amount' => 100,
            'status' => Payment::STATUS_FAILED,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        Payment::create([
            'user_id' => $matching->id,
            'gateway' => Payment::GATEWAY_WIPAY,
            'entitlement_type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'order_id' => 'paid-latest',
            'currency' => 'JMD',
            'amount' => 100,
            'status' => Payment::STATUS_PAID,
        ]);

        $response = $this->actingAsMfaVerified($this->admin)->get(route('admin.users.index', [
            'program' => $program->id,
            'access' => 'active',
            'payment' => Payment::STATUS_PAID,
        ]));

        $response->assertOk()
            ->assertSee('Matching Applicant')
            ->assertSee('Active')
            ->assertSee('Paid')
            ->assertDontSee('Other Applicant');
    }

    public function test_access_filters_match_displayed_state_for_mixed_and_time_bound_histories(): void
    {
        $revoked = $this->applicant('Mixed Revoked Applicant', 'mixed-revoked@example.com');
        Entitlement::create([
            'user_id' => $revoked->id,
            'type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'status' => Entitlement::STATUS_INACTIVE,
            'source' => 'test-old-inactive',
        ]);
        Entitlement::create([
            'user_id' => $revoked->id,
            'type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'status' => Entitlement::STATUS_REVOKED,
            'source' => 'test-current-revoked',
        ]);

        $active = $this->applicant('Current Active Applicant', 'current-active@example.com');
        Entitlement::create([
            'user_id' => $active->id,
            'type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'status' => Entitlement::STATUS_EXPIRED,
            'expires_at' => now()->subMonth(),
            'source' => 'test-old-expired',
        ]);
        Entitlement::create([
            'user_id' => $active->id,
            'type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'status' => Entitlement::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'source' => 'test-current-active',
        ]);

        $future = $this->applicant('Future Inactive Applicant', 'future-inactive@example.com');
        Entitlement::create([
            'user_id' => $future->id,
            'type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'status' => Entitlement::STATUS_ACTIVE,
            'starts_at' => now()->addWeek(),
            'expires_at' => now()->addMonth(),
            'source' => 'test-future-active',
        ]);

        $expired = $this->applicant('Only Expired Applicant', 'only-expired@example.com');
        Entitlement::create([
            'user_id' => $expired->id,
            'type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'status' => Entitlement::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
            'source' => 'test-expired',
        ]);

        $none = $this->applicant('Zero Entitlement Applicant', 'zero-entitlement@example.com');

        $expectedStates = [
            $revoked->id => User::ACCESS_REVOKED,
            $active->id => User::ACCESS_ACTIVE,
            $future->id => User::ACCESS_INACTIVE,
            $expired->id => User::ACCESS_EXPIRED,
            $none->id => User::ACCESS_NONE,
        ];

        foreach ($expectedStates as $userId => $expectedState) {
            $user = User::query()->with(['currentEntitlement', 'latestEntitlement'])->findOrFail($userId);
            $this->assertSame($expectedState, $user->accessSummaryState());
        }

        $namesByState = [
            User::ACCESS_ACTIVE => 'Current Active Applicant',
            User::ACCESS_INACTIVE => 'Future Inactive Applicant',
            User::ACCESS_EXPIRED => 'Only Expired Applicant',
            User::ACCESS_REVOKED => 'Mixed Revoked Applicant',
            User::ACCESS_NONE => 'Zero Entitlement Applicant',
        ];

        foreach ($namesByState as $state => $expectedName) {
            $response = $this->actingAsMfaVerified($this->admin)->get(route('admin.users.index', [
                'role' => 'job_seeker',
                'access' => $state,
            ]));

            $response->assertOk()->assertSeeText($expectedName);

            foreach ($namesByState as $otherName) {
                if ($otherName !== $expectedName) {
                    $response->assertDontSeeText($otherName);
                }
            }
        }
    }

    public function test_admin_detail_keeps_current_program_payment_plan_and_application_distinct(): void
    {
        $program = $this->program('Cultural Exchange & Volunteer', 'cultural-exchange');
        $applicant = $this->applicant('Operational Summary Applicant', 'summary@example.com', $program, '+1 876 555 0120');
        $plan = Plan::create([
            'name' => 'Premium Access Plan',
            'slug' => 'premium-access-plan',
            'entitlement_type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'amount' => 500,
            'currency' => 'JMD',
            'duration_days' => 365,
            'is_active' => true,
        ]);
        Payment::create([
            'user_id' => $applicant->id,
            'plan_id' => $plan->id,
            'gateway' => Payment::GATEWAY_WIPAY,
            'entitlement_type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'order_id' => 'summary-payment',
            'currency' => 'JMD',
            'amount' => 500,
            'status' => Payment::STATUS_PAID,
        ]);
        Application::create([
            'job_id' => $this->job()->id,
            'job_seeker_id' => $applicant->jobSeeker->id,
            'status' => Application::STATUS_REVIEWING,
            'applied_at' => now(),
        ]);

        $this->actingAsMfaVerified($this->admin)
            ->get(route('admin.users.show', $applicant))
            ->assertOk()
            ->assertSee('Cultural Exchange &amp; Volunteer', false)
            ->assertSeeText('Payment Plan:')
            ->assertSeeText('Premium Access Plan')
            ->assertSeeText('Applications')
            ->assertSeeText('1 total')
            ->assertSeeText('Test Opportunity')
            ->assertSeeText('Reviewing');
    }

    public function test_employer_rows_render_without_applicant_fallback_metadata(): void
    {
        $employerUser = User::factory()->create(['name' => 'Sponsor User']);
        $employerUser->assignRole('employer');
        Employer::create(['user_id' => $employerUser->id, 'company_name' => 'Sponsor Company']);

        $this->actingAsMfaVerified($this->admin)
            ->get(route('admin.users.index', ['role' => 'employer']))
            ->assertOk()
            ->assertSee('Sponsor Company')
            ->assertDontSee('No phone')
            ->assertDontSee('Not selected');
    }

    public function test_access_statuses_render_for_expired_revoked_and_no_access_users(): void
    {
        $expired = $this->applicant('Expired Access Applicant', 'expired@example.com');
        $revoked = $this->applicant('Revoked Access Applicant', 'revoked@example.com');
        $this->applicant('No Access Applicant', 'no-access@example.com');

        Entitlement::create([
            'user_id' => $expired->id,
            'type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'status' => Entitlement::STATUS_ACTIVE,
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->subDay(),
            'source' => 'test',
        ]);
        Entitlement::create([
            'user_id' => $revoked->id,
            'type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'status' => Entitlement::STATUS_REVOKED,
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->subDay(),
            'source' => 'test',
        ]);

        $this->actingAsMfaVerified($this->admin)
            ->get(route('admin.users.index', ['role' => 'job_seeker']))
            ->assertOk()
            ->assertSeeText('Expired Access Applicant')
            ->assertSeeText('Expired')
            ->assertSeeText('Revoked Access Applicant')
            ->assertSeeText('Revoked')
            ->assertSeeText('No Access Applicant')
            ->assertSeeText('No Access');
    }

    public function test_soft_delete_preserves_history_and_restore_recovers_the_user(): void
    {
        $applicant = $this->applicant('Retained Applicant', 'retained@example.com');
        $jobSeeker = $applicant->jobSeeker;
        $job = $this->job();

        $payment = Payment::create([
            'user_id' => $applicant->id,
            'gateway' => Payment::GATEWAY_WIPAY,
            'entitlement_type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'order_id' => 'retained-payment',
            'currency' => 'JMD',
            'amount' => 100,
            'status' => Payment::STATUS_PAID,
        ]);
        $application = Application::create([
            'job_id' => $job->id,
            'job_seeker_id' => $jobSeeker->id,
            'status' => Application::STATUS_APPLIED,
            'applied_at' => now(),
        ]);
        $applicationFile = ApplicationFile::create([
            'application_id' => $application->id,
            'document_type' => 'resume',
            'file_path' => 'applications/resume.pdf',
            'original_name' => 'resume.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);
        $document = JobSeekerDocument::create([
            'job_seeker_id' => $jobSeeker->id,
            'document_type' => JobSeekerDocument::TYPE_PASSPORT,
            'file_path' => 'documents/passport.pdf',
        ]);

        $this->actingAsMfaVerified($this->admin)->delete(route('admin.users.destroy', $applicant))->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted('users', ['id' => $applicant->id]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
        $this->assertDatabaseHas('applications', ['id' => $application->id]);
        $this->assertDatabaseHas('application_files', ['id' => $applicationFile->id]);
        $this->assertDatabaseHas('job_seeker_documents', ['id' => $document->id]);

        $this->patch(route('admin.users.restore', $applicant->id))->assertRedirect(route('admin.users.deleted'));
        $this->assertNotSoftDeleted('users', ['id' => $applicant->id]);
    }

    public function test_permanent_delete_is_blocked_for_document_bearing_user(): void
    {
        $applicant = $this->applicant('Document Applicant', 'document@example.com');
        JobSeekerDocument::create([
            'job_seeker_id' => $applicant->jobSeeker->id,
            'document_type' => JobSeekerDocument::TYPE_MEDICAL_RECORD,
            'file_path' => 'documents/medical.pdf',
        ]);
        $applicant->delete();

        $this->actingAsMfaVerified($this->admin)
            ->delete(route('admin.users.force-delete', $applicant->id))
            ->assertSessionHas('error');

        $this->assertSoftDeleted('users', ['id' => $applicant->id]);
        $this->assertDatabaseHas('job_seeker_documents', ['job_seeker_id' => $applicant->jobSeeker->id]);
    }

    public function test_permanent_delete_is_blocked_for_retention_sensitive_admin_overrides(): void
    {
        $target = User::factory()->create(['name' => 'Override Target']);
        $override = AdminOverride::create([
            'user_id' => $target->id,
            'entitlement_type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'reason' => 'Retention-sensitive access decision.',
            'granted_by' => $this->admin->id,
            'granted_at' => now(),
        ]);
        $target->delete();

        $this->actingAsMfaVerified($this->admin)
            ->delete(route('admin.users.force-delete', $target->id))
            ->assertSessionHas('error');

        $this->assertSoftDeleted('users', ['id' => $target->id]);
        $this->assertDatabaseHas('admin_overrides', ['id' => $override->id]);
    }

    public function test_permanent_delete_cleans_ephemeral_notifications_and_sessions(): void
    {
        $target = User::factory()->create(['name' => 'Operational Cleanup Target']);
        $notificationId = (string) Str::uuid();
        $sessionId = 'target-session-id';

        DB::table('notifications')->insert([
            'id' => $notificationId,
            'type' => 'TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $target->id,
            'data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
            'payload' => 'test-payload',
            'last_activity' => now()->timestamp,
        ]);
        $target->delete();

        $this->actingAsMfaVerified($this->admin)
            ->delete(route('admin.users.force-delete', $target->id))
            ->assertRedirect(route('admin.users.deleted'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseMissing('notifications', ['id' => $notificationId]);
        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
    }

    public function test_admin_cannot_move_their_own_account_to_recycle_bin(): void
    {
        $backupAdmin = User::factory()->create(['name' => 'Backup Admin']);
        $backupAdmin->assignRole('admin');

        $this->actingAsMfaVerified($this->admin)
            ->delete(route('admin.users.destroy', $this->admin))
            ->assertSessionHas('error', 'You cannot move your own account to the recycle bin.');

        $this->assertNotSoftDeleted('users', ['id' => $this->admin->id]);
    }

    public function test_final_active_admin_cannot_be_moved_to_recycle_bin(): void
    {
        $this->actingAsMfaVerified($this->admin)
            ->delete(route('admin.users.destroy', $this->admin))
            ->assertSessionHas('error', 'You cannot move the final active admin account to the recycle bin.');

        $this->assertNotSoftDeleted('users', ['id' => $this->admin->id]);
    }

    private function program(string $name, string $slug): Program
    {
        return Program::create(['name' => $name, 'slug' => $slug, 'is_active' => true]);
    }

    private function applicant(string $name, string $email, ?Program $program = null, ?string $phone = null): User
    {
        $user = User::factory()->create(['name' => $name, 'email' => $email]);
        $user->assignRole('job_seeker');
        JobSeeker::create([
            'user_id' => $user->id,
            'program_id' => $program?->id,
            'phone' => $phone,
        ]);

        return $user->load('jobSeeker');
    }

    private function job(): Job
    {
        $employerUser = User::factory()->create();
        $employerUser->assignRole('employer');
        $employer = Employer::create(['user_id' => $employerUser->id, 'company_name' => 'Test Sponsor']);

        return Job::create([
            'employer_id' => $employer->id,
            'title' => 'Test Opportunity',
            'description' => 'Test opportunity description.',
            'status' => Job::STATUS_PUBLISHED,
            'is_approved' => true,
        ]);
    }
}
