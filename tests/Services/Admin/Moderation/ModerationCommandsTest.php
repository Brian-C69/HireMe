<?php

declare(strict_types=1);

use App\Services\Admin\Moderation\Commands\ApproveJobCommand;
use App\Services\Admin\Moderation\Commands\AuditLogCommand;
use App\Services\Admin\Moderation\Commands\OverviewCommand;
use App\Services\Admin\Moderation\Commands\ReinstateUserCommand;
use App\Services\Admin\Moderation\Commands\SuspendUserCommand;
use App\Services\Admin\Moderation\ModerationSuspensionStore;
use App\Services\Admin\Moderation\UserLookup;
use Illuminate\Database\Capsule\Manager as Capsule;

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

class StubModuleService implements \App\Services\Modules\ModuleServiceInterface
{
    /** @param array<string, array<string, mixed>|\Throwable> $responses */
    public function __construct(
        private string $moduleName,
        private array $responses = []
    ) {
    }

    public function name(): string
    {
        return $this->moduleName;
    }

    public function handle(string $type, ?string $id, \App\Core\Request $request): array
    {
        $key = strtolower($type) . ':' . ($id ?? '');
        if (isset($this->responses[$key])) {
            $response = $this->responses[$key];
            if ($response instanceof \Throwable) {
                throw $response;
            }

            return $response;
        }

        return ['module' => $this->moduleName];
    }
}

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
$pdo->exec("INSERT INTO job_postings (job_posting_id, company_id, status, created_at, updated_at) VALUES (1, 2, 'flagged', '2025-01-01 00:00:00', '2025-01-01 00:00:00')");

$pdo->exec('CREATE TABLE candidates (
    candidate_id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT,
    email TEXT,
    created_at TEXT,
    updated_at TEXT
)');
$pdo->exec("INSERT INTO candidates (candidate_id, full_name, email, created_at, updated_at) VALUES (1, 'Test Candidate', 'candidate@example.com', '2025-01-01 00:00:00', '2025-01-01 00:00:00')");
$pdo->exec("ALTER TABLE candidates ADD COLUMN verified_status TEXT DEFAULT 'pending'");
$pdo->exec("UPDATE candidates SET verified_status = 'pending' WHERE candidate_id = 1");

$pdo->exec('CREATE TABLE payments (
    payment_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    user_type TEXT,
    amount REAL,
    transaction_status TEXT,
    created_at TEXT,
    updated_at TEXT
)');
$pdo->exec("INSERT INTO payments (payment_id, user_id, user_type, amount, transaction_status, created_at, updated_at) VALUES (1, 3, 'employer', 250.00, 'failed', '2025-01-01 00:00:00', '2025-01-01 00:00:00')");

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

$pdo->exec("UPDATE job_postings SET status = 'flagged'");

$store->suspend('candidate', 2, null, null, 1001);

$registry = new \App\Services\Modules\ModuleRegistry();
$registry->register(new StubModuleService('user-management', [
    'users:all' => [
        'users' => [
            'candidates' => [['candidate_id' => 1, 'email' => 'candidate@example.com']],
        ],
        'counts' => ['total' => 1],
    ],
    'user:1' => ['user' => ['candidate_id' => 1, 'email' => 'candidate@example.com']],
    'user:2' => ['user' => ['employer_id' => 2, 'company_name' => 'Acme Corp']],
    'user:3' => new RuntimeException('Payment owner lookup failed'),
]));
$registry->register(new StubModuleService('job-application', [
    'summary:all' => [
        'summary' => [
            'jobs' => ['total' => 1],
            'applications' => ['total' => 1],
        ],
    ],
]));
$registry->register(new StubModuleService('payment-billing', [
    'summary:all' => new RuntimeException('Billing snapshot unavailable'),
]));

$overview = new OverviewCommand($registry, $store);
$overviewResult = $overview->execute();
$overviewData = $overviewResult->data()['overview'] ?? [];
assert($overviewResult->status() === 'success', 'OverviewCommand should succeed even when a snapshot fails.');
assert($overviewData['pending_verifications'] === 1, 'Pending verification count should include awaiting candidates.');
assert($overviewData['flagged_jobs'] === 1, 'Flagged jobs count should reflect flagged listings.');
assert($overviewData['failed_payments'] === 1, 'Failed payments count should reflect failed transactions.');
assert($overviewData['active_suspensions'] === 1, 'Active suspension count should reflect stored suspensions.');
assert(($overviewData['user_counts']['total'] ?? 0) === 1, 'User snapshot should retain count metadata.');
assert(isset($overviewData['errors']['payment-billing']), 'Overview response should surface snapshot failures.');

$audit = new AuditLogCommand($registry, $store);
$auditResult = $audit->execute();
$auditData = $auditResult->data()['audit'] ?? [];
assert(count($auditData['candidates']) === 1, 'Audit log should include pending candidates.');
assert(($auditData['candidates'][0]['user']['candidate_id'] ?? null) === 1, 'Audit candidate entry should embed user details.');
assert(($auditData['jobs'][0]['employer']['employer_id'] ?? null) === 2, 'Audit job entry should include employer details.');
assert($auditData['payments'][0]['user'] === null, 'Audit payment entry should tolerate failed user lookups.');

@unlink($tempStorePath);

echo "Moderation command execution tests passed\n";
