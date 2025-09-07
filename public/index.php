<?php

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kuala_Lumpur');
session_start();

// PSR-4 autoloader for App\
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) require $file;
});

use App\Core\Router;
use App\Controllers\HomeController;

$router = new Router();

// Home — support both "/" and "/index.php" (why: users may hit the file directly)
$router->get('/', [HomeController::class, 'index']);
$router->get('/index.php', [HomeController::class, 'index']);

// Resolve request path and normalize
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
// Treat any URL ending with "/index.php" as "/"
if (preg_match('#/index\\.php$#', $path)) {
    $path = '/';
}

$router->dispatch($method, $path);
