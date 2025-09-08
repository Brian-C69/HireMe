<?php

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kuala_Lumpur');
session_start(); // session only here

// Compute BASE_URL for subfolder installs (e.g. /HireMe/public)
define('BASE_URL', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/') ?: '');

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
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\JobController;
use App\Controllers\CandidateController;
use App\Controllers\ResumeController;
use App\Controllers\EmployerController;


$router = new Router();

// Home — support both "/" and "/index.php"
$router->get('/', [HomeController::class, 'index']);
$router->get('/index.php', [HomeController::class, 'index']);

// Auth
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'doLogin']);
$router->get('/logout', [AuthController::class, 'logout']);

$router->get('/register', [\App\Controllers\AuthController::class, 'showRegister']);
$router->post('/register', [\App\Controllers\AuthController::class, 'doRegister']);

// Forgot / Reset
$router->get('/forgot', [AuthController::class, 'showForgot']);
$router->post('/forgot', [AuthController::class, 'sendReset']);
$router->get('/reset', [AuthController::class, 'showReset']);
$router->post('/reset', [AuthController::class, 'processReset']);

$router->get('/welcome', [DashboardController::class, 'welcome']);

$router->get('/jobs/create', [JobController::class, 'create']);

// Candidate profile (resume)
$router->get('/account', [CandidateController::class, 'edit']);
$router->post('/account', [CandidateController::class, 'update']);

$router->get('/resume', [ResumeController::class, 'edit']);
$router->post('/resume', [ResumeController::class, 'update']);

$router->get('/resume/pdf', [ResumeController::class, 'exportPdf']);

$router->get('/jobs',          [JobController::class, 'index']);
$router->get('/jobs/create',   [JobController::class, 'create']);  // Employer only
$router->post('/jobs',         [JobController::class, 'store']);   // Employer only
$router->get('/jobs/{id}',     [JobController::class, 'show']);

$router->get('/company', [EmployerController::class, 'edit']);    // Employer profile
$router->post('/company', [EmployerController::class, 'update']);

// Normalize path relative to BASE_URL
$method    = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uriPath   = str_replace('\\', '/', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$path      = (BASE_URL && str_starts_with($uriPath, BASE_URL)) ? substr($uriPath, strlen(BASE_URL)) : $uriPath;
if ($path === '' || $path === false) $path = '/';
if ($path === '/index.php') $path = '/';

$router->dispatch($method, $path);

// Polyfill for PHP < 8.0 (remove if not needed)
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle !== '' && strpos($haystack, $needle) === 0 || ($needle === '' && true);
    }
}
