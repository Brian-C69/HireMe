<?php

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kuala_Lumpur');
session_start();

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
use App\Controllers\ApplicationController;
use App\Controllers\PaymentController;

$router = new Router();

// Home — support both "/" and "/index.php"
$router->get('/', [HomeController::class, 'index']);
$router->get('/index.php', [HomeController::class, 'index']);

// Auth
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'doLogin']);
$router->get('/logout', [AuthController::class, 'logout']);

$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'doRegister']);

// Forgot / Reset
$router->get('/forgot', [AuthController::class, 'showForgot']);
$router->post('/forgot', [AuthController::class, 'sendReset']);
$router->get('/reset', [AuthController::class, 'showReset']);
$router->post('/reset', [AuthController::class, 'processReset']);

$router->get('/welcome', [DashboardController::class, 'welcome']);

// Candidate profile (resume)
$router->get('/account', [CandidateController::class, 'edit']);
$router->post('/account', [CandidateController::class, 'update']);

$router->get('/resume', [ResumeController::class, 'edit']);
$router->post('/resume', [ResumeController::class, 'update']);
$router->get('/resume/pdf', [ResumeController::class, 'exportPdf']);

$router->get('/applications', [\App\Controllers\ApplicationController::class, 'index']); // Candidate “My Applications”
$router->post('/applications/{id}/withdraw', [\App\Controllers\ApplicationController::class, 'withdraw']);

// Jobs (order matters: static before dynamic!)
$router->get('/jobs',        [JobController::class, 'index']);
$router->get('/jobs/create', [JobController::class, 'create']);  // Employer only
$router->post('/jobs',       [JobController::class, 'store']);   // Employer only
$router->get('/jobs/mine',   [JobController::class, 'mine']);    // <-- put BEFORE /jobs/{id}
$router->post('/jobs/{id}/status', [JobController::class, 'updateStatus']);
$router->post('/jobs/{id}/delete', [JobController::class, 'destroy']);

$router->get('/jobs/{id}/edit',       [JobController::class, 'edit']);
$router->post('/jobs/{id}/edit',      [JobController::class, 'update']);

$router->get('/jobs/{id}',            [JobController::class, 'show']); // dynamic LAST

// Employer profile
$router->get('/company',  [EmployerController::class, 'edit']);
$router->post('/company', [EmployerController::class, 'update']);

// Applications
$router->get('/jobs/{id}/apply',      [ApplicationController::class, 'create']);
$router->get('/applications/create',  [ApplicationController::class, 'create']); // legacy with ?job=
$router->post('/applications',        [ApplicationController::class, 'store']);

$router->post('/applications/{id}/withdraw', [ApplicationController::class, 'withdraw']);

// KYC (Candidate)
$router->get('/verify', [CandidateController::class, 'verifyForm']);
$router->post('/verify', [CandidateController::class, 'submitVerification']);

// Premium badge (Candidate)
$router->get('/premium',     [PaymentController::class, 'showPremium']);
$router->post('/premium/pay', [PaymentController::class, 'payPremium']);
$router->post('/premium/unset', [PaymentController::class, 'revokePremium']);

$router->get('/candidates',              [CandidateController::class, 'directory']); // list
$router->get('/candidates/{id}',         [CandidateController::class, 'view']);      // detail limited/full
$router->post('/candidates/{id}/unlock', [CandidateController::class, 'unlock']);    // unlock action

// Normalize path relative to BASE_URL (avoid str_starts_with for PHP 7+)
$method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uriPath = str_replace('\\', '/', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$base    = defined('BASE_URL') ? BASE_URL : '';
$path    = ($base && substr($uriPath, 0, strlen($base)) === $base) ? substr($uriPath, strlen($base)) : $uriPath;
$path    = rtrim($path, '/') ?: '/';
if ($path === '/index.php') $path = '/';

$router->dispatch($method, $path);
