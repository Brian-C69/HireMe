<?php

declare(strict_types=1);

use App\Core\Container;
use App\Core\DB;
use App\Core\Middleware;
use Illuminate\Database\Capsule\Manager as Capsule;

$root = __DIR__;

// -----------------------------------------------------------------------------
// Autoloading (Composer + PSR-4 for App\)
// -----------------------------------------------------------------------------
$vendor = $root . '/vendor/autoload.php';
if (is_file($vendor)) {
    require $vendor;
}

spl_autoload_register(function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = __DIR__ . '/app/';
    $len     = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative = substr($class, $len);
    $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) require $file;
});

// -----------------------------------------------------------------------------
// Environment loading (.env)
// -----------------------------------------------------------------------------
$envFile = $root . '/.env';
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strncmp($line, '#', 1) === 0) {
            continue;
        }
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if (!array_key_exists($name, $_ENV)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

// -----------------------------------------------------------------------------
// Configuration loading
// -----------------------------------------------------------------------------
$config = require $root . '/config/config.php';

// -----------------------------------------------------------------------------
// Error handling
// -----------------------------------------------------------------------------
ini_set('display_errors', $config['app']['display_errors'] ?? '1');
error_reporting($config['app']['error_level'] ?? E_ALL);
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function (\Throwable $e): void {
    error_log((string) $e);
    http_response_code(500);
    echo 'Application error';
});

date_default_timezone_set($_ENV['APP_TZ'] ?? 'Asia/Kuala_Lumpur');

// -----------------------------------------------------------------------------
// Session
// -----------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -----------------------------------------------------------------------------
// Base URL calculation
// -----------------------------------------------------------------------------
define('BASE_URL', rtrim(str_replace('\\\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/') ?: '');

// -----------------------------------------------------------------------------
// Dependency container & ORM initialisation
// -----------------------------------------------------------------------------
$capsule = new Capsule();
$capsule->addConnection(require $root . '/config/database.php');
$capsule->setAsGlobal();
$capsule->bootEloquent();
$container = new Container();
$container->set('config', $config);
$container->set('db', DB::conn());
$container->set('orm', $capsule);

// Shared middleware registry (none yet but ready for expansion)
Middleware::run();

return $container;
