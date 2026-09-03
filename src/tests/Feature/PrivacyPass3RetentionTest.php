<?php

namespace Tests\Feature;

use App\Jobs\DeleteUnreferencedApplicantDocument;
use App\Models\Application;
use App\Models\ApplicationFile;
use App\Models\DispositionPlan;
use App\Models\DispositionPlanItem;
use App\Models\Employer;
use App\Models\Job;
use App\Models\JobSeeker;
use App\Models\JobSeekerDocument;
use App\Models\LegalHold;
use App\Models\RetentionRule;
use App\Models\User;
use App\Services\Privacy\DispositionExecutorEvidence;
use App\Services\Privacy\DispositionService;
use App\Services\Privacy\LegalHoldService;
use App\Services\Privacy\RetentionDataCategories;
use App\Services\Privacy\RetentionEligibility;
use App\Services\Privacy\RetentionEligibilityService;
use App\Services\Privacy\RetentionRuleRegistryService;
use App\Services\Security\AdminSessionService;
use App\Support\PrivacySecurityPermissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

final class PrivacyPass3RetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('private');
        Storage::fake('public');
    }

    public function test_no_rule_inactive_rule_and_future_rule_never_make_a_document_eligible(): void
    {
        [$subject, $document] = $this->document();
        $service = app(RetentionEligibilityService::class);
        $this->assertSame(RetentionEligibility::NO_RULE, $service->evaluate(RetentionDataCategories::APPLICANT_DOCUMENT, $document)->status);

        $admin = $this->retentionAdmin();
        $draft = $this->draft($admin, 1, now()->subDay());
        $this->assertSame(RetentionEligibility::NO_RULE, $service->evaluate(RetentionDataCategories::APPLICANT_DOCUMENT, $document)->status);

        $future = $this->draft($admin, 2, now()->addDay());
        app(RetentionRuleRegistryService::class)->approve($admin, $future);
        $this->assertSame(RetentionEligibility::NO_RULE, $service->evaluate(RetentionDataCategories::APPLICANT_DOCUMENT, $document)->status);
        $this->assertNotNull($subject->id);
        $this->assertSame(RetentionRule::STATUS_DRAFT, $draft->status);
    }

    public function test_approved_history_is_immutable_and_future_replacement_preserves_current_governing_rule(): void
    {
        $admin = $this->retentionAdmin();
        $registry = app(RetentionRuleRegistryService::class);
        $first = $registry->approve($admin, $this->draft($admin, 1, now()->subDay()));
        $future = $registry->approve($admin, $this->draft($admin, 2, now()->addDay()));

        $this->assertSame($first->id, $registry->governing(RetentionDataCategories::APPLICANT_DOCUMENT)->id);
        $this->assertEquals($future->effective_at, $first->fresh()->retired_at);
        $this->expectException(LogicException::class);
        $first->fresh()->update(['retention_value' => 999]);
    }

    public function test_retiring_a_future_replacement_restores_predecessor_continuity(): void
    {
        $admin = $this->retentionAdmin();
        $registry = app(RetentionRuleRegistryService::class);
        $first = $registry->approve($admin, $this->draft($admin, 1, now()->subDay()));
        $future = $registry->approve($admin, $this->draft($admin, 2, now()->addDay()));
        $registry->retire($admin, $future);

        $this->assertNull($first->fresh()->retired_at);
        $this->assertSame($first->id, $registry->governing(RetentionDataCategories::APPLICANT_DOCUMENT)->id);
    }

    public function test_subject_category_and_resource_holds_block_and_release_restores_future_eligibility(): void
    {
        [$subject, $document] = $this->document();
        $admin = $this->retentionAdmin();
        $this->approve($admin);
        $holds = app(LegalHoldService::class);
        $eligibility = app(RetentionEligibilityService::class);

        foreach ([
            ['scope_type' => LegalHold::SCOPE_SUBJECT, 'data_category' => null, 'resource_id' => null],
            ['scope_type' => LegalHold::SCOPE_CATEGORY, 'data_category' => RetentionDataCategories::APPLICANT_DOCUMENT, 'resource_id' => null],
            ['scope_type' => LegalHold::SCOPE_RESOURCE, 'data_category' => RetentionDataCategories::APPLICANT_DOCUMENT, 'resource_id' => $document->id],
        ] as $scope) {
            $hold = $holds->issue($admin, [...$scope, 'subject_user_id' => $subject->id, 'hold_code' => 'controller_direction', 'effective_at' => now()->subMinute()]);
            $this->assertSame(RetentionEligibility::LEGAL_HOLD, $eligibility->evaluate(RetentionDataCategories::APPLICANT_DOCUMENT, $document)->status);
            $holds->release($admin, $hold);
            $this->assertSame(RetentionEligibility::ELIGIBLE, $eligibility->evaluate(RetentionDataCategories::APPLICANT_DOCUMENT, $document)->status);
        }
        $this->assertDatabaseCount('legal_holds', 3);
        $this->assertDatabaseHas('audit_logs', ['action' => 'legal_hold_released', 'subject_user_id' => $subject->id]);
    }

    public function test_unauthorized_hold_issuance_and_unsupported_resource_scope_fail_closed(): void
    {
        [$subject] = $this->document();
        $plainAdmin = $this->admin();
        $this->expectException(ValidationException::class);
        app(LegalHoldService::class)->issue($plainAdmin, [
            'subject_user_id' => $subject->id, 'scope_type' => LegalHold::SCOPE_SUBJECT,
            'data_category' => null, 'resource_id' => null, 'hold_code' => 'controller_direction', 'effective_at' => now(),
        ]);
    }

    public function test_dry_run_command_is_aggregate_only_and_never_creates_a_plan_or_mutates_data(): void
    {
        [, $document] = $this->document('private/applicant-documents/secret-passport.pdf');
        $admin = $this->retentionAdmin();
        $this->approve($admin);

        $this->artisan('privacy:retention-plan', ['category' => RetentionDataCategories::APPLICANT_DOCUMENT, '--user_id' => $admin->id])
            ->expectsOutputToContain('Technically eligible under configured rule: 1')
            ->expectsOutputToContain('DRY RUN')
            ->doesntExpectOutputToContain('secret-passport.pdf')
            ->assertSuccessful();
        $this->assertDatabaseCount('disposition_plans', 0);
        $this->assertDatabaseHas('job_seeker_documents', ['id' => $document->id]);
    }

    public function test_plan_binds_exact_rule_resource_and_snapshot_then_requires_explicit_authorization(): void
    {
        [, $document] = $this->document();
        $admin = $this->retentionAdmin();
        $rule = $this->approve($admin);
        $plan = app(DispositionService::class)->createPlan($admin, $rule, [$document]);

        $this->assertSame(DispositionPlan::STATUS_PLANNED, $plan->status);
        $this->assertSame($rule->id, $plan->retention_rule_id);
        $this->assertSame($rule->version, $plan->retention_rule_version);
        $this->assertSame($rule->canonical_configuration_hash, $plan->retention_rule_hash);
        $this->assertArrayNotHasKey('execution_token', $plan->toArray());
        $item = $plan->items->first();
        $this->assertSame($document->id, $item->resource_id);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $item->eligibility_snapshot_hash);
        $this->assertArrayNotHasKey('artifact_revision', $item->toArray());
        $this->assertArrayNotHasKey('artifact_fingerprint', $item->toArray());
        $this->expectException(ValidationException::class);
        app(DispositionService::class)->execute($this->evidence($admin), $plan);
    }

    public function test_hold_created_after_authorization_blocks_database_and_physical_file_disposition(): void
    {
        [$subject, $document] = $this->document();
        Storage::disk('private')->put($document->file_path, 'sensitive');
        $admin = $this->retentionAdmin();
        $rule = $this->approve($admin);
        $service = app(DispositionService::class);
        $plan = $service->authorize($admin, $service->createPlan($admin, $rule, [$document]));
        app(LegalHoldService::class)->issue($admin, [
            'subject_user_id' => $subject->id, 'scope_type' => LegalHold::SCOPE_SUBJECT,
            'data_category' => null, 'resource_id' => null, 'hold_code' => 'litigation_preservation', 'effective_at' => now()->subMinute(),
        ]);

        $result = $service->execute($this->evidence($admin), $plan);
        $this->assertSame(DispositionPlan::STATUS_REVIEW_REQUIRED, $result->status);
        $this->assertDatabaseHas('job_seeker_documents', ['id' => $document->id]);
        Storage::disk('private')->assertExists($document->file_path);
        $this->assertDatabaseHas('disposition_plan_items', ['disposition_plan_id' => $plan->id, 'status' => DispositionPlanItem::STATUS_SKIPPED, 'outcome_code' => RetentionEligibility::LEGAL_HOLD]);
    }

    public function test_active_hold_blocks_direct_document_deletion_and_queued_physical_cleanup(): void
    {
        [$subject, $document] = $this->document('private/applicant-documents/held.pdf');
        Storage::disk('private')->put($document->file_path, 'held');
        $admin = $this->retentionAdmin();
        app(LegalHoldService::class)->issue($admin, [
            'subject_user_id' => $subject->id, 'scope_type' => LegalHold::SCOPE_SUBJECT,
            'data_category' => null, 'resource_id' => null, 'hold_code' => 'controller_direction', 'effective_at' => now()->subMinute(),
        ]);

        try {
            DB::transaction(fn () => $document->delete());
            $this->fail('An ordinary deletion path must not bypass an active hold.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('job_seeker_documents', ['id' => $document->id]);
        }

        $this->expectException(\RuntimeException::class);
        try {
            (new DeleteUnreferencedApplicantDocument(
                $document->file_path,
                $subject->id,
                RetentionDataCategories::APPLICANT_DOCUMENT,
                $document->id,
                null,
                $document->artifact_revision,
                hash('sha256', $document->file_path),
            ))->handle(app(\App\Services\Documents\ApplicantDocumentLifecycle::class), app(LegalHoldService::class));
        } finally {
            Storage::disk('private')->assertExists($document->file_path);
        }
    }

    public function test_revoked_authorizer_permission_invalidates_authorization(): void
    {
        [, $document] = $this->document();
        $admin = $this->retentionAdmin();
        $service = app(DispositionService::class);
        $plan = $service->authorize($admin, $service->createPlan($admin, $this->approve($admin), [$document]));
        $admin->revokePermissionTo(PrivacySecurityPermissions::DISPOSITION_AUTHORIZE);

        $this->expectException(ValidationException::class);
        $service->execute($this->evidence($admin), $plan);
    }

    public function test_rule_retired_after_plan_makes_execution_stale_and_safe(): void
    {
        [, $document] = $this->document();
        $admin = $this->retentionAdmin();
        $registry = app(RetentionRuleRegistryService::class);
        $rule = $this->approve($admin);
        $service = app(DispositionService::class);
        $plan = $service->authorize($admin, $service->createPlan($admin, $rule, [$document]));
        $registry->retire($admin, $rule);

        $result = $service->execute($this->evidence($admin), $plan);
        $this->assertSame(DispositionPlan::STATUS_REVIEW_REQUIRED, $result->status);
        $this->assertDatabaseHas('job_seeker_documents', ['id' => $document->id]);
        $this->assertDatabaseHas('disposition_plan_items', ['disposition_plan_id' => $plan->id, 'outcome_code' => RetentionEligibility::NO_RULE]);
    }

    public function test_authorized_current_plan_disposes_reference_idempotently_without_auditing_deleted_values(): void
    {
        [$subject, $document] = $this->document('private/applicant-documents/top-secret.pdf');
        Storage::disk('private')->put($document->file_path, 'sensitive');
        $admin = $this->retentionAdmin();
        $service = app(DispositionService::class);
        $plan = $service->authorize($admin, $service->createPlan($admin, $this->approve($admin), [$document]));
        $result = $service->execute($this->evidence($admin), $plan);

        $this->assertSame(DispositionPlan::STATUS_COMPLETED, $result->status);
        $this->assertDatabaseMissing('job_seeker_documents', ['id' => $document->id]);
        $audit = \App\Models\AuditLog::query()->where('action', 'disposition_succeeded')->firstOrFail();
        $this->assertStringNotContainsString('top-secret.pdf', json_encode($audit->meta));
        $this->assertSame(DispositionPlan::STATUS_COMPLETED, $service->execute($this->evidence($admin), $result)->status);
        $item = $plan->items()->firstOrFail();
        (new DeleteUnreferencedApplicantDocument(
            $document->file_path,
            $subject->id,
            RetentionDataCategories::APPLICANT_DOCUMENT,
            $document->id,
            $item->id,
            $item->artifact_revision,
            $item->artifact_fingerprint,
        ))
            ->handle(app(\App\Services\Documents\ApplicantDocumentLifecycle::class), app(LegalHoldService::class));
        Storage::disk('private')->assertMissing($document->file_path);
        $this->assertSame(DispositionPlanItem::CLEANUP_COMPLETED, $item->fresh()->file_cleanup_status);
    }

    public function test_resource_change_after_planning_is_stale_and_audit_failure_rolls_back_disposition(): void
    {
        [, $changed] = $this->document('private/applicant-documents/changed.pdf');
        $admin = $this->retentionAdmin();
        $service = app(DispositionService::class);
        $rule = $this->approve($admin);
        $changedPlan = $service->authorize($admin, $service->createPlan($admin, $rule, [$changed]));
        $changed->forceFill(['created_at' => now()->subYears(3)])->save();
        $this->assertSame(DispositionPlan::STATUS_REVIEW_REQUIRED, $service->execute($this->evidence($admin), $changedPlan)->status);
        $this->assertDatabaseHas('job_seeker_documents', ['id' => $changed->id]);

        [, $rollback] = $this->document('private/applicant-documents/rollback.pdf');
        Storage::disk('private')->put($rollback->file_path, 'retained');
        $rollbackPlan = $service->authorize($admin, $service->createPlan($admin, $rule, [$rollback]));
        DB::unprepared("CREATE TRIGGER fail_disposition_audit BEFORE INSERT ON audit_logs WHEN NEW.action = 'disposition_succeeded' BEGIN SELECT RAISE(ABORT, 'synthetic audit failure'); END");
        $result = $service->execute($this->evidence($admin), $rollbackPlan);
        $this->assertSame(DispositionPlan::STATUS_REVIEW_REQUIRED, $result->status);
        $this->assertDatabaseHas('job_seeker_documents', ['id' => $rollback->id]);
        Storage::disk('private')->assertExists($rollback->file_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'disposition_failed', 'outcome' => 'failure']);
    }

    public function test_admin_ui_is_default_deny_and_requires_mfa_and_recent_password_confirmation(): void
    {
        $admin = $this->enrollAdministratorMfa($this->admin());
        $this->actingAsMfaVerified($admin)->get(route('admin.retention.index'))->assertForbidden();
        $admin->givePermissionTo(PrivacySecurityPermissions::RETENTION_VIEW);
        $this->actingAsMfaVerified($admin)->get(route('admin.retention.index'))->assertOk()->assertSee('NOT A LEGAL CONCLUSION');
        $admin->givePermissionTo(PrivacySecurityPermissions::RETENTION_MANAGE);
        $this->actingAsMfaVerified($admin, false)->withSession(['auth.password_confirmed_at' => 0])
            ->post(route('admin.retention.rules.store'), [])->assertRedirect(route('password.confirm'));
    }

    public function test_reconciliation_defaults_to_dry_run_and_only_repairs_stale_claim_state(): void
    {
        $admin = $this->retentionAdmin();
        [, $document] = $this->document();
        $plan = app(DispositionService::class)->authorize($admin, app(DispositionService::class)->createPlan($admin, $this->approve($admin), [$document]));
        $plan->forceFill(['status' => DispositionPlan::STATUS_EXECUTING, 'execution_token' => \Illuminate\Support\Str::uuid(), 'execution_started_at' => now()->subHour()])->save();
        $this->artisan('privacy:reconcile-disposition', ['--user_id' => $admin->id])->expectsOutputToContain('DRY RUN')->assertSuccessful();
        $this->assertSame(DispositionPlan::STATUS_EXECUTING, $plan->fresh()->status);
        $this->artisan('privacy:reconcile-disposition', ['--user_id' => $admin->id, '--execute' => true])->assertSuccessful();
        $this->assertSame(DispositionPlan::STATUS_REVIEW_REQUIRED, $plan->fresh()->status);
        $this->assertDatabaseHas('job_seeker_documents', ['id' => $document->id]);
    }

    public function test_replacement_after_authorization_changes_artifact_revision_and_old_plan_cannot_delete_it(): void
    {
        [$subject, $document] = $this->document('applicants/1/documents/passport/original.pdf');
        $document->forceFill(['document_type' => JobSeekerDocument::TYPE_PASSPORT])->save();
        Storage::disk('private')->put($document->file_path, 'artifact-a');
        $oldPath = $document->file_path;
        $oldRevision = $document->artifact_revision;
        $admin = $this->retentionAdmin();
        $service = app(DispositionService::class);
        $plan = $service->authorize($admin, $service->createPlan($admin, $this->approve($admin), [$document->fresh()]));
        Queue::fake();

        $this->actingAs($subject)->post(route('jobseeker.documents.store'), [
            'document_type' => JobSeekerDocument::TYPE_PASSPORT,
            'file' => UploadedFile::fake()->create('replacement.pdf', 20, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $replacement = JobSeekerDocument::query()->findOrFail($document->id);
        $this->assertNotSame($oldRevision, $replacement->artifact_revision);
        $this->assertNotSame($oldPath, $replacement->file_path);
        $replacementPath = $replacement->file_path;
        $result = $service->execute($this->evidence($admin), $plan);

        $this->assertSame(DispositionPlan::STATUS_REVIEW_REQUIRED, $result->status);
        $this->assertDatabaseHas('job_seeker_documents', ['id' => $replacement->id, 'artifact_revision' => $replacement->artifact_revision]);
        Storage::disk('private')->assertExists($replacementPath);
        Queue::assertPushed(DeleteUnreferencedApplicantDocument::class, 1);
        $this->assertStringNotContainsString($replacementPath, json_encode(\App\Models\AuditLog::query()->get()->pluck('meta')));
    }

    public function test_replacement_after_planning_invalidates_snapshot_before_authorization_execution(): void
    {
        [$subject, $document] = $this->document('applicants/1/documents/passport/planned.pdf');
        $document->forceFill(['document_type' => JobSeekerDocument::TYPE_PASSPORT])->save();
        Storage::disk('private')->put($document->file_path, 'planned-a');
        $admin = $this->retentionAdmin();
        $service = app(DispositionService::class);
        $plan = $service->createPlan($admin, $this->approve($admin), [$document->fresh()]);

        $this->actingAs($subject)->post(route('jobseeker.documents.store'), [
            'document_type' => JobSeekerDocument::TYPE_PASSPORT,
            'file' => UploadedFile::fake()->create('planned-b.pdf', 20, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $replacement = $document->fresh();
        $result = $service->execute($this->evidence($admin), $service->authorize($admin, $plan));

        $this->assertSame(DispositionPlan::STATUS_REVIEW_REQUIRED, $result->status);
        $this->assertDatabaseHas('job_seeker_documents', ['id' => $replacement->id, 'artifact_revision' => $replacement->artifact_revision]);
        Storage::disk('private')->assertExists($replacement->file_path);
    }

    public function test_stale_document_instance_cannot_delete_a_replacement_artifact(): void
    {
        [, $document] = $this->document('applicants/1/documents/passport/stale-a.pdf');
        $stale = JobSeekerDocument::query()->findOrFail($document->id);
        $replacementPath = 'applicants/1/documents/passport/stale-b.pdf';
        DB::transaction(fn () => JobSeekerDocument::query()->findOrFail($document->id)->update(['file_path' => $replacementPath]));

        $this->expectException(ValidationException::class);
        try {
            DB::transaction(fn () => $stale->delete());
        } finally {
            $this->assertDatabaseHas('job_seeker_documents', ['id' => $document->id, 'file_path' => $replacementPath]);
        }
    }

    public function test_old_cleanup_identity_deletes_only_obsolete_artifact_after_replacement(): void
    {
        [$subject, $document] = $this->document('applicants/1/documents/passport/cleanup-a.pdf');
        Storage::disk('private')->put($document->file_path, 'artifact-a');
        $oldPath = $document->file_path;
        $oldRevision = $document->artifact_revision;
        $newPath = 'applicants/1/documents/passport/cleanup-b.pdf';
        DB::transaction(fn () => $document->update(['file_path' => $newPath, 'uploaded_at' => now()]));
        Storage::disk('private')->put($newPath, 'artifact-b');

        (new DeleteUnreferencedApplicantDocument(
            $oldPath,
            $subject->id,
            RetentionDataCategories::APPLICANT_DOCUMENT,
            $document->id,
            null,
            $oldRevision,
            hash('sha256', $oldPath),
        ))->handle(app(\App\Services\Documents\ApplicantDocumentLifecycle::class), app(LegalHoldService::class));

        Storage::disk('private')->assertMissing($oldPath);
        Storage::disk('private')->assertExists($newPath);
        $this->assertDatabaseHas('job_seeker_documents', ['id' => $document->id, 'file_path' => $newPath]);
    }

    public function test_subject_and_profile_category_holds_block_resume_replacement_and_clear(): void
    {
        foreach ([LegalHold::SCOPE_SUBJECT, LegalHold::SCOPE_CATEGORY] as $scope) {
            [$subject] = $this->document();
            $profile = $subject->jobSeeker;
            $oldPath = 'applicants/'.$profile->id.'/profile/resume/held-'.$scope.'.pdf';
            $profile->update(['resume_path' => $oldPath]);
            Storage::disk('private')->put($oldPath, 'held');
            $admin = $this->retentionAdmin();
            $hold = app(LegalHoldService::class)->issue($admin, [
                'subject_user_id' => $subject->id,
                'scope_type' => $scope,
                'data_category' => $scope === LegalHold::SCOPE_CATEGORY ? RetentionDataCategories::APPLICANT_PROFILE : null,
                'resource_id' => null,
                'hold_code' => 'controller_direction',
                'effective_at' => now()->subMinute(),
            ]);

            $this->actingAs($subject)->post(route('jobseeker.profile.resume.upload'), [
                'resume' => UploadedFile::fake()->create('blocked.pdf', 20, 'application/pdf'),
            ])->assertSessionHas('error');
            $this->assertSame($oldPath, $profile->fresh()->resume_path);
            Storage::disk('private')->assertExists($oldPath);

            $this->actingAs($subject)->delete(route('jobseeker.profile.resume.clear'))->assertSessionHas('error');
            $this->assertSame($oldPath, $profile->fresh()->resume_path);
            Storage::disk('private')->assertExists($oldPath);
            app(LegalHoldService::class)->release($admin, $hold);
        }
    }

    public function test_released_and_unrelated_holds_allow_resume_replacement_with_hold_aware_cleanup_context(): void
    {
        [$subject] = $this->document();
        [$other] = $this->document();
        $profile = $subject->jobSeeker;
        $oldPath = 'applicants/'.$profile->id.'/profile/resume/released.pdf';
        $profile->update(['resume_path' => $oldPath]);
        Storage::disk('private')->put($oldPath, 'old');
        $admin = $this->retentionAdmin();
        $holds = app(LegalHoldService::class);
        $released = $holds->issue($admin, [
            'subject_user_id' => $subject->id, 'scope_type' => LegalHold::SCOPE_SUBJECT,
            'data_category' => null, 'resource_id' => null, 'hold_code' => 'controller_direction', 'effective_at' => now()->subMinute(),
        ]);
        $holds->release($admin, $released);
        $holds->issue($admin, [
            'subject_user_id' => $other->id, 'scope_type' => LegalHold::SCOPE_SUBJECT,
            'data_category' => null, 'resource_id' => null, 'hold_code' => 'controller_direction', 'effective_at' => now()->subMinute(),
        ]);
        Queue::fake();

        $this->actingAs($subject)->post(route('jobseeker.profile.resume.upload'), [
            'resume' => UploadedFile::fake()->create('allowed.pdf', 20, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertNotSame($oldPath, $profile->fresh()->resume_path);
        Queue::assertPushed(DeleteUnreferencedApplicantDocument::class, fn ($job): bool => $job->path === $oldPath
            && $job->subjectUserId === $subject->id
            && $job->dataCategory === RetentionDataCategories::APPLICANT_PROFILE
            && $job->resourceId === $profile->id
            && $job->artifactFingerprint === hash('sha256', $oldPath));
    }

    public function test_historical_rule_version_cannot_regress_after_retirement(): void
    {
        $admin = $this->retentionAdmin();
        $registry = app(RetentionRuleRegistryService::class);
        $v5 = $registry->approve($admin, $this->draft($admin, 5, now()->subDay()));
        $registry->retire($admin, $v5);

        $this->expectException(ValidationException::class);
        $registry->approve($admin, $this->draft($admin, 2, now()->addDay()));
    }

    public function test_security_version_suspension_and_executor_revocation_each_prevent_destruction(): void
    {
        [, $versionDocument] = $this->document();
        $authorizer = $this->retentionAdmin();
        $executor = $this->retentionAdmin();
        $service = app(DispositionService::class);
        $rule = $this->approve($authorizer);
        $versionPlan = $service->authorize($authorizer, $service->createPlan($authorizer, $rule, [$versionDocument]));
        app(AdminSessionService::class)->invalidateAll($authorizer);
        $this->expectValidationFailureWithoutDeletion(fn () => $service->execute($this->evidence($executor), $versionPlan), $versionDocument);

        [, $suspendedDocument] = $this->document();
        $authorizer2 = $this->retentionAdmin();
        $rule2 = app(RetentionRuleRegistryService::class)->governing(RetentionDataCategories::APPLICANT_DOCUMENT);
        $suspendedPlan = $service->authorize($authorizer2, $service->createPlan($authorizer2, $rule2, [$suspendedDocument]));
        $authorizer2->delete();
        $this->expectValidationFailureWithoutDeletion(fn () => $service->execute($this->evidence($executor), $suspendedPlan), $suspendedDocument);

        [, $executorDocument] = $this->document();
        $authorizer3 = $this->retentionAdmin();
        $executor3 = $this->retentionAdmin();
        $executorPlan = $service->authorize($authorizer3, $service->createPlan($authorizer3, $rule2, [$executorDocument]));
        $executor3->revokePermissionTo(PrivacySecurityPermissions::DISPOSITION_EXECUTE);
        $this->expectValidationFailureWithoutDeletion(fn () => $service->execute($this->evidence($executor3), $executorPlan), $executorDocument);

        [, $suspendedExecutorDocument] = $this->document();
        $authorizer4 = $this->retentionAdmin();
        $executor4 = $this->retentionAdmin();
        $suspendedExecutorPlan = $service->authorize($authorizer4, $service->createPlan($authorizer4, $rule2, [$suspendedExecutorDocument]));
        $executor4->delete();
        $this->expectValidationFailureWithoutDeletion(fn () => $service->execute($this->evidence($executor4), $suspendedExecutorPlan), $suspendedExecutorDocument);
    }

    public function test_initiating_executor_version_is_immutable_and_reset_first_prevents_destruction(): void
    {
        [, $document] = $this->document();
        $authorizer = $this->retentionAdmin();
        $executor = $this->retentionAdmin();
        $service = app(DispositionService::class);
        $plan = $service->authorize($authorizer, $service->createPlan($authorizer, $this->approve($authorizer), [$document]));
        $evidence = $this->evidence($executor);
        Queue::fake();

        app(AdminSessionService::class)->invalidateAll($executor);

        $this->expectValidationFailureWithoutDeletion(fn () => $service->execute($evidence, $plan), $document);
        Queue::assertNothingPushed();
    }

    public function test_pass3_http_and_ui_authority_is_direct_user_only(): void
    {
        $admin = $this->enrollAdministratorMfa($this->admin());
        $admin->roles->firstOrFail()->givePermissionTo(PrivacySecurityPermissions::RETENTION_VIEW);

        $this->actingAsMfaVerified($admin)
            ->get(route('admin.retention.index'))
            ->assertForbidden();
        $this->actingAsMfaVerified($admin)
            ->get(route('admin.dashboard'))
            ->assertDontSee('Retention, Holds & Disposition');

        $admin->givePermissionTo(PrivacySecurityPermissions::RETENTION_VIEW);
        $this->actingAsMfaVerified($admin)
            ->get(route('admin.retention.index'))
            ->assertOk();
    }

    public function test_http_execution_uses_authenticated_session_version_evidence(): void
    {
        [, $document] = $this->document();
        $admin = $this->enrollAdministratorMfa($this->retentionAdmin());
        $service = app(DispositionService::class);
        $plan = $service->authorize($admin, $service->createPlan($admin, $this->approve($admin), [$document]));
        Queue::fake();

        $this->actingAsMfaVerified($admin)
            ->post(route('admin.retention.plans.execute', $plan))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('job_seeker_documents', ['id' => $document->id]);
    }

    public function test_missing_cleanup_context_fails_closed(): void
    {
        $path = 'applicants/unbound.pdf';
        Storage::disk('private')->put($path, 'retained');

        try {
            (new DeleteUnreferencedApplicantDocument($path))->handle(app(\App\Services\Documents\ApplicantDocumentLifecycle::class));
            $this->fail('Context-free applicant cleanup must fail closed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Applicant document cleanup identity is invalid.', $exception->getMessage());
        }

        Storage::disk('private')->assertExists($path);
    }

    public function test_application_file_application_and_job_seeker_cleanup_dispatch_complete_context(): void
    {
        Queue::fake();
        [$subject, $document] = $this->document('applicants/context/document.pdf');
        $profile = $subject->jobSeeker;
        $profile->update([
            'resume_path' => 'applicants/context/profile-resume.pdf',
            'cover_letter_path' => 'applicants/context/profile-cover-letter.pdf',
        ]);
        $application = $this->application($profile, 'applicants/context/application-resume.pdf');
        $file = ApplicationFile::query()->create([
            'application_id' => $application->id,
            'document_type' => 'supporting_document',
            'file_path' => 'applicants/context/application-file.pdf',
            'original_name' => 'supporting.pdf',
        ]);

        DB::transaction(fn () => $file->delete());
        Queue::assertPushed(DeleteUnreferencedApplicantDocument::class, fn ($job): bool => $job->subjectUserId === $subject->id
            && $job->dataCategory === RetentionDataCategories::APPLICATION_FILE
            && $job->resourceId === $file->id
            && $job->artifactFingerprint === hash('sha256', $file->file_path));

        Queue::fake();
        $application->setRelation('files', collect());
        DB::transaction(fn () => $application->delete());
        Queue::assertPushed(DeleteUnreferencedApplicantDocument::class, fn ($job): bool => $job->subjectUserId === $subject->id
            && $job->dataCategory === RetentionDataCategories::APPLICATION
            && $job->resourceId === $application->id);

        Queue::fake();
        DB::transaction(fn () => $profile->delete());
        Queue::assertPushed(DeleteUnreferencedApplicantDocument::class, fn ($job): bool => $job->subjectUserId === $subject->id
            && $job->dataCategory === RetentionDataCategories::APPLICANT_PROFILE
            && $job->resourceId === $profile->id);
        Queue::assertPushed(DeleteUnreferencedApplicantDocument::class, fn ($job): bool => $job->subjectUserId === $subject->id
            && $job->dataCategory === RetentionDataCategories::APPLICANT_DOCUMENT
            && $job->resourceId === $document->id
            && $job->artifactRevision === $document->artifact_revision);
    }

    public function test_cleanup_current_checks_hold_after_logical_source_is_deleted(): void
    {
        Queue::fake();
        [$subject] = $this->document();
        $application = $this->application($subject->jobSeeker);
        $file = ApplicationFile::query()->create([
            'application_id' => $application->id,
            'document_type' => 'supporting_document',
            'file_path' => 'applicants/context/held-after-delete.pdf',
            'original_name' => 'held.pdf',
        ]);
        Storage::disk('private')->put($file->file_path, 'held');
        DB::transaction(fn () => $file->delete());
        $job = null;
        Queue::assertPushed(DeleteUnreferencedApplicantDocument::class, function ($queued) use (&$job): bool {
            $job = $queued;

            return true;
        });
        $admin = $this->retentionAdmin();
        $hold = app(LegalHoldService::class)->issue($admin, [
            'subject_user_id' => $subject->id,
            'scope_type' => LegalHold::SCOPE_CATEGORY,
            'data_category' => RetentionDataCategories::APPLICATION_FILE,
            'resource_id' => null,
            'hold_code' => 'controller_direction',
            'effective_at' => now()->subMinute(),
        ]);

        try {
            $job->handle(app(\App\Services\Documents\ApplicantDocumentLifecycle::class), app(LegalHoldService::class));
            $this->fail('A hold committed after logical deletion must block physical cleanup.');
        } catch (\RuntimeException) {
            Storage::disk('private')->assertExists($file->file_path);
        }

        app(LegalHoldService::class)->release($admin, $hold);
        $job->handle(app(\App\Services\Documents\ApplicantDocumentLifecycle::class), app(LegalHoldService::class));
        Storage::disk('private')->assertMissing($file->file_path);
    }

    public function test_legacy_migration_preserves_old_location_while_hold_is_active(): void
    {
        $subject = User::factory()->create();
        $subject->assignRole('job_seeker');
        $profile = JobSeeker::query()->create(['user_id' => $subject->id]);
        $source = 'jobseekers/resumes/held-migration.pdf';
        $profile->update(['resume_path' => $source]);
        Storage::disk('public')->put($source, 'held migration');
        $admin = $this->retentionAdmin();
        $hold = app(LegalHoldService::class)->issue($admin, [
            'subject_user_id' => $subject->id,
            'scope_type' => LegalHold::SCOPE_CATEGORY,
            'data_category' => RetentionDataCategories::APPLICANT_PROFILE,
            'resource_id' => null,
            'hold_code' => 'controller_direction',
            'effective_at' => now()->subMinute(),
        ]);

        $this->artisan('kairox:migrate-private-documents', ['--execute' => true, '--user' => $subject->id])->assertSuccessful();
        $this->assertSame($source, $profile->fresh()->resume_path);
        Storage::disk('public')->assertExists($source);
        $this->assertNotEmpty(Storage::disk('private')->allFiles());

        app(LegalHoldService::class)->release($admin, $hold);
        $this->artisan('kairox:migrate-private-documents', ['--execute' => true, '--user' => $subject->id])->assertSuccessful();
        $this->assertStringStartsWith('applicants/', $profile->fresh()->resume_path);
        Storage::disk('public')->assertMissing($source);
    }

    public function test_legacy_document_migration_rotates_revision_and_queues_exact_old_identity(): void
    {
        $source = 'jobseekers/documents/legacy-profile-photo.png';
        [$subject, $document] = $this->document($source);
        $document->forceFill(['document_type' => JobSeekerDocument::TYPE_PROFILE_PHOTO])->save();
        $oldRevision = $document->artifact_revision;
        Storage::disk('public')->put($source, 'legacy profile photo');
        Queue::fake();

        $this->artisan('kairox:migrate-private-documents', ['--execute' => true, '--user' => $subject->id])->assertSuccessful();

        $current = $document->fresh();
        $this->assertNotSame($source, $current->file_path);
        $this->assertNotSame($oldRevision, $current->artifact_revision);
        Queue::assertPushed(DeleteUnreferencedApplicantDocument::class, fn ($job): bool => $job->path === $source
            && $job->subjectUserId === $subject->id
            && $job->dataCategory === RetentionDataCategories::APPLICANT_DOCUMENT
            && $job->resourceId === $document->id
            && $job->artifactRevision === $oldRevision
            && $job->artifactFingerprint === hash('sha256', $source));
    }

    public function test_disposition_persists_encrypted_cleanup_identity_and_reconciliation_redispatches_it(): void
    {
        [$subject, $document] = $this->document('applicants/1/documents/certificate/durable.pdf');
        Storage::disk('private')->put($document->file_path, 'durable');
        $path = $document->file_path;
        $admin = $this->retentionAdmin();
        $service = app(DispositionService::class);
        Queue::fake();
        $plan = $service->authorize($admin, $service->createPlan($admin, $this->approve($admin), [$document]));
        $service->execute($this->evidence($admin), $plan);
        $item = $plan->items()->firstOrFail()->fresh();

        $this->assertSame($path, $item->cleanup_path);
        $raw = DB::table('disposition_plan_items')->where('id', $item->id)->value('cleanup_path');
        $this->assertIsString($raw);
        $this->assertStringNotContainsString($path, $raw);
        Queue::fake();
        $this->artisan('privacy:reconcile-disposition', ['--user_id' => $admin->id, '--execute' => true])
            ->doesntExpectOutputToContain($path)
            ->assertSuccessful();
        Queue::assertPushed(DeleteUnreferencedApplicantDocument::class, fn ($job): bool => $job->planItemId === $item->id
            && $job->subjectUserId === $subject->id
            && $job->artifactFingerprint === hash('sha256', $path));

        $item->forceFill(['file_cleanup_status' => DispositionPlanItem::CLEANUP_FAILED])->save();
        app(RetentionRuleRegistryService::class)->retire($admin, $plan->rule);
        Queue::fake();
        $this->artisan('privacy:reconcile-disposition', ['--user_id' => $admin->id, '--execute' => true])->assertSuccessful();
        Queue::assertNotPushed(DeleteUnreferencedApplicantDocument::class);
    }

    private function expectValidationFailureWithoutDeletion(callable $operation, JobSeekerDocument $document): void
    {
        try {
            $operation();
            $this->fail('Stale authority must prevent execution.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('job_seeker_documents', ['id' => $document->id]);
        }
    }

    private function evidence(User $user): DispositionExecutorEvidence
    {
        $current = $user->fresh() ?? $user;

        return new DispositionExecutorEvidence((int) $current->id, (int) $current->security_version);
    }

    private function application(JobSeeker $jobSeeker, ?string $resumePath = null): Application
    {
        $employerUser = User::factory()->create();
        $employerUser->assignRole('employer');
        $employer = Employer::query()->create(['user_id' => $employerUser->id, 'company_name' => 'Cleanup Context Employer']);
        $job = Job::query()->create([
            'employer_id' => $employer->id,
            'title' => 'Cleanup Context Role',
            'description' => 'Synthetic test opportunity.',
            'status' => Job::STATUS_PUBLISHED,
            'is_approved' => true,
            'application_deadline' => now()->addMonth(),
        ]);

        return Application::query()->create([
            'job_id' => $job->id,
            'job_seeker_id' => $jobSeeker->id,
            'status' => Application::STATUS_APPLIED,
            'applied_at' => now(),
            'submitted_resume_path' => $resumePath,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function retentionAdmin(): User
    {
        $user = $this->admin();
        $user->givePermissionTo(PrivacySecurityPermissions::retentionManager());

        return $user;
    }

    /** @return array{User, JobSeekerDocument} */
    private function document(string $path = 'private/applicant-documents/document.pdf'): array
    {
        $subject = User::factory()->create();
        $subject->assignRole('job_seeker');
        $profile = JobSeeker::query()->create(['user_id' => $subject->id]);
        $document = JobSeekerDocument::query()->create(['job_seeker_id' => $profile->id, 'document_type' => JobSeekerDocument::TYPE_CERTIFICATE, 'file_path' => $path, 'uploaded_at' => now()->subYears(2)]);
        $document->forceFill(['created_at' => now()->subYears(2), 'updated_at' => now()->subYears(2)])->save();

        return [$subject, $document->fresh()];
    }

    private function draft(User $admin, int $version, mixed $effectiveAt): RetentionRule
    {
        return app(RetentionRuleRegistryService::class)->createDraft($admin, [
            'data_category' => RetentionDataCategories::APPLICANT_DOCUMENT,
            'version' => $version,
            'trigger_type' => RetentionDataCategories::TRIGGER_RECORD_CREATED,
            'retention_value' => 1,
            'retention_unit' => RetentionRule::UNIT_DAYS,
            'disposition_method' => RetentionDataCategories::METHOD_DETACH_AND_DELETE_FILE,
            'effective_at' => $effectiveAt,
        ]);
    }

    private function approve(User $admin): RetentionRule
    {
        return app(RetentionRuleRegistryService::class)->approve($admin, $this->draft($admin, 1, now()->subDay()));
    }
}
