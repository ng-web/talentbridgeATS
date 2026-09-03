<?php

namespace Tests\Feature;

use App\Models\DispositionPlan;
use App\Models\DispositionPlanItem;
use App\Models\JobSeeker;
use App\Models\JobSeekerDocument;
use App\Models\RetentionRule;
use App\Models\User;
use App\Services\Privacy\DispositionExecutionBarrier;
use App\Services\Privacy\DispositionExecutorEvidence;
use App\Services\Privacy\DispositionService;
use App\Services\Privacy\LegalHoldService;
use App\Services\Privacy\RetentionDataCategories;
use App\Services\Privacy\RetentionRuleRegistryService;
use App\Services\Security\AdminSessionService;
use App\Support\PrivacySecurityPermissions;
use Closure;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class PrivacyPass3MySqlConcurrencyTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();
        $database = (string) env('MYSQL_CONCURRENCY_DATABASE', '');
        if ($database === '') {
            $this->markTestSkipped('Set MYSQL_CONCURRENCY_DATABASE to an isolated MySQL schema to run destructive concurrency tests.');
        }

        $base = config('database.connections.mysql');
        foreach (['mysql_concurrency_a', 'mysql_concurrency_b'] as $name) {
            config(["database.connections.{$name}" => [
                ...$base,
                'host' => env('MYSQL_CONCURRENCY_HOST', $base['host']),
                'port' => env('MYSQL_CONCURRENCY_PORT', $base['port']),
                'database' => $database,
                'username' => env('MYSQL_CONCURRENCY_USERNAME', $base['username']),
                'password' => env('MYSQL_CONCURRENCY_PASSWORD', $base['password']),
            ]]);
            DB::purge($name);
        }

        DB::setDefaultConnection('mysql_concurrency_a');
        Artisan::call('migrate:fresh', [
            '--database' => 'mysql_concurrency_a',
            '--force' => true,
            '--path' => [
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'database/migrations/2026_03_06_194229_create_permission_tables.php',
                'database/migrations/2026_03_06_194457_create_job_seekers_table.php',
                'database/migrations/2026_03_06_194459_create_audit_logs_table.php',
                'database/migrations/2026_05_21_000002_create_job_seeker_documents_table.php',
                'database/migrations/2026_06_01_000002_update_job_seeker_documents_for_multi_upload.php',
                'database/migrations/2026_06_29_000004_add_deleted_at_to_users_table.php',
                'database/migrations/2026_08_18_000001_extend_audit_logs_for_security_events.php',
                'database/migrations/2026_08_18_000002_add_admin_security_fields_to_users_table.php',
                'database/migrations/2026_09_02_000001_create_retention_and_disposition_tables.php',
                'database/migrations/2026_09_02_000002_add_pass3_privacy_permissions.php',
            ],
        ]);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->assertSame('REPEATABLE-READ', DB::connection('mysql_concurrency_a')->selectOne('SELECT @@transaction_isolation AS level')->level);
    }

    protected function tearDown(): void
    {
        foreach (['mysql_concurrency_a', 'mysql_concurrency_b'] as $name) {
            if (config("database.connections.{$name}") && DB::connection($name)->transactionLevel() > 0) {
                DB::connection($name)->rollBack();
            }
            if (config("database.connections.{$name}")) {
                DB::purge($name);
            }
        }
        DB::setDefaultConnection($this->originalConnection);

        parent::tearDown();
    }

    public function test_actual_disposition_service_serializes_both_hold_orderings(): void
    {
        [$subject, $document] = $this->document();
        $authorizer = $this->retentionAdmin();
        $executor = $this->retentionAdmin();
        $holdAdmin = $this->retentionAdmin();
        $rule = $this->approve($authorizer, 1, now()->subDays(2));
        $plan = $this->authorizedPlan($authorizer, $rule, $document);
        Queue::fake();
        $this->barrier(DispositionExecutionBarrier::BEFORE_SUBJECT_FENCE, function () use ($holdAdmin, $subject): void {
            $this->onConnection('mysql_concurrency_b', fn () => app(LegalHoldService::class)->issue($holdAdmin, $this->subjectHold($subject)));
        });

        $result = app(DispositionService::class)->execute($this->evidence($executor), $plan);

        $this->assertSame(DispositionPlan::STATUS_REVIEW_REQUIRED, $result->status);
        $this->assertDatabaseHas('job_seeker_documents', ['id' => $document->id], 'mysql_concurrency_a');
        $this->assertDatabaseHas('disposition_plan_items', ['disposition_plan_id' => $plan->id, 'status' => DispositionPlanItem::STATUS_SKIPPED], 'mysql_concurrency_a');
        Queue::assertNothingPushed();

        [$subject2, $document2] = $this->document();
        $plan2 = $this->authorizedPlan($authorizer, $rule, $document2);
        $timedOut = false;
        $this->barrier(DispositionExecutionBarrier::BEFORE_DESTRUCTIVE_MUTATION, function () use ($holdAdmin, $subject2, &$timedOut): void {
            $this->onConnection('mysql_concurrency_b', function () use ($holdAdmin, $subject2, &$timedOut): void {
                $this->assertLockTimeout(fn () => app(LegalHoldService::class)->issue($holdAdmin, $this->subjectHold($subject2)));
                $timedOut = true;
            });
        });

        $result2 = app(DispositionService::class)->execute($this->evidence($executor), $plan2);

        $this->assertTrue($timedOut);
        $this->assertSame(DispositionPlan::STATUS_COMPLETED, $result2->status);
        $this->assertDatabaseMissing('job_seeker_documents', ['id' => $document2->id], 'mysql_concurrency_a');
        $this->onConnection('mysql_concurrency_b', fn () => app(LegalHoldService::class)->issue($holdAdmin, $this->subjectHold($subject2)));
    }

    public function test_actual_disposition_service_serializes_both_rule_orderings(): void
    {
        [, $document] = $this->document();
        $authorizer = $this->retentionAdmin();
        $executor = $this->retentionAdmin();
        $ruleAdmin = $this->retentionAdmin();
        $rule = $this->approve($authorizer, 1, now()->subDays(2));
        $plan = $this->authorizedPlan($authorizer, $rule, $document);
        $this->barrier(DispositionExecutionBarrier::BEFORE_CATEGORY_FENCE, function () use ($ruleAdmin, $rule): void {
            $this->onConnection('mysql_concurrency_b', fn () => app(RetentionRuleRegistryService::class)->retire($ruleAdmin, $rule));
        });

        $result = app(DispositionService::class)->execute($this->evidence($executor), $plan);

        $this->assertSame(DispositionPlan::STATUS_REVIEW_REQUIRED, $result->status);
        $this->assertDatabaseHas('job_seeker_documents', ['id' => $document->id], 'mysql_concurrency_a');

        $rule2 = $this->approve($authorizer, 2, now()->subDay());
        [, $document2] = $this->document();
        $plan2 = $this->authorizedPlan($authorizer, $rule2, $document2);
        $timedOut = false;
        $this->barrier(DispositionExecutionBarrier::BEFORE_DESTRUCTIVE_MUTATION, function () use ($ruleAdmin, $rule2, &$timedOut): void {
            $this->onConnection('mysql_concurrency_b', function () use ($ruleAdmin, $rule2, &$timedOut): void {
                $this->assertLockTimeout(fn () => app(RetentionRuleRegistryService::class)->retire($ruleAdmin, $rule2));
                $timedOut = true;
            });
        });

        $result2 = app(DispositionService::class)->execute($this->evidence($executor), $plan2);

        $this->assertTrue($timedOut);
        $this->assertSame(DispositionPlan::STATUS_COMPLETED, $result2->status);
        $this->assertDatabaseMissing('job_seeker_documents', ['id' => $document2->id], 'mysql_concurrency_a');
        $this->onConnection('mysql_concurrency_b', fn () => app(RetentionRuleRegistryService::class)->retire($ruleAdmin, $rule2));
    }

    public function test_actual_disposition_service_rejects_reset_first_and_serializes_execution_first(): void
    {
        [, $document] = $this->document();
        $authorizer = $this->retentionAdmin();
        $executor = $this->retentionAdmin();
        $rule = $this->approve($authorizer, 1, now()->subDays(2));
        $plan = $this->authorizedPlan($authorizer, $rule, $document);
        $evidence = $this->evidence($executor);
        Queue::fake();
        $this->barrier(DispositionExecutionBarrier::BEFORE_AUTHORITY_FENCE, function () use ($executor): void {
            $this->onConnection('mysql_concurrency_b', fn () => app(AdminSessionService::class)->invalidateAll(User::query()->findOrFail($executor->id)));
        });

        $result = app(DispositionService::class)->execute($evidence, $plan);

        $this->assertSame(DispositionPlan::STATUS_REVIEW_REQUIRED, $result->status);
        $this->assertDatabaseHas('job_seeker_documents', ['id' => $document->id], 'mysql_concurrency_a');
        $this->assertDatabaseHas('disposition_plan_items', ['disposition_plan_id' => $plan->id, 'outcome_code' => 'stale_authority'], 'mysql_concurrency_a');
        Queue::assertNothingPushed();

        [, $authorizerDocument] = $this->document();
        $authorizer2 = $this->retentionAdmin();
        $authorizerExecutor = $this->retentionAdmin();
        $authorizerPlan = $this->authorizedPlan($authorizer2, $rule, $authorizerDocument);
        $this->barrier(DispositionExecutionBarrier::BEFORE_AUTHORITY_FENCE, function () use ($authorizer2): void {
            $this->onConnection('mysql_concurrency_b', fn () => User::query()->findOrFail($authorizer2->id)->revokePermissionTo(PrivacySecurityPermissions::DISPOSITION_AUTHORIZE));
        });

        $authorizerResult = app(DispositionService::class)->execute($this->evidence($authorizerExecutor), $authorizerPlan);

        $this->assertSame(DispositionPlan::STATUS_REVIEW_REQUIRED, $authorizerResult->status);
        $this->assertDatabaseHas('job_seeker_documents', ['id' => $authorizerDocument->id], 'mysql_concurrency_a');
        $this->assertDatabaseHas('disposition_plan_items', ['disposition_plan_id' => $authorizerPlan->id, 'outcome_code' => 'stale_authority'], 'mysql_concurrency_a');

        [, $document2] = $this->document();
        $executor2 = $this->retentionAdmin();
        $plan2 = $this->authorizedPlan($authorizer, $rule, $document2);
        $evidence2 = $this->evidence($executor2);
        $timedOut = false;
        $this->barrier(DispositionExecutionBarrier::BEFORE_DESTRUCTIVE_MUTATION, function () use ($executor2, &$timedOut): void {
            $this->onConnection('mysql_concurrency_b', function () use ($executor2, &$timedOut): void {
                $this->assertLockTimeout(fn () => app(AdminSessionService::class)->invalidateAll(User::query()->findOrFail($executor2->id)));
                $timedOut = true;
            });
        });

        $result2 = app(DispositionService::class)->execute($evidence2, $plan2);

        $this->assertTrue($timedOut);
        $this->assertSame(DispositionPlan::STATUS_COMPLETED, $result2->status);
        $this->assertDatabaseMissing('job_seeker_documents', ['id' => $document2->id], 'mysql_concurrency_a');
        $this->onConnection('mysql_concurrency_b', fn () => app(AdminSessionService::class)->invalidateAll(User::query()->findOrFail($executor2->id)));
        $this->assertGreaterThan($evidence2->securityVersion, User::query()->findOrFail($executor2->id)->security_version);
    }

    public function test_actual_disposition_service_rejects_permission_revocation_first_and_serializes_execution_first(): void
    {
        [, $document] = $this->document();
        $authorizer = $this->retentionAdmin();
        $executor = $this->retentionAdmin();
        $rule = $this->approve($authorizer, 1, now()->subDays(2));
        $plan = $this->authorizedPlan($authorizer, $rule, $document);
        $evidence = $this->evidence($executor);
        Queue::fake();
        $this->barrier(DispositionExecutionBarrier::BEFORE_AUTHORITY_FENCE, function () use ($executor): void {
            $this->onConnection('mysql_concurrency_b', fn () => User::query()->findOrFail($executor->id)->revokePermissionTo(PrivacySecurityPermissions::DISPOSITION_EXECUTE));
        });

        $result = app(DispositionService::class)->execute($evidence, $plan);

        $this->assertSame(DispositionPlan::STATUS_REVIEW_REQUIRED, $result->status);
        $this->assertDatabaseHas('job_seeker_documents', ['id' => $document->id], 'mysql_concurrency_a');
        $this->assertDatabaseHas('disposition_plan_items', ['disposition_plan_id' => $plan->id, 'outcome_code' => 'stale_authority'], 'mysql_concurrency_a');
        Queue::assertNothingPushed();

        [, $document2] = $this->document();
        $executor2 = $this->retentionAdmin();
        $plan2 = $this->authorizedPlan($authorizer, $rule, $document2);
        $timedOut = false;
        $this->barrier(DispositionExecutionBarrier::BEFORE_DESTRUCTIVE_MUTATION, function () use ($executor2, &$timedOut): void {
            $this->onConnection('mysql_concurrency_b', function () use ($executor2, &$timedOut): void {
                $this->assertLockTimeout(fn () => User::query()->findOrFail($executor2->id)->revokePermissionTo(PrivacySecurityPermissions::DISPOSITION_EXECUTE));
                $timedOut = true;
            });
        });

        $result2 = app(DispositionService::class)->execute($this->evidence($executor2), $plan2);

        $this->assertTrue($timedOut);
        $this->assertSame(DispositionPlan::STATUS_COMPLETED, $result2->status);
        $this->assertDatabaseMissing('job_seeker_documents', ['id' => $document2->id], 'mysql_concurrency_a');
        $this->onConnection('mysql_concurrency_b', fn () => User::query()->findOrFail($executor2->id)->revokePermissionTo(PrivacySecurityPermissions::DISPOSITION_EXECUTE));
        $this->assertFalse(User::query()->findOrFail($executor2->id)->hasDirectPermission(PrivacySecurityPermissions::DISPOSITION_EXECUTE));
    }

    private function authorizedPlan(User $authorizer, RetentionRule $rule, JobSeekerDocument $document): DispositionPlan
    {
        $service = app(DispositionService::class);

        return $service->authorize($authorizer, $service->createPlan($authorizer, $rule, [$document]));
    }

    private function evidence(User $executor): DispositionExecutorEvidence
    {
        $current = $executor->fresh();

        return new DispositionExecutorEvidence((int) $current->id, (int) $current->security_version);
    }

    private function barrier(string $target, Closure $callback): void
    {
        $this->app->instance(DispositionExecutionBarrier::class, new class($target, $callback) implements DispositionExecutionBarrier
        {
            private bool $fired = false;

            public function __construct(private readonly string $target, private readonly Closure $callback) {}

            public function reached(string $point): void
            {
                if (! $this->fired && $point === $this->target) {
                    $this->fired = true;
                    ($this->callback)();
                }
            }
        });
    }

    private function onConnection(string $connection, Closure $operation): mixed
    {
        $previous = DB::getDefaultConnection();
        DB::setDefaultConnection($connection);

        try {
            return $operation();
        } finally {
            DB::setDefaultConnection($previous);
        }
    }

    private function assertLockTimeout(callable $operation): void
    {
        DB::connection('mysql_concurrency_b')->statement('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            $operation();
            $this->fail('The second connection must not cross an owned authoritative fence.');
        } catch (QueryException $exception) {
            $this->assertSame('HY000', $exception->errorInfo[0] ?? null);
            $this->assertSame(1205, $exception->errorInfo[1] ?? null);
        }
    }

    private function retentionAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->givePermissionTo(PrivacySecurityPermissions::retentionManager());

        return $user;
    }

    /** @return array{User, JobSeekerDocument} */
    private function document(): array
    {
        $subject = User::factory()->create();
        $subject->assignRole('job_seeker');
        $profile = JobSeeker::query()->create(['user_id' => $subject->id]);
        $document = JobSeekerDocument::query()->create([
            'job_seeker_id' => $profile->id,
            'document_type' => JobSeekerDocument::TYPE_CERTIFICATE,
            'file_path' => 'applicants/'.$profile->id.'/documents/certificate/'.\Illuminate\Support\Str::uuid().'.pdf',
            'uploaded_at' => now()->subYears(2),
        ]);
        $document->forceFill(['created_at' => now()->subYears(2), 'updated_at' => now()->subYears(2)])->save();

        return [$subject, $document->fresh()];
    }

    private function approve(User $admin, int $version, mixed $effectiveAt): RetentionRule
    {
        $registry = app(RetentionRuleRegistryService::class);

        return $registry->approve($admin, $registry->createDraft($admin, [
            'data_category' => RetentionDataCategories::APPLICANT_DOCUMENT,
            'version' => $version,
            'trigger_type' => RetentionDataCategories::TRIGGER_RECORD_CREATED,
            'retention_value' => 1,
            'retention_unit' => RetentionRule::UNIT_DAYS,
            'disposition_method' => RetentionDataCategories::METHOD_DETACH_AND_DELETE_FILE,
            'effective_at' => $effectiveAt,
        ]));
    }

    private function subjectHold(User $subject): array
    {
        return [
            'subject_user_id' => $subject->id,
            'scope_type' => \App\Models\LegalHold::SCOPE_SUBJECT,
            'data_category' => null,
            'resource_id' => null,
            'hold_code' => 'controller_direction',
            'effective_at' => now()->subMinute(),
        ];
    }
}
