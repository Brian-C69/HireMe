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
DB::setConnection($pdo);

$_GET = [];
$_POST = [];
$_COOKIE = [];
$_FILES = [];
$_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/',
    'SCRIPT_NAME' => '/index.php',
    'SERVER_PROTOCOL' => 'HTTP/1.1',
    'HTTP_ACCEPT' => 'text/html',
];

ob_start();
require __DIR__ . '/../public/index.php';
$output = ob_get_clean();

assert(str_contains($output, 'HireMe — Hiring in Malaysia, simplified.'), 'Home page should include the marketing headline.');

echo "Home route smoke test passed\n";
