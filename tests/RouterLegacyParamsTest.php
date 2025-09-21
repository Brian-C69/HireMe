<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';
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

use App\Core\DB;

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec('CREATE TABLE employers (
    employer_id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_name TEXT,
    company_logo TEXT
)');

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
)');

$pdo->exec("INSERT INTO employers (employer_id, company_name, company_logo) VALUES (1, 'Acme Corp', '')");
$pdo->exec("INSERT INTO job_postings (
    company_id,
    recruiter_id,
    job_title,
    job_description,
    job_requirements,
    job_location,
    job_languages,
    employment_type,
    salary_range_min,
    salary_range_max,
    application_deadline,
    date_posted,
    status,
    number_of_positions,
    required_experience,
    education_level,
    created_at,
    updated_at
) VALUES (
    1,
    NULL,
    'Test Job',
    'Testing description',
    'Requirements',
    'Kuala Lumpur',
    'English',
    'Full-time',
    5000,
    6000,
    '2025-12-31',
    '2024-01-01',
    'Open',
    1,
    '2 years',
    'Bachelor',
    '2024-01-01 00:00:00',
    '2024-01-01 00:00:00'
)");

DB::setConnection($pdo);

$_GET = [];
$_POST = [];
$_COOKIE = [];
$_FILES = [];
$_SESSION = [];
$_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/jobs',
    'SCRIPT_NAME' => '/index.php',
    'SERVER_PROTOCOL' => 'HTTP/1.1',
    'HTTP_ACCEPT' => 'text/html',
];

ob_start();
require __DIR__ . '/../public/index.php';
$output = ob_get_clean();

assert(str_contains($output, 'Job Posts'), 'Jobs index should render the listing heading.');

echo "Router legacy params test passed\n";

