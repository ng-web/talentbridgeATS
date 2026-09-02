<?php

namespace Tests\Feature;

use App\Models\JobSeeker;
use App\Models\JobSeekerDocument;
use App\Models\PolicyAcknowledgement;
use App\Models\PolicyDocument;
use App\Models\PrivacyRequest;
use App\Models\Program;
use App\Models\SensitiveProcessingEvidence;
use App\Models\User;
use App\Services\Privacy\PolicyRegistryService;
use App\Services\Privacy\PrivacyRequestWorkflow;
use App\Support\PrivacySecurityPermissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class PrivacyPass2PolicyAndRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config([
            'privacy.identity_verification_methods' => ['controller_approved_process'],
            'privacy.sensitive_processing.purpose_codes' => ['controller_approved_collection'],
        ]);
    }

    public function test_policy_versions_are_immutable_and_activation_retires_the_prior_version(): void
    {
        $admin = $this->user('admin');
        $first = $this->policy($admin, PolicyDocument::TYPE_PRIVACY_NOTICE, '2026.1');
        $second = $this->policy($admin, PolicyDocument::TYPE_PRIVACY_NOTICE, '2026.2');

        $this->assertFalse($first->fresh()->is_active);
        $this->assertNotNull($first->fresh()->retired_at);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertSame($second->id, PolicyDocument::current(PolicyDocument::TYPE_PRIVACY_NOTICE)->id);

        $this->expectException(LogicException::class);
        $first->fresh()->update(['title' => 'Mutated historical title']);
    }

    public function test_future_policy_activation_is_rejected_without_creating_an_active_policy_gap(): void
    {
        $admin = $this->user('admin');
        $current = $this->policy($admin, PolicyDocument::TYPE_PRIVACY_NOTICE, '2026.1');
        $future = app(PolicyRegistryService::class)->createVersion($admin, [
            'type' => PolicyDocument::TYPE_PRIVACY_NOTICE,
            'version' => '2026.2',
            'title' => 'Future approved reference',
            'content_reference' => 'https://example.test/policies/privacy_notice/2026.2',
            'effective_at' => now()->addDay()->toDateTimeString(),
        ]);

        try {
            app(PolicyRegistryService::class)->activate($admin, $future);
            $this->fail('Future-effective activation should be rejected.');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertTrue($current->fresh()->is_active);
            $this->assertNull($current->fresh()->retired_at);
            $this->assertFalse($future->fresh()->is_active);
            $this->assertSame($current->id, PolicyDocument::current(PolicyDocument::TYPE_PRIVACY_NOTICE)->id);
        }
    }

    public function test_policy_hash_is_explicitly_a_canonical_reference_hash(): void
    {
        $admin = $this->user('admin');
        $policy = $this->policy($admin, PolicyDocument::TYPE_PRIVACY_NOTICE, '2026.1');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $policy->canonical_reference_hash);
        $this->assertArrayNotHasKey('content_hash', $policy->getAttributes());
    }

    public function test_existing_users_are_not_silently_backfilled_and_applicant_can_view_real_acknowledgement_history(): void
    {
        $admin = $this->user('admin');
        $applicant = $this->user('job_seeker');
        $policy = $this->policy($admin, PolicyDocument::TYPE_PRIVACY_NOTICE, '2026.1');

        $this->assertDatabaseCount('policy_acknowledgements', 0);
        $this->actingAs($applicant)->get(route('privacy.index'))
            ->assertOk()
            ->assertSee('No recorded acknowledgement');

        PolicyAcknowledgement::query()->create([
            'user_id' => $applicant->id,
            'policy_document_id' => $policy->id,
            'acknowledgement_type' => PolicyAcknowledgement::TYPE_NOTICE_ACKNOWLEDGED,
            'source_context' => 'registration',
            'acknowledged_at' => now(),
        ]);

        $this->actingAs($applicant)->get(route('privacy.index'))->assertOk()->assertSee('2026.1');
    }

    public function test_registration_records_the_exact_active_version_transactionally(): void
    {
        Mail::fake();
        config(['privacy.registration.evidence_enabled' => true, 'privacy.registration.require_privacy_notice' => true]);
        $admin = $this->user('admin');
        $policy = $this->policy($admin, PolicyDocument::TYPE_PRIVACY_NOTICE, '2026.1');
        $program = Program::query()->create(['name' => 'Pass 2 Program', 'slug' => 'pass-2-program', 'is_active' => true]);

        $this->post(route('register'), [
            'name' => 'New Applicant',
            'email' => 'new-pass2@example.test',
            'role' => 'job_seeker',
            'program' => $program->slug,
            'password' => 'password',
            'password_confirmation' => 'password',
            'policy_documents' => [PolicyDocument::TYPE_PRIVACY_NOTICE => $policy->id],
            'policy_acknowledgements' => [PolicyDocument::TYPE_PRIVACY_NOTICE => '1'],
        ])->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'new-pass2@example.test')->firstOrFail();
        $this->assertDatabaseHas('policy_acknowledgements', [
            'user_id' => $user->id,
            'policy_document_id' => $policy->id,
            'acknowledgement_type' => PolicyAcknowledgement::TYPE_NOTICE_ACKNOWLEDGED,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'policy_acknowledged', 'subject_user_id' => $user->id]);
    }

    public function test_registration_rejects_a_policy_version_that_became_stale_without_creating_an_account(): void
    {
        Mail::fake();
        config(['privacy.registration.evidence_enabled' => true, 'privacy.registration.require_privacy_notice' => true]);
        $admin = $this->user('admin');
        $stale = $this->policy($admin, PolicyDocument::TYPE_PRIVACY_NOTICE, '2026.1');
        $this->policy($admin, PolicyDocument::TYPE_PRIVACY_NOTICE, '2026.2');
        $program = Program::query()->create(['name' => 'Pass 2 Program', 'slug' => 'pass-2-program', 'is_active' => true]);

        $this->post(route('register'), [
            'name' => 'Stale Applicant',
            'email' => 'stale-policy@example.test',
            'role' => 'job_seeker',
            'program' => $program->slug,
            'password' => 'password',
            'password_confirmation' => 'password',
            'policy_documents' => [PolicyDocument::TYPE_PRIVACY_NOTICE => $stale->id],
            'policy_acknowledgements' => [PolicyDocument::TYPE_PRIVACY_NOTICE => '1'],
        ])->assertSessionHasErrors('policy_documents');

        $this->assertDatabaseMissing('users', ['email' => 'stale-policy@example.test']);
        $this->assertDatabaseCount('policy_acknowledgements', 0);
    }

    public function test_applicant_submission_is_uuid_scoped_append_only_and_has_no_destructive_effect(): void
    {
        $first = $this->user('job_seeker');
        $second = $this->user('job_seeker');

        $this->actingAs($first)->post(route('privacy.store'), ['request_type' => PrivacyRequest::TYPE_DELETION])->assertRedirect();
        $privacyRequest = PrivacyRequest::query()->firstOrFail();

        $this->assertTrue(\Illuminate\Support\Str::isUuid($privacyRequest->uuid));
        $this->assertNull($first->fresh()->deleted_at);
        $this->assertSame(PrivacyRequest::STATE_SUBMITTED, $privacyRequest->state);
        $this->assertDatabaseHas('privacy_request_events', ['privacy_request_id' => $privacyRequest->id, 'event' => 'submitted']);
        $this->actingAs($first)->get(route('privacy.show', $privacyRequest))->assertOk();
        $this->actingAs($second)->get(route('privacy.show', $privacyRequest))->assertNotFound();
        $this->actingAs($first)->get('/privacy/requests/1')->assertNotFound();
    }

    public function test_admin_privacy_permissions_are_default_deny_and_view_only_cannot_decide(): void
    {
        $applicant = $this->user('job_seeker');
        $privacyRequest = app(PrivacyRequestWorkflow::class)->submit($applicant, PrivacyRequest::TYPE_ACCESS);
        $admin = $this->enrollAdministratorMfa($this->user('admin'));

        $this->actingAsMfaVerified($admin)->get(route('admin.privacy-requests.index'))->assertForbidden();
        $admin->givePermissionTo(PrivacySecurityPermissions::PRIVACY_REQUESTS_VIEW);
        $this->actingAsMfaVerified($admin)->get(route('admin.privacy-requests.index'))->assertOk()->assertDontSee($applicant->email);
        $this->actingAsMfaVerified($admin)->post(route('admin.privacy-requests.decide', $privacyRequest), [
            'state' => PrivacyRequest::STATE_APPROVED,
            'controller_decision_code' => 'controller_approved',
        ])->assertForbidden();
    }

    public function test_controller_decision_requires_verification_allowed_state_permission_and_recent_password_confirmation(): void
    {
        $applicant = $this->user('job_seeker');
        $admin = $this->enrollAdministratorMfa($this->user('admin'));
        $admin->givePermissionTo([PrivacySecurityPermissions::PRIVACY_REQUESTS_VIEW, PrivacySecurityPermissions::PRIVACY_REQUESTS_MANAGE, PrivacySecurityPermissions::PRIVACY_REQUESTS_DECIDE]);
        $workflow = app(PrivacyRequestWorkflow::class);
        $privacyRequest = $workflow->submit($applicant, PrivacyRequest::TYPE_ACCESS);
        $privacyRequest = $workflow->transition($admin, $privacyRequest, PrivacyRequest::STATE_UNDER_REVIEW);
        $privacyRequest = $workflow->verifyIdentity($admin, $privacyRequest, 'controller_approved_process');
        $privacyRequest = $workflow->transition($admin, $privacyRequest, PrivacyRequest::STATE_DECISION_REQUIRED);

        $this->actingAsMfaVerified($admin, false)->post(route('admin.privacy-requests.decide', $privacyRequest), [
            'state' => PrivacyRequest::STATE_APPROVED,
            'controller_decision_code' => 'controller_approved',
        ])->assertRedirect(route('password.confirm'));

        $this->actingAsMfaVerified($admin)->post(route('admin.privacy-requests.decide', $privacyRequest), [
            'state' => PrivacyRequest::STATE_APPROVED,
            'controller_decision_code' => 'controller_approved',
        ])->assertRedirect();

        $this->assertSame(PrivacyRequest::STATE_APPROVED, $privacyRequest->fresh()->state);
        $this->assertDatabaseHas('audit_logs', ['action' => 'privacy_request_decision_recorded', 'subject_user_id' => $applicant->id]);
    }

    public function test_invalid_state_transition_and_unauthorized_identity_verification_are_rejected(): void
    {
        $applicant = $this->user('job_seeker');
        $admin = $this->enrollAdministratorMfa($this->user('admin'));
        $admin->givePermissionTo(PrivacySecurityPermissions::PRIVACY_REQUESTS_VIEW);
        $privacyRequest = app(PrivacyRequestWorkflow::class)->submit($applicant, PrivacyRequest::TYPE_ACCESS);

        $this->actingAsMfaVerified($admin)->post(route('admin.privacy-requests.verify-identity', $privacyRequest), [
            'method' => 'controller_approved_process',
        ])->assertForbidden();

        $admin->givePermissionTo(PrivacySecurityPermissions::PRIVACY_REQUESTS_MANAGE);
        $this->actingAsMfaVerified($admin)->post(route('admin.privacy-requests.transition', $privacyRequest), [
            'state' => PrivacyRequest::STATE_FULFILLED,
        ])->assertSessionHasErrors('state');
        $this->assertSame(PrivacyRequest::STATE_SUBMITTED, $privacyRequest->fresh()->state);
        $this->assertNull($privacyRequest->fresh()->identity_verification_method);
    }

    public function test_workflow_invariants_prevent_applicant_control_direct_verification_and_invalid_fulfilment(): void
    {
        $applicant = $this->user('job_seeker');
        $admin = $this->user('admin');
        $workflow = app(PrivacyRequestWorkflow::class);
        $request = $workflow->submit($applicant, PrivacyRequest::TYPE_ACCESS);

        foreach ([
            fn () => $workflow->transition($applicant, $request, PrivacyRequest::STATE_UNDER_REVIEW),
            fn () => $workflow->transition($admin, $request, PrivacyRequest::STATE_VERIFIED),
        ] as $operation) {
            try {
                $operation();
                $this->fail('The workflow invariant should reject this mutation.');
            } catch (\Illuminate\Validation\ValidationException) {
                $this->assertSame(PrivacyRequest::STATE_SUBMITTED, $request->fresh()->state);
            }
        }

        $request = $workflow->transition($admin, $request, PrivacyRequest::STATE_UNDER_REVIEW);
        $request = $workflow->verifyIdentity($admin, $request, 'controller_approved_process');
        $request = $workflow->transition($admin, $request, PrivacyRequest::STATE_DECISION_REQUIRED);
        $request = $workflow->transition($admin, $request, PrivacyRequest::STATE_APPROVED, 'controller_approved');
        $request->forceFill(['controller_decision_code' => null])->save();
        try {
            $workflow->transition($admin, $request, PrivacyRequest::STATE_FULFILLED);
            $this->fail('Fulfilment without an approved controller decision should be rejected.');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertSame(PrivacyRequest::STATE_APPROVED, $request->fresh()->state);
        }
    }

    public function test_assignment_is_workflow_owned_and_rejects_every_post_decision_or_terminal_state_without_events(): void
    {
        $actor = $this->user('admin');
        $assignee = $this->user('admin');
        $workflow = app(PrivacyRequestWorkflow::class);

        foreach (PrivacyRequest::STATES as $state) {
            $subject = $this->user('job_seeker');
            $request = $workflow->submit($subject, PrivacyRequest::TYPE_ACCESS);
            $request->forceFill(['state' => $state])->save();
            $eventCount = $request->events()->count();
            $auditCount = \App\Models\AuditLog::query()->where('entity_type', $request->getMorphClass())->where('entity_id', $request->id)->count();

            if (in_array($state, PrivacyRequestWorkflow::ASSIGNABLE_STATES, true)) {
                $assigned = $workflow->assign($actor, $request, $assignee);
                $this->assertSame($assignee->id, $assigned->assigned_admin_id, "{$state} should remain assignable.");
                $this->assertSame($eventCount + 1, $request->events()->count());

                continue;
            }

            try {
                $workflow->assign($actor, $request, $assignee);
                $this->fail("{$state} must reject assignment.");
            } catch (\Illuminate\Validation\ValidationException) {
                $this->assertNull($request->fresh()->assigned_admin_id);
                $this->assertSame($eventCount, $request->events()->count());
                $this->assertSame($auditCount, \App\Models\AuditLog::query()->where('entity_type', $request->getMorphClass())->where('entity_id', $request->id)->count());
            }
        }
    }

    public function test_controller_manager_bootstrap_is_explicit_idempotent_and_does_not_grant_support_or_generic_admins(): void
    {
        $manager = $this->user('admin');
        $support = $this->user('admin');

        foreach (PrivacySecurityPermissions::controllerManager() as $permission) {
            $this->assertTrue(Permission::query()->where('name', $permission)->exists());
            $this->assertFalse($manager->can($permission));
            $this->assertFalse($support->can($permission));
        }

        $this->artisan('privacy:grant-controller-manager', ['user_id' => $manager->id])->assertSuccessful();
        $auditCount = \App\Models\AuditLog::query()->where('action', 'admin_permission_changed')->count();
        $this->artisan('privacy:grant-controller-manager', ['user_id' => $manager->id])->assertSuccessful();

        foreach (PrivacySecurityPermissions::controllerManager() as $permission) {
            $this->assertTrue($manager->fresh()->hasDirectPermission($permission));
            $this->assertFalse($support->fresh()->can($permission));
        }
        $this->assertSame($auditCount, \App\Models\AuditLog::query()->where('action', 'admin_permission_changed')->count());
        $this->assertFalse($manager->fresh()->can(PrivacySecurityPermissions::ADMIN_SECURITY_MANAGE));
    }

    public function test_configured_high_risk_collection_requires_controlled_controller_evidence_without_storing_identity_values(): void
    {
        Storage::fake('private');
        config(['privacy.sensitive_processing.enforcement_enabled' => true]);
        $applicant = $this->user('job_seeker');
        $admin = $this->enrollAdministratorMfa($this->user('admin'));

        $this->actingAs($applicant)->post(route('jobseeker.documents.store'), [
            'document_type' => \App\Models\JobSeekerDocument::TYPE_PASSPORT,
            'file' => UploadedFile::fake()->create('passport.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('job_seeker_documents', 0);

        $admin->givePermissionTo(PrivacySecurityPermissions::PRIVACY_EVIDENCE_MANAGE);
        $this->actingAsMfaVerified($admin)->post(route('admin.privacy-evidence.store'), [
            'user_id' => $applicant->id,
            'category' => \App\Models\JobSeekerDocument::TYPE_PASSPORT,
            'purpose_code' => 'controller_approved_collection',
            'evidence_type' => 'controller_recorded',
            'controller_reference' => 'KAIROX-APPROVED-REF-1',
        ])->assertRedirect();

        $this->actingAs($applicant)->post(route('jobseeker.documents.store'), [
            'document_type' => \App\Models\JobSeekerDocument::TYPE_PASSPORT,
            'file' => UploadedFile::fake()->create('passport.pdf', 10, 'application/pdf'),
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('sensitive_processing_evidence', [
            'user_id' => $applicant->id,
            'category' => 'passport',
            'purpose_code' => 'controller_approved_collection',
            'source_context' => 'controller_admin',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sensitive_processing_evidence_recorded', 'subject_user_id' => $applicant->id]);
        $this->assertDatabaseCount('job_seeker_documents', 1);
    }

    public function test_sensitive_upload_requires_current_subject_purpose_and_evidence_rule(): void
    {
        Storage::fake('private');
        config(['privacy.sensitive_processing.enforcement_enabled' => true]);
        $applicant = $this->user('job_seeker');
        $other = $this->user('job_seeker');

        $cases = [
            ['user_id' => $applicant->id, 'purpose_code' => 'historical_other_purpose', 'withdrawn_at' => null],
            ['user_id' => $applicant->id, 'purpose_code' => 'controller_approved_collection', 'withdrawn_at' => now()],
            ['user_id' => $other->id, 'purpose_code' => 'controller_approved_collection', 'withdrawn_at' => null],
        ];
        foreach ($cases as $case) {
            SensitiveProcessingEvidence::query()->create([
                ...$case,
                'category' => JobSeekerDocument::TYPE_PASSPORT,
                'evidence_type' => 'controller_recorded',
                'recorded_at' => now(),
                'source_context' => 'controller_admin',
            ]);
            $this->actingAs($applicant)->post(route('jobseeker.documents.store'), [
                'document_type' => JobSeekerDocument::TYPE_PASSPORT,
                'file' => UploadedFile::fake()->create('passport.pdf', 10, 'application/pdf'),
            ])->assertSessionHasErrors('file');
            $this->assertDatabaseCount('job_seeker_documents', 0);
        }

        SensitiveProcessingEvidence::query()->create([
            'user_id' => $applicant->id,
            'category' => JobSeekerDocument::TYPE_PASSPORT,
            'purpose_code' => 'controller_approved_collection',
            'evidence_type' => 'controller_recorded',
            'recorded_at' => now(),
            'source_context' => 'controller_admin',
        ]);
        $this->actingAs($applicant)->post(route('jobseeker.documents.store'), [
            'document_type' => JobSeekerDocument::TYPE_PASSPORT,
            'file' => UploadedFile::fake()->create('passport.pdf', 10, 'application/pdf'),
        ])->assertSessionHas('success');
        $this->assertDatabaseCount('job_seeker_documents', 1);
    }

    public function test_sensitive_upload_rejects_stale_policy_evidence_when_current_policy_is_required(): void
    {
        Storage::fake('private');
        $admin = $this->user('admin');
        $applicant = $this->user('job_seeker');
        $stale = $this->policy($admin, PolicyDocument::TYPE_PRIVACY_NOTICE, '2026.1');
        $current = $this->policy($admin, PolicyDocument::TYPE_PRIVACY_NOTICE, '2026.2');
        config([
            'privacy.sensitive_processing.enforcement_enabled' => true,
            'privacy.sensitive_processing.require_current_policy' => true,
            'privacy.sensitive_processing.current_policy_type' => PolicyDocument::TYPE_PRIVACY_NOTICE,
        ]);

        foreach ([$stale, $current] as $policy) {
            SensitiveProcessingEvidence::query()->create([
                'user_id' => $applicant->id,
                'category' => JobSeekerDocument::TYPE_PASSPORT,
                'purpose_code' => 'controller_approved_collection',
                'policy_document_id' => $policy->id,
                'evidence_type' => 'controller_recorded',
                'recorded_at' => now(),
                'source_context' => 'controller_admin',
            ]);
            $response = $this->actingAs($applicant)->post(route('jobseeker.documents.store'), [
                'document_type' => JobSeekerDocument::TYPE_PASSPORT,
                'file' => UploadedFile::fake()->create('passport.pdf', 10, 'application/pdf'),
            ]);
            $policy->is($stale) ? $response->assertSessionHasErrors('file') : $response->assertSessionHas('success');
        }
        $this->assertDatabaseCount('job_seeker_documents', 1);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        if ($role === 'job_seeker') {
            JobSeeker::query()->create(['user_id' => $user->id]);
        }

        return $user;
    }

    private function policy(User $actor, string $type, string $version): PolicyDocument
    {
        $registry = app(PolicyRegistryService::class);
        $document = $registry->createVersion($actor, [
            'type' => $type,
            'version' => $version,
            'title' => 'Kairox-approved document reference',
            'content_reference' => 'https://example.test/policies/'.$type.'/'.$version,
            'effective_at' => now()->subMinute()->toDateTimeString(),
        ]);

        return $registry->activate($actor, $document);
    }
}
