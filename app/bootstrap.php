<?php

declare(strict_types=1);

use App\Core\Container;
use App\Core\DB;
use App\Core\Middleware;
use Illuminate\Database\Capsule\Manager as Capsule;

// Base directory of the project
$root = dirname(__DIR__);

// -----------------------------------------------------------------------------
// Autoloading (Composer + PSR-4 for App\)
// -----------------------------------------------------------------------------
$vendor = $root . '/vendor/autoload.php';
if (is_file($vendor)) {
    require $vendor;
}

// Fallback PSR-4 autoloader for the App namespace
spl_autoload_register(function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = __DIR__ . '/';
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
$debug = (bool)($config['app']['debug'] ?? false);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting($config['app']['error_level'] ?? E_ALL);
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function (\Throwable $e) use ($debug): void {
    error_log((string) $e);
    http_response_code(500);
    if ($debug) {
        echo $e->getMessage() . "\n" . $e->getTraceAsString();
    } else {
        echo 'Application error';
    }
});

date_default_timezone_set($_ENV['APP_TZ'] ?? 'Asia/Kuala_Lumpur');

// -----------------------------------------------------------------------------
// Session (secure cookie settings + inactivity timeout)
// -----------------------------------------------------------------------------
$now = time();
$timeoutSeconds = (int) ($_ENV['SESSION_TIMEOUT'] ?? 900);
if ($timeoutSeconds <= 0) {
    $timeoutSeconds = 900; // sensible default (15 minutes)
}

$cookieParams = session_get_cookie_params();
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$sameSiteEnv = (string) ($_ENV['SESSION_SAMESITE'] ?? 'Strict');
$allowedSameSites = ['lax', 'strict', 'none'];
$normalisedSameSite = strtolower($sameSiteEnv);
if (!in_array($normalisedSameSite, $allowedSameSites, true)) {
    $normalisedSameSite = 'strict';
}
$sessionOptions = [
    'cookie_lifetime' => 0,
    'cookie_path' => $cookieParams['path'] ?? '/',
    'cookie_secure' => (($cookieParams['secure'] ?? false) || $isSecure),
    'cookie_httponly' => true,
    'cookie_samesite' => ucfirst($normalisedSameSite),
    'use_strict_mode' => 1,
    'gc_maxlifetime' => max($timeoutSeconds, (int) ini_get('session.gc_maxlifetime')),
];
if (!empty($cookieParams['domain'])) {
    $sessionOptions['cookie_domain'] = $cookieParams['domain'];
}

$startSession = static function () use ($sessionOptions): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start($sessionOptions);
    }
};

$startSession();

if (isset($_SESSION['user'])) {
    $lastActivity = $_SESSION['last_activity'] ?? null;
    if (is_int($lastActivity) && ($now - $lastActivity) > $timeoutSeconds) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', [
                    'expires' => $now - 42000,
                    'path' => $params['path'] ?? '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => $params['secure'] ?? false,
                    'httponly' => $params['httponly'] ?? true,
                    'samesite' => $params['samesite'] ?? 'Strict',
                ]);
            }
            session_destroy();
        }
        $startSession();
        $_SESSION['flash'] = [
            'type' => 'warning',
            'message' => 'Your session expired due to inactivity. Please log in again.',
        ];
    }
}

$_SESSION['last_activity'] = $now;

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

