<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) require $file;
});

use App\Services\JobService;
use Illuminate\Database\Capsule\Manager as Capsule;

// set up an in-memory sqlite database for testing
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
    recruiter_id INTEGER,
    job_title TEXT,
    job_description TEXT,
    job_requirements TEXT,
    job_location TEXT,
    job_languages TEXT,
    employment_type TEXT,
    salary_range_min REAL,
    salary_range_max REAL,
    application_deadline TEXT,
    date_posted TEXT,
    status TEXT,
    number_of_positions INTEGER,
    required_experience TEXT,
    education_level TEXT,
    created_at TEXT,
    updated_at TEXT
);');
$pdo->exec('CREATE TABLE job_micro_questions (
    job_posting_id INTEGER,
    question_id INTEGER
);');

$service = new JobService();
[$jobId, $errors] = $service->create('Employer', 1, [
    'job_title' => 'Example job',
    'job_description' => 'Description',
    'salary' => '1200',
    'employment_type' => 'Full-time',
    'mi_questions' => [1, 2, 3],
]);

assert(empty($errors), 'No validation errors expected');
assert($jobId === 1, 'Job ID should be 1');

echo "JobService tests passed\n";
