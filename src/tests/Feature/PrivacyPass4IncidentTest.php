<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\LegalHold;
use App\Models\PrivacyIncident;
use App\Models\PrivacyIncidentEvent;
use App\Models\User;
use App\Services\Privacy\IncidentActorEvidence;
use App\Services\Privacy\IncidentService;
use App\Services\Privacy\RetentionDataCategories;
use App\Support\PrivacySecurityPermissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

final class PrivacyPass4IncidentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_create_uses_minimized_versioned_evidence_and_semantic_audit(): void
    {
        $admin = $this->incidentAdmin();
        $incident = $this->create($admin);

        $this->assertSame('open', $incident->status);
        $this->assertSame('high', $incident->technical_severity);
        $this->assertSame('not_escalated', $incident->escalation_status);
        $this->assertNull($incident->latestDecision());
        $this->assertSame([RetentionDataCategories::APPLICANT_DOCUMENT], $incident->latestScope()->data_categories);
        $this->assertSame(12, $incident->latestScope()->potentially_affected_subject_count);
        $this->assertDatabaseHas('privacy_incident_events', ['privacy_incident_id' => $incident->id, 'event_type' => 'incident_created']);
        $audit = AuditLog::query()->where('action', 'incident.created')->firstOrFail();
        $this->assertStringNotContainsString('Minimized', json_encode($audit->meta));
        $this->assertStringNotContainsString('Minimized technical observation', DB::table('privacy_incidents')->where('id', $incident->id)->value('technical_summary'));
        $newOwner = $this->incidentAdmin();
        app(IncidentService::class)->assign($this->evidence($admin), $incident, $newOwner->id, (string) Str::uuid());
        $this->assertSame($newOwner->id, $incident->fresh()->owner_user_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'incident.assigned']);
    }

    public function test_http_subject_count_is_strictly_normalized_for_create_and_scope_updates(): void
    {
        $admin = $this->enrollAdministratorMfa($this->incidentAdmin());
        $this->actingAsMfaVerified($admin);

        $key = (string) Str::uuid();
        $this->post(route('admin.incidents.store'), $this->httpCreateValues($admin, $key, '12'))->assertRedirect();
        $incident = PrivacyIncident::query()->where('creation_key', $key)->firstOrFail();
        $this->assertSame(12, $incident->latestScope()->potentially_affected_subject_count);

        $omittedKey = (string) Str::uuid();
        $this->post(route('admin.incidents.store'), $this->httpCreateValues($admin, $omittedKey))->assertRedirect();
        $this->assertNull(PrivacyIncident::query()->where('creation_key', $omittedKey)->firstOrFail()->latestScope()->potentially_affected_subject_count);

        $this->post(route('admin.incidents.scope', $incident), [
            'idempotency_key' => (string) Str::uuid(),
            'data_categories' => [],
            'system_codes' => [],
            'potentially_affected_subject_count' => '0',
        ])->assertRedirect();
        $this->assertSame(0, $incident->scopeVersions()->reorder()->orderByDesc('version')->firstOrFail()->potentially_affected_subject_count);

        $this->post(route('admin.incidents.scope', $incident), [
            'idempotency_key' => (string) Str::uuid(),
            'data_categories' => [],
            'system_codes' => [],
            'potentially_affected_subject_count' => '12',
        ])->assertRedirect();
        $this->assertSame(12, $incident->scopeVersions()->reorder()->orderByDesc('version')->firstOrFail()->potentially_affected_subject_count);

        $this->post(route('admin.incidents.scope', $incident), [
            'idempotency_key' => (string) Str::uuid(),
            'data_categories' => [],
            'system_codes' => [],
        ])->assertRedirect();
        $this->assertNull($incident->scopeVersions()->reorder()->orderByDesc('version')->firstOrFail()->potentially_affected_subject_count);
    }

    public function test_http_subject_count_rejects_negative_decimal_alpha_and_overflow_values(): void
    {
        $admin = $this->enrollAdministratorMfa($this->incidentAdmin());
        $this->actingAsMfaVerified($admin);

        foreach (['-1', '1.5', 'twelve', '4294967296', '12abc'] as $invalid) {
            $this->post(route('admin.incidents.store'), $this->httpCreateValues($admin, (string) Str::uuid(), $invalid))
                ->assertSessionHasErrors('potentially_affected_subject_count');
        }

        $this->assertDatabaseCount('privacy_incidents', 0);

        $incident = $this->create($admin);
        foreach (['-1', '1.5', 'twelve', '4294967296', '12abc'] as $invalid) {
            $this->post(route('admin.incidents.scope', $incident), [
                'idempotency_key' => (string) Str::uuid(),
                'data_categories' => [],
                'system_codes' => [],
                'potentially_affected_subject_count' => $invalid,
            ])->assertSessionHasErrors('potentially_affected_subject_count');
        }
        $this->assertSame(1, $incident->scopeVersions()->count());
    }

    public function test_state_machine_validates_transitions_and_retry_is_idempotent(): void
    {
        $admin = $this->incidentAdmin();
        $incident = $this->create($admin);
        $service = app(IncidentService::class);
        $key = (string) Str::uuid();
        $service->transition($this->evidence($admin), $incident, 'triage', 'triage_review', 'performed', $key);
        $service->transition($this->evidence($admin), $incident, 'triage', 'triage_review', 'performed', $key);
        $this->assertSame(1, PrivacyIncidentEvent::query()->where('idempotency_key', $key)->count());

        $this->expectException(ValidationException::class);
        $service->transition($this->evidence($admin), $incident->fresh(), 'resolved', 'recovery_verified', 'verified', (string) Str::uuid());
    }

    public function test_transition_evidence_compatibility_rejects_contradictions_atomically_and_preserves_close_prerequisites(): void
    {
        $admin = $this->incidentAdmin();
        $incident = $this->create($admin);
        $service = app(IncidentService::class);
        $service->transition($this->evidence($admin), $incident, 'triage', 'triage_review', 'requested', (string) Str::uuid());
        $service->transition($this->evidence($admin), $incident, 'investigating', 'investigation_update', 'performed', (string) Str::uuid());

        $failedContainment = (string) Str::uuid();
        $this->assertValidationRejected(fn () => $service->transition($this->evidence($admin), $incident, 'contained', 'access_restricted', 'failed', $failedContainment));
        $this->assertSame('investigating', $incident->fresh()->status);
        $this->assertNull($incident->fresh()->contained_at);
        $this->assertDatabaseMissing('privacy_incident_events', ['idempotency_key' => $failedContainment]);

        $service->transition($this->evidence($admin), $incident, 'contained', 'access_restricted', 'performed', (string) Str::uuid());
        $requestedRecovery = (string) Str::uuid();
        $this->assertValidationRejected(fn () => $service->transition($this->evidence($admin), $incident, 'recovering', 'configuration_corrected', 'requested', $requestedRecovery));
        $this->assertSame('contained', $incident->fresh()->status);
        $this->assertNull($incident->fresh()->recovery_started_at);

        $service->transition($this->evidence($admin), $incident, 'recovering', 'configuration_corrected', 'verified', (string) Str::uuid());
        foreach (['failed', 'requested'] as $result) {
            $key = (string) Str::uuid();
            $stateAuditCount = AuditLog::query()->where('action', 'incident.state_changed')->count();
            $this->assertValidationRejected(fn () => $service->transition($this->evidence($admin), $incident, 'resolved', 'recovery_verified', $result, $key));
            $this->assertSame('recovering', $incident->fresh()->status);
            $this->assertNull($incident->fresh()->recovered_at);
            $this->assertNull($incident->fresh()->resolved_at);
            $this->assertDatabaseMissing('privacy_incident_events', ['idempotency_key' => $key]);
            $this->assertSame($stateAuditCount, AuditLog::query()->where('action', 'incident.state_changed')->count());
        }

        $service->recordDecision($this->evidence($admin), $incident, $this->decision('pending'), (string) Str::uuid());
        $closeKey = (string) Str::uuid();
        $this->assertValidationRejected(fn () => $service->close($this->evidence($admin), $incident, $closeKey));
        $this->assertDatabaseMissing('privacy_incident_events', ['idempotency_key' => $closeKey]);

        $service->transition($this->evidence($admin), $incident, 'resolved', 'recovery_verified', 'verified', (string) Str::uuid());
        $service->close($this->evidence($admin), $incident, (string) Str::uuid());
        $this->assertSame('closed', $incident->fresh()->status);
    }

    public function test_creation_key_retries_require_current_authority_same_creator_and_same_payload(): void
    {
        $creator = $this->incidentAdmin();
        $other = $this->incidentAdmin();
        $service = app(IncidentService::class);
        $key = (string) Str::uuid();
        $detectedAt = now()->subMinute()->startOfSecond();
        $values = $this->serviceCreateValues($creator, $detectedAt);

        $first = $service->create($this->evidence($creator), $values, $key);
        $retry = $service->create($this->evidence($creator), $values, $key);
        $this->assertSame($first->uuid, $retry->uuid);
        $this->assertSame(1, PrivacyIncidentEvent::query()->where('event_type', 'incident_created')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'incident.created')->count());

        $changed = [...$values, 'technical_severity' => 'critical'];
        $this->assertValidationRejected(fn () => $service->create($this->evidence($creator), $changed, $key));
        $this->assertValidationRejected(fn () => $service->create($this->evidence($other), $values, $key));

        $staleEvidence = $this->evidence($creator);
        $creator->forceFill(['security_version' => $creator->security_version + 1])->save();
        $this->assertValidationRejected(fn () => $service->create($staleEvidence, $values, $key));
        $this->assertDatabaseCount('privacy_incidents', 1);
        $this->assertSame(1, PrivacyIncidentEvent::query()->where('event_type', 'incident_created')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'incident.created')->count());
    }

    public function test_lifecycle_controller_decision_close_and_reopen_preserve_history(): void
    {
        $admin = $this->incidentAdmin();
        $incident = $this->create($admin);
        $this->resolve($admin, $incident);
        $service = app(IncidentService::class);
        $service->recordDecision($this->evidence($admin), $incident, $this->decision('pending'), (string) Str::uuid());
        $service->recordDecision($this->evidence($admin), $incident, $this->decision('requires_further_review'), (string) Str::uuid());
        $this->assertSame([1, 2], $incident->decisions()->pluck('version')->all());
        $service->close($this->evidence($admin), $incident, (string) Str::uuid());
        $closedAt = $incident->fresh()->closed_at;
        $service->reopen($this->evidence($admin), $incident, 'new_evidence', (string) Str::uuid());
        $this->assertSame('investigating', $incident->fresh()->status);
        $this->assertEquals($closedAt, $incident->fresh()->closed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'incident.closed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'incident.reopened']);
        $this->assertStringNotContainsString('Minimized controller-provided rationale', AuditLog::query()->get()->pluck('meta')->toJson());
        $this->expectException(LogicException::class);
        $incident->decisions()->firstOrFail()->forceFill(['reason_code' => 'new_evidence'])->save();
    }

    public function test_controller_boundary_requires_direct_authority_and_severity_never_sets_decisions(): void
    {
        $support = $this->admin([PrivacySecurityPermissions::INCIDENTS_MANAGE]);
        $incident = $this->create($support);
        \Illuminate\Support\Facades\Mail::fake();
        app(IncidentService::class)->changeSeverity($this->evidence($support), $incident, 'critical', (string) Str::uuid());
        $this->assertDatabaseCount('privacy_incident_decisions', 0);
        \Illuminate\Support\Facades\Mail::assertNothingSent();

        $this->expectException(ValidationException::class);
        app(IncidentService::class)->recordDecision($this->evidence($support), $incident, $this->decision('pending'), (string) Str::uuid());
    }

    public function test_escalation_and_acknowledgement_are_explicit_distinct_idempotent_actions(): void
    {
        $admin = $this->incidentAdmin();
        $incident = $this->create($admin);
        $service = app(IncidentService::class);
        $escalationKey = (string) Str::uuid();
        $service->escalate($this->evidence($admin), $incident, $escalationKey);
        $service->escalate($this->evidence($admin), $incident, $escalationKey);
        $this->assertSame('escalated', $incident->fresh()->escalation_status);
        $this->assertNull($incident->fresh()->acknowledged_at);
        $service->acknowledge($this->evidence($admin), $incident, (string) Str::uuid());
        $this->assertSame('acknowledged', $incident->fresh()->escalation_status);
        $this->assertNotNull($incident->fresh()->acknowledged_at);
    }

    public function test_explicit_existing_hold_survives_close_and_reopen_without_duplication(): void
    {
        $admin = $this->incidentAdmin();
        $admin->givePermissionTo(PrivacySecurityPermissions::LEGAL_HOLDS_MANAGE);
        $subject = User::factory()->create();
        $incident = $this->create($admin);
        $service = app(IncidentService::class);
        $key = (string) Str::uuid();
        $service->issueHold($this->evidence($admin->fresh()), $incident, [
            'subject_user_id' => $subject->id, 'scope_type' => LegalHold::SCOPE_SUBJECT,
            'data_category' => null, 'resource_id' => null, 'hold_code' => 'investigation_preservation', 'effective_at' => now(),
        ], $key);
        $service->issueHold($this->evidence($admin->fresh()), $incident, [
            'subject_user_id' => $subject->id, 'scope_type' => LegalHold::SCOPE_SUBJECT,
            'data_category' => null, 'resource_id' => null, 'hold_code' => 'investigation_preservation', 'effective_at' => now(),
        ], $key);
        $this->resolve($admin->fresh(), $incident);
        $service->recordDecision($this->evidence($admin->fresh()), $incident, $this->decision('pending'), (string) Str::uuid());
        $service->close($this->evidence($admin->fresh()), $incident, (string) Str::uuid());
        $service->reopen($this->evidence($admin->fresh()), $incident, 'new_evidence', (string) Str::uuid());
        $this->assertDatabaseCount('legal_holds', 1);
        $this->assertDatabaseHas('legal_holds', ['status' => LegalHold::STATUS_ACTIVE]);
        $this->assertDatabaseCount('privacy_incident_holds', 1);
    }

    public function test_models_are_guarded_history_is_immutable_and_audit_failure_rolls_back(): void
    {
        $admin = $this->incidentAdmin();
        $incident = $this->create($admin);
        try {
            $incident->fill(['status' => 'closed']);
            $this->fail('Lifecycle mass assignment must fail.');
        } catch (MassAssignmentException) {
            $this->assertSame('open', $incident->status);
        }
        $this->expectException(LogicException::class);
        $incident->events()->firstOrFail()->forceFill(['event_type' => 'incident_closed'])->save();
    }

    public function test_critical_audit_failure_rolls_back_close(): void
    {
        $admin = $this->incidentAdmin();
        $incident = $this->create($admin);
        $this->resolve($admin, $incident);
        app(IncidentService::class)->recordDecision($this->evidence($admin), $incident, $this->decision('pending'), (string) Str::uuid());
        DB::unprepared("CREATE TRIGGER fail_incident_audit BEFORE INSERT ON audit_logs WHEN NEW.action = 'incident.closed' BEGIN SELECT RAISE(ABORT, 'synthetic audit failure'); END");
        try {
            app(IncidentService::class)->close($this->evidence($admin), $incident, (string) Str::uuid());
            $this->fail('Audit failure must abort closure.');
        } catch (\Throwable) {
            $this->assertSame('resolved', $incident->fresh()->status);
            $this->assertDatabaseMissing('privacy_incident_events', ['privacy_incident_id' => $incident->id, 'event_type' => 'incident_closed']);
        }
    }

    public function test_ui_denies_non_admin_generic_admin_role_only_and_stale_session(): void
    {
        $applicant = User::factory()->create();
        $applicant->assignRole('job_seeker');
        $this->actingAs($applicant)->get('/admin/privacy/incidents')->assertForbidden();
        $employer = User::factory()->create();
        $employer->assignRole('employer');
        $this->actingAs($employer)->get('/admin/privacy/incidents')->assertForbidden();
        $plain = $this->enrollAdministratorMfa($this->admin());
        $this->actingAsMfaVerified($plain)->get('/admin/privacy/incidents')->assertForbidden();
        $plain->getRoleNames();
        $plain->roles->first()->givePermissionTo(PrivacySecurityPermissions::INCIDENTS_VIEW);
        $this->actingAsMfaVerified($plain->fresh())->get('/admin/privacy/incidents')->assertForbidden();
        $plain->givePermissionTo(PrivacySecurityPermissions::INCIDENTS_VIEW);
        $this->actingAsMfaVerified($plain->fresh())->get('/admin/privacy/incidents')->assertOk()->assertSee('TECHNICAL INCIDENT');
        $plain->givePermissionTo(PrivacySecurityPermissions::INCIDENTS_MANAGE);
        $this->actingAsMfaVerified($plain->fresh(), false)->withSession(['auth.password_confirmed_at' => 0])
            ->post('/admin/privacy/incidents', [])->assertRedirect(route('password.confirm'));
        $this->withSession([\App\Services\Security\AdminSessionService::SESSION_VERSION_KEY => $plain->security_version - 1])
            ->get('/admin/privacy/incidents')->assertRedirect(route('login'));
    }

    public function test_reconciliation_defaults_to_read_only_and_only_adds_review_evidence(): void
    {
        $admin = $this->incidentAdmin();
        $incident = $this->create($admin);
        $incident->owner->revokePermissionTo(PrivacySecurityPermissions::INCIDENTS_MANAGE);
        $this->artisan('privacy:reconcile-incidents', ['user_id' => $admin->id])->assertSuccessful();
        $this->assertNull($incident->fresh()->review_required_at);
        $this->artisan('privacy:reconcile-incidents', ['user_id' => $admin->id, '--execute' => true])->assertSuccessful();
        $this->assertNotNull($incident->fresh()->review_required_at);
        $this->assertSame('open', $incident->fresh()->status);
        $this->assertDatabaseCount('privacy_incident_decisions', 0);
        $this->artisan('privacy:reconcile-incidents', ['user_id' => $admin->id, '--execute' => true])->assertSuccessful();
        $this->assertSame(1, PrivacyIncidentEvent::query()->where('event_type', 'review_required')->count());
    }

    public function test_incident_manager_bootstrap_is_explicit_idempotent_and_audited(): void
    {
        $admin = $this->admin();
        foreach (PrivacySecurityPermissions::incidentManager() as $permission) {
            $this->assertFalse($admin->hasDirectPermission($permission));
        }
        $this->artisan('privacy:grant-incident-manager', ['user_id' => $admin->id])
            ->expectsOutput('Incident-manager permissions granted and audited.')
            ->assertSuccessful();
        $this->artisan('privacy:grant-incident-manager', ['user_id' => $admin->id])->assertSuccessful();
        foreach (PrivacySecurityPermissions::incidentManager() as $permission) {
            $this->assertTrue($admin->fresh()->hasDirectPermission($permission));
        }
        $this->assertSame(count(PrivacySecurityPermissions::incidentManager()), AuditLog::query()
            ->where('action', 'admin_permission_changed')
            ->where('reason_code', 'approved_incident_manager_bootstrap')->count());
    }

    private function create(User $admin): PrivacyIncident
    {
        return app(IncidentService::class)->create($this->evidence($admin), [
            'technical_severity' => 'high', 'classification_code' => 'confidentiality',
            'technical_summary' => 'Minimized technical observation without personal data.', 'detected_at' => now()->subMinute(),
            'owner_user_id' => $admin->id, 'data_categories' => [RetentionDataCategories::APPLICANT_DOCUMENT],
            'system_codes' => ['private_documents'], 'potentially_affected_subject_count' => 12,
        ], (string) Str::uuid());
    }

    private function serviceCreateValues(User $admin, mixed $detectedAt): array
    {
        return [
            'technical_severity' => 'high', 'classification_code' => 'confidentiality',
            'technical_summary' => 'Minimized technical observation without personal data.', 'detected_at' => $detectedAt,
            'owner_user_id' => $admin->id, 'data_categories' => [RetentionDataCategories::APPLICANT_DOCUMENT],
            'system_codes' => ['private_documents'], 'potentially_affected_subject_count' => 12,
        ];
    }

    private function httpCreateValues(User $admin, string $key, mixed $count = null): array
    {
        $values = [
            'idempotency_key' => $key,
            ...$this->serviceCreateValues($admin, now()->subMinute()->format('Y-m-d H:i:s')),
        ];
        if ($count === null) {
            unset($values['potentially_affected_subject_count']);
        } else {
            $values['potentially_affected_subject_count'] = $count;
        }

        return $values;
    }

    private function assertValidationRejected(callable $operation): void
    {
        try {
            $operation();
            $this->fail('The operation should have failed closed.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    private function resolve(User $admin, PrivacyIncident $incident): void
    {
        $service = app(IncidentService::class);
        foreach ([['triage', 'triage_review', 'performed'], ['investigating', 'investigation_update', 'performed'], ['contained', 'access_restricted', 'verified'], ['recovering', 'configuration_corrected', 'performed'], ['resolved', 'recovery_verified', 'verified']] as [$state,$action,$result]) {
            $service->transition($this->evidence($admin), $incident, $state, $action, $result, (string) Str::uuid());
        }
    }

    private function decision(string $assessment): array
    {
        return ['breach_assessment' => $assessment, 'authority_notification_decision' => 'pending', 'subject_notification_decision' => 'pending', 'reason_code' => 'controller_review', 'rationale' => 'Minimized controller-provided rationale.'];
    }

    private function evidence(User $user): IncidentActorEvidence
    {
        $user = $user->fresh();

        return new IncidentActorEvidence($user->id, $user->security_version);
    }

    private function incidentAdmin(): User
    {
        return $this->admin(PrivacySecurityPermissions::incidentManager());
    }

    private function admin(array $permissions = []): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        if ($permissions) {
            $user->givePermissionTo($permissions);
        }

        return $user->fresh();
    }
}
