<?php

use App\Models\User;
use App\Services\Privacy\IncidentActorEvidence;
use App\Services\Privacy\IncidentMutationBarrier;
use App\Services\Privacy\IncidentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$base = config('database.connections.mysql');
config(['database.connections.mysql_concurrency_worker' => [
    ...$base,
    'host' => env('MYSQL_CONCURRENCY_HOST', $base['host']),
    'port' => env('MYSQL_CONCURRENCY_PORT', $base['port']),
    'database' => env('MYSQL_CONCURRENCY_DATABASE', $base['database']),
    'username' => env('MYSQL_CONCURRENCY_USERNAME', $base['username']),
    'password' => env('MYSQL_CONCURRENCY_PASSWORD', $base['password']),
]]);
DB::purge('mysql_concurrency_worker');
DB::setDefaultConnection('mysql_concurrency_worker');

[$script, $actorId, $creationKey, $detectedAt, $holdAtBarrier] = $argv;
$actor = User::query()->findOrFail((int) $actorId);

if ($holdAtBarrier === 'hold') {
    $app->instance(IncidentMutationBarrier::class, new class implements IncidentMutationBarrier
    {
        public function reached(string $point): void
        {
            if ($point !== self::AFTER_CREATE_AUTHORITY) {
                return;
            }
            fwrite(STDOUT, "AUTHORITY_ESTABLISHED\n");
            fflush(STDOUT);
            if (fgets(STDIN) === false) {
                throw new RuntimeException('The concurrency barrier was not released.');
            }
        }
    });
}

fwrite(STDOUT, 'CONNECTION_ID='.(int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id."\n");
fflush(STDOUT);

$incident = $app->make(IncidentService::class)->create(
    new IncidentActorEvidence($actor->id, $actor->security_version),
    [
        'technical_severity' => 'high',
        'classification_code' => 'confidentiality',
        'technical_summary' => 'Minimal concurrent retry evidence.',
        'detected_at' => $detectedAt,
        'owner_user_id' => $actor->id,
        'data_categories' => [],
        'system_codes' => [],
        'potentially_affected_subject_count' => null,
    ],
    $creationKey,
);

fwrite(STDOUT, 'INCIDENT_UUID='.$incident->uuid."\n");
fflush(STDOUT);
