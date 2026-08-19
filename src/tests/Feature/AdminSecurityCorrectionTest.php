<?php

namespace Tests\Feature;

use App\Mail\AccountSetupMail;
use App\Models\Entitlement;
use App\Models\User;
use App\Services\Security\AccountSetupService;
use App\Services\Security\PrivacyAuditService;
use App\Support\PrivacySecurityPermissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class AdminSecurityCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_production_mailer_validation_recurses_through_unsafe_nested_and_cyclic_graphs(): void
    {
        Mail::fake();
        config(['app.env' => 'production']);
        $actor = $this->administrator('recursive-mail-actor@example.test');

        $graphs = [
            'direct unsafe transport' => [
                'default' => 'unsafe',
                'mailers' => ['unsafe' => ['transport' => 'array']],
            ],
            'safe to unsafe failover' => [
                'default' => 'outer',
                'mailers' => [
                    'outer' => ['transport' => 'failover', 'mailers' => ['resend', 'unsafe']],
                    'unsafe' => ['transport' => 'log'],
                ],
            ],
            'nested failover' => [
                'default' => 'outer',
                'mailers' => [
                    'outer' => ['transport' => 'failover', 'mailers' => ['inner']],
                    'inner' => ['transport' => 'failover', 'mailers' => ['resend', 'unsafe']],
                    'unsafe' => ['transport' => 'array'],
                ],
            ],
            'nested round robin' => [
                'default' => 'outer',
                'mailers' => [
                    'outer' => ['transport' => 'roundrobin', 'mailers' => ['inner']],
                    'inner' => ['transport' => 'failover', 'mailers' => ['resend', 'unsafe']],
                    'unsafe' => ['transport' => 'log'],
                ],
            ],
            'cyclic graph' => [
                'default' => 'first',
                'mailers' => [
                    'first' => ['transport' => 'failover', 'mailers' => ['second']],
                    'second' => ['transport' => 'roundrobin', 'mailers' => ['first']],
                ],
            ],
        ];

        foreach ($graphs as $label => $graph) {
            config(['mail.default' => $graph['default']]);

            foreach ($graph['mailers'] as $name => $configuration) {
                config(["mail.mailers.{$name}" => $configuration]);
            }

            $target = $this->user('employer', str_replace(' ', '-', $label).'@example.test');
            $originalPassword = $target->password;

            try {
                app(AccountSetupService::class)->issue($target, $actor);
                $this->fail("Unsafe mail graph [{$label}] was accepted.");
            } catch (RuntimeException) {
                $this->assertSame($originalPassword, $target->fresh()->password);
            }
        }

        Mail::assertNothingSent();
    }

    public function test_production_resend_and_local_smtp_are_allowed(): void
    {
        Mail::fake();
        $actor = $this->administrator('safe-mail-actor@example.test');

        config(['app.env' => 'production', 'mail.default' => 'resend']);
        app(AccountSetupService::class)->issue($this->user('employer', 'resend-target@example.test'), $actor);

        config(['app.env' => 'local', 'mail.default' => 'smtp']);
        app(AccountSetupService::class)->issue($this->user('employer', 'mailpit-target@example.test'), $actor);

        Mail::assertSent(AccountSetupMail::class, 2);
    }

    public function test_token_pages_set_no_referrer_and_replace_token_bearing_browser_history(): void
    {
        Mail::fake();
        $actor = $this->administrator('header-actor@example.test');
        $target = $this->user('employer', 'header-target@example.test');
        $setupUrl = '';

        app(AccountSetupService::class)->issue($target, $actor);
        Mail::assertSent(AccountSetupMail::class, function (AccountSetupMail $mail) use (&$setupUrl): bool {
            $setupUrl = $mail->setupUrl;

            return true;
        });

        $this->get($setupUrl)
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('window.history.replaceState', false)
            ->assertSee(route('account-setup.store'), false);

        $this->get(route('password.reset', ['token' => 'synthetic-reset-token', 'email' => $target->email]))
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('window.history.replaceState', false)
            ->assertSee(route('password.store'), false);
    }

    public function test_audit_rejects_secret_like_arbitrary_value_under_an_allowed_key(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(PrivacyAuditService::class)->record(
            event: 'privileged_reauthentication_succeeded',
            metadata: ['operation' => 'Bearer super-secret-session-value'],
        );
    }

    public function test_newly_hardened_admin_routes_default_deny_without_narrow_permissions(): void
    {
        Role::findByName('admin')->revokePermissionTo('entitlements.manage');
        $operator = $this->administrator('route-denied-operator@example.test');
        $target = $this->user('job_seeker', 'route-denied-target@example.test');
        $deleted = $this->user('employer', 'route-denied-deleted@example.test');
        $deleted->delete();
        $entitlement = Entitlement::create([
            'user_id' => $target->id,
            'type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'status' => Entitlement::STATUS_ACTIVE,
        ]);

        $this->actingAsMfaVerified($operator)
            ->patch(route('admin.users.restore', $deleted->id))
            ->assertForbidden();
        $this->post(route('admin.users.grant-access', $target), [
            'type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
        ])->assertForbidden();
        $this->delete(route('admin.users.revoke-access', [$target, Entitlement::TYPE_JOB_SEEKER_ACCESS]))
            ->assertForbidden();
        $this->post(route('admin.entitlements.store'), [
            'user_id' => $target->id,
            'type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'status' => Entitlement::STATUS_ACTIVE,
        ])->assertForbidden();
        $this->delete(route('admin.entitlements.destroy', $entitlement))->assertForbidden();

        $this->assertSoftDeleted('users', ['id' => $deleted->id]);
        $this->assertDatabaseHas('entitlements', ['id' => $entitlement->id]);
    }

    public function test_newly_hardened_admin_routes_require_recent_password_confirmation(): void
    {
        $operator = $this->administrator('route-reauth-operator@example.test');
        $operator->givePermissionTo(PrivacySecurityPermissions::ADMIN_SECURITY_MANAGE);
        $target = $this->user('job_seeker', 'route-reauth-target@example.test');
        $deleted = $this->user('employer', 'route-reauth-deleted@example.test');
        $deleted->delete();
        $entitlement = Entitlement::create([
            'user_id' => $target->id,
            'type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'status' => Entitlement::STATUS_ACTIVE,
        ]);

        $this->actingAsMfaVerified($operator, false)
            ->patch(route('admin.users.restore', $deleted->id))
            ->assertRedirect(route('password.confirm'));
        $this->post(route('admin.users.grant-access', $target), [
            'type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
        ])->assertRedirect(route('password.confirm'));
        $this->delete(route('admin.users.revoke-access', [$target, Entitlement::TYPE_JOB_SEEKER_ACCESS]))
            ->assertRedirect(route('password.confirm'));
        $this->post(route('admin.entitlements.store'), [
            'user_id' => $target->id,
            'type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'status' => Entitlement::STATUS_ACTIVE,
        ])->assertRedirect(route('password.confirm'));
        $this->delete(route('admin.entitlements.destroy', $entitlement))
            ->assertRedirect(route('password.confirm'));

        $this->assertSoftDeleted('users', ['id' => $deleted->id]);
        $this->assertDatabaseHas('entitlements', ['id' => $entitlement->id]);
    }

    public function test_mfa_reset_and_soft_deletion_roll_back_when_critical_audit_fails(): void
    {
        $operator = $this->administrator('rollback-operator@example.test');
        $operator->givePermissionTo(PrivacySecurityPermissions::ADMIN_SECURITY_MANAGE);
        $mfaTarget = $this->administrator('rollback-mfa-target@example.test');
        $originalSecret = $mfaTarget->two_factor_secret;
        $originalVersion = $mfaTarget->security_version;
        $this->failAuditFor('admin_mfa_reset');

        $this->actingAsMfaVerified($operator)
            ->delete(route('admin.security.users.mfa.destroy', $mfaTarget))
            ->assertServerError();
        $mfaTarget->refresh();
        $this->assertSame($originalSecret, $mfaTarget->two_factor_secret);
        $this->assertSame($originalVersion, $mfaTarget->security_version);

        DB::unprepared('DROP TRIGGER fail_selected_audit');
        $deleteTarget = $this->user('employer', 'rollback-delete-target@example.test');
        $originalVersion = $deleteTarget->security_version;
        $this->failAuditFor('user_soft_deleted');

        $this->actingAsMfaVerified($operator)
            ->delete(route('admin.users.destroy', $deleteTarget))
            ->assertServerError();
        $this->assertNotSoftDeleted('users', ['id' => $deleteTarget->id]);
        $this->assertSame($originalVersion, $deleteTarget->fresh()->security_version);
    }

    public function test_bootstrap_permission_grant_is_idempotent_audited_and_rolls_back_on_audit_failure(): void
    {
        $manager = $this->administrator('bootstrap-manager@example.test');

        $this->artisan('security:grant-manager', ['user_id' => $manager->id])->assertSuccessful();
        $this->artisan('security:grant-manager', ['user_id' => $manager->id])->assertSuccessful();
        $this->assertTrue($manager->fresh()->hasDirectPermission(PrivacySecurityPermissions::ADMIN_SECURITY_MANAGE));
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'admin_permission_changed')->count());

        $second = $this->administrator('bootstrap-rollback@example.test');
        $this->failAuditFor('admin_permission_changed');
        $this->artisan('security:grant-manager', ['user_id' => $second->id])->assertFailed();
        $this->assertFalse($second->fresh()->hasDirectPermission(PrivacySecurityPermissions::ADMIN_SECURITY_MANAGE));
    }

    public function test_provisioning_audit_failure_rolls_back_and_delivery_audit_failure_does_not_duplicate_email(): void
    {
        Mail::fake();
        $operator = $this->administrator('provisioning-operator@example.test');
        $operator->givePermissionTo(PrivacySecurityPermissions::ADMIN_SECURITY_MANAGE);
        $this->failAuditFor('employer_account_provisioned');

        $this->actingAsMfaVerified($operator)
            ->post(route('admin.employers.store'), [
                'name' => 'Rollback Employer',
                'email' => 'rollback-employer@example.test',
                'company_name' => 'Rollback Company',
            ])
            ->assertSessionHas('error');
        $this->assertDatabaseMissing('users', ['email' => 'rollback-employer@example.test']);
        Mail::assertNothingSent();

        DB::unprepared('DROP TRIGGER fail_selected_audit');
        $this->failAuditFor('account_setup_link_delivery_succeeded');
        $target = $this->user('employer', 'delivery-audit-target@example.test');
        app(AccountSetupService::class)->issue($target, $operator);

        Mail::assertSent(AccountSetupMail::class, 1);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account_setup_link_issued',
            'outcome' => PrivacyAuditService::OUTCOME_PENDING,
        ]);
        Mail::assertSent(AccountSetupMail::class, function (AccountSetupMail $mail) use ($target): bool {
            $token = basename((string) parse_url($mail->setupUrl, PHP_URL_PATH));

            return Password::broker()->tokenExists($target, $token);
        });
    }

    private function administrator(string $email): User
    {
        return $this->enrollAdministratorMfa($this->user('admin', $email));
    }

    private function user(string $role, string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'must_change_password' => false,
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }

    private function failAuditFor(string $action): void
    {
        $escaped = str_replace("'", "''", $action);
        DB::unprepared("CREATE TRIGGER fail_selected_audit BEFORE INSERT ON audit_logs WHEN NEW.action = '{$escaped}' BEGIN SELECT RAISE(ABORT, 'synthetic audit failure'); END");
    }
}
