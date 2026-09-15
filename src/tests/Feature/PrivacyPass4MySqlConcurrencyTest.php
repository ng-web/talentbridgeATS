<?php

namespace Tests\Feature;

use App\Models\PrivacyIncident;
use App\Models\User;
use App\Services\Privacy\IncidentActorEvidence;
use App\Services\Privacy\IncidentMutationBarrier;
use App\Services\Privacy\IncidentService;
use App\Support\PrivacySecurityPermissions;
use Closure;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class PrivacyPass4MySqlConcurrencyTest extends TestCase
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
            config(["database.connections.{$name}" => [...$base, 'host' => env('MYSQL_CONCURRENCY_HOST', $base['host']), 'port' => env('MYSQL_CONCURRENCY_PORT', $base['port']), 'database' => $database, 'username' => env('MYSQL_CONCURRENCY_USERNAME', $base['username']), 'password' => env('MYSQL_CONCURRENCY_PASSWORD', $base['password'])]]);
            DB::purge($name);
        }
        DB::setDefaultConnection('mysql_concurrency_a');
        Artisan::call('migrate:fresh', ['--database' => 'mysql_concurrency_a', '--force' => true, '--path' => [
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
            'database/migrations/2026_09_03_000001_create_privacy_incident_tables.php',
            'database/migrations/2026_09_03_000002_add_pass4_privacy_permissions.php',
        ]]);
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

    public function test_incident_row_fence_serializes_close_against_reopen(): void
    {
        $closer = $this->admin();
        $reopener = $this->admin();
        $incident = $this->incident($closer);
        $this->resolveAndDecide($closer, $incident);
        $timedOut = false;
        $this->barrier(function () use ($reopener, $incident, &$timedOut): void {
            $this->onConnection('mysql_concurrency_b', function () use ($reopener, $incident, &$timedOut): void {
                $this->assertLockTimeout(fn () => app(IncidentService::class)->reopen($this->evidence($reopener), $incident, 'new_evidence', (string) Str::uuid()));
                $timedOut = true;
            });
        });
        app(IncidentService::class)->close($this->evidence($closer), $incident, (string) Str::uuid());
        $this->assertTrue($timedOut);
        $this->onConnection('mysql_concurrency_b', fn () => app(IncidentService::class)->reopen($this->evidence($reopener), $incident, 'new_evidence', (string) Str::uuid()));
        $this->assertSame('investigating', $incident->fresh()->status);
        $this->assertNotNull($incident->fresh()->closed_at);
    }

    public function test_incident_row_fence_serializes_decision_versions(): void
    {
        $first = $this->admin();
        $second = $this->admin();
        $incident = $this->incident($first);
        $timedOut = false;
        $this->barrier(function () use ($second, $incident, &$timedOut): void {
            $this->onConnection('mysql_concurrency_b', function () use ($second, $incident, &$timedOut): void {
                $this->assertLockTimeout(fn () => app(IncidentService::class)->recordDecision($this->evidence($second), $incident, $this->decision(), (string) Str::uuid()));
                $timedOut = true;
            });
        });
        app(IncidentService::class)->recordDecision($this->evidence($first), $incident, $this->decision(), (string) Str::uuid());
        $this->assertTrue($timedOut);
        $this->onConnection('mysql_concurrency_b', fn () => app(IncidentService::class)->recordDecision($this->evidence($second), $incident, $this->decision(), (string) Str::uuid()));
        $this->assertSame([1, 2], $incident->decisions()->pluck('version')->all());
    }

    public function test_concurrent_same_actor_creation_key_retries_converge_without_duplicate_evidence(): void
    {
        $actor = $this->admin();
        $key = (string) Str::uuid();
        $detectedAt = now()->subMinute()->startOfSecond()->format('Y-m-d H:i:s');
        $worker = base_path('tests/Support/Pass4ConcurrentCreateWorker.php');
        $input = new InputStream;
        $first = new Process([PHP_BINARY, $worker, (string) $actor->id, $key, $detectedAt, 'hold'], base_path(), null, $input, 20);
        $second = new Process([PHP_BINARY, $worker, (string) $actor->id, $key, $detectedAt, 'continue'], base_path(), null, null, 20);

        $first->start();
        $this->waitForProcessOutput($first, 'AUTHORITY_ESTABLISHED');
        $second->start();
        $this->waitForProcessOutput($second, 'CONNECTION_ID=');
        preg_match('/CONNECTION_ID=(\d+)/', $second->getOutput(), $connectionMatch);
        $this->waitForMySqlLockWait((int) ($connectionMatch[1] ?? 0));

        $input->write("continue\n");
        $input->close();
        $first->wait();
        $second->wait();

        $this->assertTrue($first->isSuccessful(), $first->getErrorOutput().$first->getOutput());
        $this->assertTrue($second->isSuccessful(), $second->getErrorOutput().$second->getOutput());
        preg_match('/INCIDENT_UUID=([0-9a-f-]+)/i', $first->getOutput(), $firstUuid);
        preg_match('/INCIDENT_UUID=([0-9a-f-]+)/i', $second->getOutput(), $secondUuid);
        $this->assertNotEmpty($firstUuid[1] ?? null);
        $this->assertSame($firstUuid[1], $secondUuid[1] ?? null);
        $this->assertSame(1, PrivacyIncident::query()->where('creation_key', $key)->count());
        $incident = PrivacyIncident::query()->where('creation_key', $key)->firstOrFail();
        $this->assertSame(1, $incident->events()->where('event_type', 'incident_created')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'incident.created')->count());
    }

    private function barrier(Closure $callback): void
    {
        $this->app->instance(IncidentMutationBarrier::class, new class($callback) implements IncidentMutationBarrier
        {
            private bool $fired = false;

            public function __construct(private readonly Closure $callback) {}

            public function reached(string $point): void
            {
                if (! $this->fired && $point === self::AFTER_INCIDENT_LOCK) {
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
            $this->fail('Concurrent mutation crossed the incident row fence.');
        } catch (QueryException $e) {
            $this->assertSame('HY000', $e->errorInfo[0] ?? null);
            $this->assertSame(1205, $e->errorInfo[1] ?? null);
        }
    }

    private function waitForProcessOutput(Process $process, string $needle): void
    {
        $ready = $process->waitUntil(fn (string $type, string $buffer): bool => str_contains($process->getOutput(), $needle));
        $this->assertTrue($ready, $process->getErrorOutput().$process->getOutput());
    }

    private function waitForMySqlLockWait(int $connectionId): void
    {
        $this->assertGreaterThan(0, $connectionId);
        $deadline = hrtime(true) + 5_000_000_000;
        $process = null;
        do {
            $process = DB::connection('mysql_concurrency_a')->selectOne(
                'SELECT COMMAND, STATE, INFO FROM information_schema.PROCESSLIST WHERE ID = ?',
                [$connectionId],
            );
            if ($process && str_contains(strtolower((string) $process->INFO), 'for update')) {
                $this->addToAssertionCount(1);

                return;
            }
            usleep(10_000);
        } while (hrtime(true) < $deadline);

        $this->fail('The second create request did not reach the actor authority lock. Last process state: '.json_encode($process));
    }

    private function evidence(User $u): IncidentActorEvidence
    {
        $u = $u->fresh();

        return new IncidentActorEvidence($u->id, $u->security_version);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');
        $u->givePermissionTo(PrivacySecurityPermissions::incidentManager());

        return $u->fresh();
    }

    private function incident(User $u): PrivacyIncident
    {
        return app(IncidentService::class)->create($this->evidence($u), ['technical_severity' => 'high', 'classification_code' => 'confidentiality', 'technical_summary' => 'Minimal technical evidence.', 'detected_at' => now(), 'owner_user_id' => $u->id, 'data_categories' => [], 'system_codes' => [], 'potentially_affected_subject_count' => null], (string) Str::uuid());
    }

    private function decision(): array
    {
        return ['breach_assessment' => 'pending', 'authority_notification_decision' => 'pending', 'subject_notification_decision' => 'pending', 'reason_code' => 'controller_review', 'rationale' => null];
    }

    private function resolveAndDecide(User $u, PrivacyIncident $i): void
    {
        foreach ([['triage', 'triage_review'], ['investigating', 'investigation_update'], ['contained', 'access_restricted'], ['recovering', 'configuration_corrected'], ['resolved', 'recovery_verified']] as [$state,$action]) {
            app(IncidentService::class)->transition($this->evidence($u), $i, $state, $action, 'verified', (string) Str::uuid());
        } app(IncidentService::class)->recordDecision($this->evidence($u), $i, $this->decision(), (string) Str::uuid());
    }
}
