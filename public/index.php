<?php

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kuala_Lumpur');
session_start(); // session only here

// PSR-4 autoloader for App\
spl_autoload_register(function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    $len     = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative = substr($class, $len);
    $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) require $file;
});

use App\Core\Router;
use App\Controllers\HomeController;

$router = new Router();
$router->get('/', [HomeController::class, 'index']);
$router->get('/index.php', [HomeController::class, 'index']); // allow direct file hit

// Normalize path for XAMPP subfolder like /HireMe/public
$method    = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uriPath   = str_replace('\\', '/', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/'); // e.g. /HireMe/public
$path      = ($scriptDir && strpos($uriPath, $scriptDir) === 0) ? substr($uriPath, strlen($scriptDir)) : $uriPath;
if ($path === '' || $path === false) $path = '/';
if ($path === '/index.php') $path = '/';

$router->dispatch($method, $path);
