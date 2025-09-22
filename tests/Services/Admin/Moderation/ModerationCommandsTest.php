<?php

declare(strict_types=1);

require __DIR__ . '/../../../../vendor/autoload.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../../../../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Services\Admin\Moderation\Commands\ApproveJobCommand;
use App\Services\Admin\Moderation\Commands\ReinstateUserCommand;
use App\Services\Admin\Moderation\Commands\SuspendUserCommand;
use App\Services\Admin\Moderation\ModerationSuspensionStore;
use App\Services\Admin\Moderation\UserLookup;
use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule();
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$pdo = $capsule->getConnection()->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec('CREATE TABLE job_postings (
    job_posting_id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER,
    status TEXT,
    created_at TEXT,
    updated_at TEXT
)');
$pdo->exec("INSERT INTO job_postings (job_posting_id, company_id, status, created_at, updated_at) VALUES (1, 10, 'flagged', '2025-01-01 00:00:00', '2025-01-01 00:00:00')");

$pdo->exec('CREATE TABLE candidates (
    candidate_id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT,
    email TEXT,
    created_at TEXT,
    updated_at TEXT
)');
$pdo->exec("INSERT INTO candidates (candidate_id, full_name, email, created_at, updated_at) VALUES (1, 'Test Candidate', 'candidate@example.com', '2025-01-01 00:00:00', '2025-01-01 00:00:00')");

$approve = new ApproveJobCommand(1, 42);
$approveResult = $approve->execute();

assert($approveResult->status() === 'success', 'ApproveJobCommand should succeed.');
assert($approveResult->data()['status'] === 'Open', 'Job status should be set to Open.');
$status = $pdo->query('SELECT status FROM job_postings WHERE job_posting_id = 1')->fetchColumn();
assert($status === 'Open', 'Database status should reflect approval.');

$tempStorePath = sys_get_temp_dir() . '/moderation_suspensions_' . uniqid() . '.json';
$store = new ModerationSuspensionStore($tempStorePath);
$lookup = new UserLookup();

$until = new DateTimeImmutable('+2 days');
$suspend = new SuspendUserCommand('candidate', 1, $store, $lookup, $until, 'Review documents', 99);
$suspendResult = $suspend->execute();
assert($suspendResult->status() === 'success', 'SuspendUserCommand should succeed.');
$suspensionData = $store->get('candidate', 1);
if ($suspensionData === null) {
    throw new RuntimeException('Suspension record should exist.');
}
assert($suspensionData['until'] === $until->format(DateTimeInterface::ATOM), 'Suspension expiry should match the expected timestamp.');

$reinstate = new ReinstateUserCommand('candidate', 1, $store, $lookup, 99);
$reinstateResult = $reinstate->execute();
assert($reinstateResult->status() === 'success', 'ReinstateUserCommand should succeed.');
assert($store->get('candidate', 1) === null, 'Suspension should be cleared after reinstatement.');

@unlink($tempStorePath);

echo "Moderation command execution tests passed\n";
