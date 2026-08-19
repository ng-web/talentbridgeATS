<?php

namespace Tests\Feature;

use App\Mail\AccountSetupMail;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Security\AdministratorMfaSession;
use App\Services\Security\AdminSessionService;
use App\Services\Security\PrivacyAuditService;
use App\Support\PrivacySecurityPermissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class AdminSecurityFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_without_completed_mfa_cannot_enter_admin_area_but_applicant_and_employer_login_are_unchanged(): void
    {
        $admin = $this->user('admin', 'admin-mfa-required@example.test');
        $applicant = $this->user('job_seeker', 'applicant-no-mfa@example.test');
        $employer = $this->user('employer', 'employer-no-mfa@example.test');

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.security.mfa.show'));

        $this->post('/logout');
        $this->post('/login', ['email' => $applicant->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($applicant);

        $this->post('/logout');
        $this->post('/login', ['email' => $employer->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($employer);
    }

    public function test_admin_can_confirm_mfa_and_complete_a_valid_challenge(): void
    {
        $admin = $this->user('admin', 'admin-mfa-success@example.test');
        $this->startMfa($admin);

        $secret = Fortify::currentEncrypter()->decrypt($admin->fresh()->two_factor_secret);
        $this->assertNotSame('TEST-ONLY-MFA-SECRET', $secret);
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        $code = (new Google2FA)->getCurrentOtp($secret);

        $this->post(route('admin.security.mfa.confirm'), ['code' => $code])
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $this->assertNotNull($admin->fresh()->two_factor_confirmed_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin_mfa_enabled',
            'subject_user_id' => $admin->id,
            'outcome' => PrivacyAuditService::OUTCOME_SUCCESS,
        ]);

        auth()->logout();
        $this->travel(31)->seconds();
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'));
        $this->assertGuest();

        $google2fa = new Google2FA;
        $nextCode = $google2fa->oathTotp($secret, $google2fa->getTimestamp() + 1);
        $this->post(route('two-factor.login.store'), ['code' => $nextCode])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_invalid_mfa_challenge_is_denied_and_recovery_code_is_single_use(): void
    {
        $admin = $this->confirmedMfaAdmin('admin-mfa-recovery@example.test');
        $recoveryCode = $admin->recoveryCodes()[0];

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'));
        $this->post(route('two-factor.login.store'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post(route('two-factor.login.store'), ['recovery_code' => $recoveryCode])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);
        $this->assertNotContains($recoveryCode, $admin->fresh()->recoveryCodes());

        $this->post('/logout');
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'));
        $this->post(route('two-factor.login.store'), ['recovery_code' => $recoveryCode])
            ->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_remembered_admin_identity_requires_mfa_before_direct_admin_access(): void
    {
        $admin = $this->confirmedMfaAdmin('remembered-admin@example.test');
        [$cookieName, $cookieValue] = $this->recallerCookie($admin);
        $rememberToken = $admin->remember_token;

        $this->flushSession();
        Auth::forgetGuards();

        $this->withCookie($cookieName, $cookieValue)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('two-factor.login'))
            ->assertSessionHas('login.id', $admin->id);

        $this->assertGuest();
        $this->assertNotSame($rememberToken, $admin->fresh()->remember_token);
        $this->assertNull(session(AdministratorMfaSession::VERIFIED_AT_KEY));
    }

    public function test_remember_cookie_created_before_mfa_enrollment_is_invalidated_when_mfa_is_enabled(): void
    {
        $admin = $this->user('admin', 'pre-enrollment-cookie@example.test');
        [$cookieName, $cookieValue] = $this->recallerCookie($admin);
        $rememberToken = $admin->remember_token;

        $admin = $this->enrollAdministratorMfa($admin);
        $this->assertNotSame($rememberToken, $admin->remember_token);

        $this->flushSession();
        Auth::forgetGuards();

        $this->withCookie($cookieName, $cookieValue)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_mfa_reset_is_permissioned_reauthenticated_audited_and_revokes_sessions(): void
    {
        $operator = $this->confirmedMfaAdmin('mfa-operator@example.test');
        $target = $this->confirmedMfaAdmin('mfa-target@example.test');
        $operator->givePermissionTo(PrivacySecurityPermissions::ADMIN_SECURITY_MANAGE);
        $target = $target->fresh();
        $originalVersion = $target->security_version;
        $originalRememberToken = $target->remember_token;
        $this->insertSession('mfa-target-session', $target);

        $this->actingAsMfaVerified($operator)
            ->delete(route('admin.security.users.mfa.destroy', $target))
            ->assertSessionHas('success');

        $target->refresh();
        $this->assertNull($target->two_factor_secret);
        $this->assertNull($target->two_factor_recovery_codes);
        $this->assertNull($target->two_factor_confirmed_at);
        $this->assertSame($originalVersion + 1, $target->security_version);
        $this->assertNotSame($originalRememberToken, $target->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'mfa-target-session']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin_mfa_reset',
            'actor_user_id' => $operator->id,
            'subject_user_id' => $target->id,
            'reason_code' => 'authorized_admin_recovery',
        ]);
    }

    public function test_admin_without_explicit_security_permission_is_denied_and_privileged_action_requires_reauthentication(): void
    {
        $operator = $this->confirmedMfaAdmin('mfa-no-permission@example.test');
        $target = $this->confirmedMfaAdmin('mfa-denied-target@example.test');

        $this->actingAsMfaVerified($operator)
            ->delete(route('admin.security.users.mfa.destroy', $target))
            ->assertForbidden();

        $operator->givePermissionTo(PrivacySecurityPermissions::ADMIN_SECURITY_MANAGE);
        $this->actingAsMfaVerified($operator)
            ->withSession(['auth.password_confirmed_at' => 0])
            ->delete(route('admin.security.users.mfa.destroy', $target))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_future_privacy_permissions_exist_and_are_deny_by_default(): void
    {
        $admin = $this->confirmedMfaAdmin('future-permissions@example.test');
        $applicant = $this->user('job_seeker', 'future-permissions-applicant@example.test');

        foreach (PrivacySecurityPermissions::all() as $permission) {
            $this->assertTrue(Permission::query()->where('name', $permission)->exists());
        }

        $this->assertTrue($admin->can(PrivacySecurityPermissions::ADMIN_SECURITY_SELF));
        $this->assertFalse($admin->can(PrivacySecurityPermissions::PRIVACY_REQUESTS_MANAGE));
        $this->assertFalse($admin->can(PrivacySecurityPermissions::RETENTION_EXECUTE));
        $this->assertFalse($applicant->can(PrivacySecurityPermissions::ADMIN_SECURITY_SELF));
    }

    public function test_account_setup_email_contains_no_password_and_session_or_view_never_flashes_credentials(): void
    {
        Mail::fake();
        $operator = $this->confirmedMfaAdmin('setup-operator@example.test');
        $operator->givePermissionTo(PrivacySecurityPermissions::ADMIN_SECURITY_MANAGE);
        $target = $this->user('employer', 'setup-target@example.test', 'KnownExistingPassword!');
        $logHandler = new TestHandler;
        Log::swap(new Logger('account-setup-test', [$logHandler]));

        $response = $this->actingAsMfaVerified($operator)
            ->post(route('admin.users.send-account-setup-link', $target));

        $response->assertSessionHas('success')
            ->assertSessionMissing('provisioned_credentials')
            ->assertSessionMissing('temporary_password');

        Mail::assertSent(AccountSetupMail::class, function (AccountSetupMail $mail): bool {
            $rendered = $mail->render();

            return ! str_contains($rendered, 'KnownExistingPassword!')
                && ! str_contains(strtolower($rendered), 'temporary password');
        });

        $this->actingAsMfaVerified($operator)
            ->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertDontSee('Temporary Password')
            ->assertDontSee('KnownExistingPassword!');

        $capturedLogs = json_encode($logHandler->getRecords(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('KnownExistingPassword!', $capturedLogs);
        $this->assertStringNotContainsString('temporary_password', strtolower($capturedLogs));
        $this->assertFalse(Hash::check('KnownExistingPassword!', $target->fresh()->password));
    }

    public function test_production_account_setup_refuses_a_logging_mail_transport_before_rotating_credentials(): void
    {
        Mail::fake();
        $operator = $this->confirmedMfaAdmin('unsafe-mailer-operator@example.test');
        $operator->givePermissionTo(PrivacySecurityPermissions::ADMIN_SECURITY_MANAGE);
        $target = $this->user('employer', 'unsafe-mailer-target@example.test', 'StillValidPassword!');
        $originalPasswordHash = $target->password;
        $originalSecurityVersion = $target->security_version;
        config(['app.env' => 'production', 'mail.default' => 'log']);

        $this->actingAsMfaVerified($operator)
            ->post(route('admin.users.send-account-setup-link', $target))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
        $target->refresh();
        $this->assertSame($originalPasswordHash, $target->password);
        $this->assertSame($originalSecurityVersion, $target->security_version);
    }

    public function test_setup_link_is_signed_expiring_single_use_and_establishes_password(): void
    {
        Mail::fake();
        $operator = $this->confirmedMfaAdmin('setup-flow-operator@example.test');
        $operator->givePermissionTo(PrivacySecurityPermissions::ADMIN_SECURITY_MANAGE);
        $target = $this->user('employer', 'setup-flow-target@example.test');
        $setupUrl = '';

        $this->actingAsMfaVerified($operator)->post(route('admin.users.send-account-setup-link', $target));
        Mail::assertSent(AccountSetupMail::class, function (AccountSetupMail $mail) use (&$setupUrl): bool {
            $setupUrl = $mail->setupUrl;

            return true;
        });

        auth()->logout();
        $this->get($setupUrl)->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->get($setupUrl.'&email=tampered@example.test')->assertForbidden();

        $path = (string) parse_url($setupUrl, PHP_URL_PATH);
        $token = basename($path);
        parse_str((string) parse_url($setupUrl, PHP_URL_QUERY), $query);

        $payload = [
            'token' => $token,
            'email' => $query['email'],
            'password' => 'NewSecurePassword!123',
            'password_confirmation' => 'NewSecurePassword!123',
        ];

        $this->post(route('account-setup.store'), $payload)
            ->assertRedirect(route('login'));
        $target = $target->fresh();
        $this->assertTrue(Hash::check('NewSecurePassword!123', $target->password));
        $this->assertFalse($target->must_change_password);

        $this->post(route('account-setup.store'), $payload)
            ->assertSessionHasErrors('email');
    }

    public function test_setup_link_signature_expires(): void
    {
        Mail::fake();
        $operator = $this->confirmedMfaAdmin('setup-expiry-operator@example.test');
        $operator->givePermissionTo(PrivacySecurityPermissions::ADMIN_SECURITY_MANAGE);
        $target = $this->user('employer', 'setup-expiry-target@example.test');
        $setupUrl = '';

        $this->actingAsMfaVerified($operator)->post(route('admin.users.send-account-setup-link', $target));
        Mail::assertSent(AccountSetupMail::class, function (AccountSetupMail $mail) use (&$setupUrl): bool {
            $setupUrl = $mail->setupUrl;

            return true;
        });

        $this->travel(((int) config('auth.passwords.users.expire', 60)) + 1)->minutes();
        auth()->logout();
        $this->get($setupUrl)->assertForbidden();
    }

    public function test_security_version_invalidates_stale_sessions_and_password_reset_removes_stored_sessions(): void
    {
        $user = $this->user('employer', 'session-version@example.test');
        $user = $user->fresh();

        $this->be($user)->withSession([
            AdminSessionService::SESSION_VERSION_KEY => $user->security_version - 1,
        ]);
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->insertSession('password-reset-session', $user);
        $token = Password::broker()->createToken($user);
        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ResetSecurePassword!123',
            'password_confirmation' => 'ResetSecurePassword!123',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('sessions', ['id' => 'password-reset-session']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'password_reset_completed',
            'subject_user_id' => $user->id,
        ]);
    }

    public function test_account_suspension_invalidates_all_sessions_and_deleted_user_cannot_continue(): void
    {
        $operator = $this->confirmedMfaAdmin('suspension-operator@example.test');
        $target = $this->user('employer', 'suspension-target@example.test');
        $target = $target->fresh();
        $originalVersion = $target->security_version;
        $this->insertSession('suspended-target-session', $target);

        $this->actingAsMfaVerified($operator)
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted('users', ['id' => $target->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'suspended-target-session']);
        $this->assertSame($originalVersion + 1, User::withTrashed()->findOrFail($target->id)->security_version);

        auth()->logout();
        $this->post('/login', ['email' => $target->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_audit_interface_records_structured_fields_and_rejects_sensitive_metadata(): void
    {
        $user = $this->user('job_seeker', 'audit-interface@example.test');
        $audit = app(PrivacyAuditService::class);

        $record = $audit->record(
            event: 'session_revoked',
            actor: $user,
            resource: $user,
            subjectUserId: $user->id,
            reasonCode: 'security_reset',
            metadata: [
                'session_revocation_count' => 2,
                'revocation_scope' => 'all_sessions',
            ],
        );

        $this->assertNotNull($record->correlation_id);
        $this->assertSame(PrivacyAuditService::OUTCOME_SUCCESS, $record->outcome);
        $this->assertSame('security_reset', $record->reason_code);
        $this->assertNotNull($record->occurred_at);

        foreach (['password', 'token', 'mfa_secret', 'recovery_codes', 'session_id'] as $sensitiveKey) {
            try {
                $audit->record(
                    event: 'session_revoked',
                    actor: $user,
                    metadata: [$sensitiveKey => 'must-not-be-stored'],
                );
                $this->fail("Sensitive key [{$sensitiveKey}] was accepted.");
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(1, AuditLog::query()->where('action', 'session_revoked')->count());
    }

    private function startMfa(User $admin): void
    {
        $admin->refresh();
        $this->be($admin)->withSession([
            AdminSessionService::SESSION_VERSION_KEY => $admin->security_version,
            'auth.password_confirmed_at' => time(),
        ]);

        $this->post(route('admin.security.mfa.start'))->assertOk();
        $this->assertNotNull($admin->fresh()->two_factor_secret);
        $this->assertNull($admin->fresh()->two_factor_confirmed_at);
    }

    private function confirmedMfaAdmin(string $email): User
    {
        $admin = $this->user('admin', $email);
        app(EnableTwoFactorAuthentication::class)($admin);
        $secret = Fortify::currentEncrypter()->decrypt($admin->fresh()->two_factor_secret);
        $this->assertNotSame('TEST-ONLY-MFA-SECRET', $secret);
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        app(ConfirmTwoFactorAuthentication::class)($admin, (new Google2FA)->getCurrentOtp($secret));

        return $admin->fresh();
    }

    private function user(string $role, string $email, string $password = 'password'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make($password),
            'must_change_password' => false,
        ]);
        $user->assignRole($role);

        return $user->fresh();
    }

    /** @return array{string, string} */
    private function recallerCookie(User $user): array
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        $user->refresh();
        $guard = Auth::guard('web');

        return [
            $guard->getRecallerName(),
            $user->getAuthIdentifier().'|'.$user->getRememberToken().'|'.$guard->hashPasswordForCookie($user->getAuthPassword()),
        ];
    }

    private function insertSession(string $id, User $user): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'security-test',
            'payload' => 'synthetic',
            'last_activity' => time(),
        ]);
    }
}
