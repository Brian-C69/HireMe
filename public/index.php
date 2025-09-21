<?php

declare(strict_types=1);

use App\Core\Request;
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
use App\Controllers\RecruiterCompaniesController;
use App\Controllers\AdminController;
use App\Controllers\AccountController;
use App\Controllers\UserController;
use App\Controllers\Api\ResourceController;

$container = require dirname(__DIR__) . '/app/bootstrap.php';

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

$router->get('/privacy', [HomeController::class, 'privacy']);
$router->get('/terms',   [HomeController::class, 'terms']);

$router->post('/jobs/bulk', [JobController::class, 'bulk']);

$router->get('/jobs/{id}/applicants', [JobController::class, 'applicants']);
$router->post('/applications/{id}/status', [ApplicationController::class, 'updateStatus']);

$router->get('/credits',      [PaymentController::class, 'showCredits']);
$router->post('/credits/pay', [PaymentController::class, 'payCredits']);

// Recruiter: manage client companies
$router->get('/companies',                [RecruiterCompaniesController::class, 'index']);
$router->get('/companies/create',         [RecruiterCompaniesController::class, 'create']);
$router->post('/companies',               [RecruiterCompaniesController::class, 'store']);
$router->get('/companies/{id}/edit',      [RecruiterCompaniesController::class, 'edit']);
$router->post('/companies/{id}/edit',     [RecruiterCompaniesController::class, 'update']);
$router->post('/companies/{id}/delete',   [RecruiterCompaniesController::class, 'destroy']);
$router->post('/companies/bulk',          [RecruiterCompaniesController::class, 'bulk']);

// Admin
$router->get('/admin',                         [AdminController::class, 'dashboard']);
$router->get('/admin/tables',                  [AdminController::class, 'tables']);
$router->get('/admin/t/{table}',               [AdminController::class, 'browse']);
$router->get('/admin/t/{table}/create',        [AdminController::class, 'create']);
$router->post('/admin/t/{table}',              [AdminController::class, 'store']);
$router->get('/admin/t/{table}/{id}/edit',     [AdminController::class, 'edit']);
$router->post('/admin/t/{table}/{id}/edit',    [AdminController::class, 'update']);
$router->post('/admin/t/{table}/{id}/delete',  [AdminController::class, 'destroy']);
$router->get('/admin/candidates', [AdminController::class, 'candidatesIndex']);
$router->get('/admin/employers',  [AdminController::class, 'employersIndex']);
$router->get('/admin/recruiters', [AdminController::class, 'recruitersIndex']);

$router->get('/admin/candidates',                 [AdminController::class, 'candidatesIndex']);
$router->get('/admin/candidates/create',          [AdminController::class, 'candidatesCreate']);
$router->post('/admin/candidates',                 [AdminController::class, 'candidatesStore']);
$router->get('/admin/candidates/{id}/edit',       [AdminController::class, 'candidatesEdit']);
$router->post('/admin/candidates/{id}/edit',       [AdminController::class, 'candidatesUpdate']);
$router->post('/admin/candidates/{id}/delete',     [AdminController::class, 'candidatesDestroy']);
$router->post('/admin/candidates/bulk',            [AdminController::class, 'candidatesBulk']);

$router->get('/admin/employers',                 [AdminController::class, 'employersIndex']);
$router->get('/admin/employers/create',          [AdminController::class, 'employersCreate']);
$router->post('/admin/employers',                 [AdminController::class, 'employersStore']);
$router->get('/admin/employers/{id}/edit',       [AdminController::class, 'employersEdit']);
$router->post('/admin/employers/{id}/edit',       [AdminController::class, 'employersUpdate']);
$router->post('/admin/employers/{id}/delete',     [AdminController::class, 'employersDestroy']);
$router->post('/admin/employers/bulk',            [AdminController::class, 'employersBulk']);

$router->get('/admin/recruiters',               [AdminController::class, 'recruitersIndex']);
$router->get('/admin/recruiters/create',        [AdminController::class, 'recruitersCreate']);
$router->post('/admin/recruiters',               [AdminController::class, 'recruitersStore']);
$router->get('/admin/recruiters/{id}/edit',     [AdminController::class, 'recruitersEdit']);
$router->post('/admin/recruiters/{id}/edit',     [AdminController::class, 'recruitersUpdate']);
$router->post('/admin/recruiters/{id}/delete',   [AdminController::class, 'recruitersDestroy']);
$router->post('/admin/recruiters/bulk',          [AdminController::class, 'recruitersBulk']);

$router->get('/admin/jobs',               [AdminController::class, 'jobsIndex']);
$router->get('/admin/jobs/create',        [AdminController::class, 'jobsCreate']);
$router->post('/admin/jobs',               [AdminController::class, 'jobsStore']);
$router->get('/admin/jobs/{id}/edit',     [AdminController::class, 'jobsEdit']);
$router->post('/admin/jobs/{id}/edit',     [AdminController::class, 'jobsUpdate']);
$router->post('/admin/jobs/{id}/delete',   [AdminController::class, 'jobsDestroy']);
$router->post('/admin/jobs/bulk',          [AdminController::class, 'jobsBulk']);

$router->get('/admin/verifications',                 [AdminController::class, 'verifIndex']);
$router->get('/admin/verifications/{id}',            [AdminController::class, 'verifShow']);
$router->post('/admin/verifications/{id}/approve',    [AdminController::class, 'verifApprove']);
$router->post('/admin/verifications/{id}/reject',     [AdminController::class, 'verifReject']);
$router->post('/admin/verifications/bulk',            [AdminController::class, 'verifBulk']);

// Credits
$router->get('/credits',              [PaymentController::class, 'showCredits']);
$router->post('/credits/checkout',    [PaymentController::class, 'checkoutCredits']);
$router->get('/credits/success',      [PaymentController::class, 'creditsSuccess']);
$router->get('/credits/cancel',       [PaymentController::class, 'creditsCancel']);

// Stripe webhook (NO CSRF!)
$router->post('/webhooks/stripe',     [PaymentController::class, 'webhook']);

$router->get('/admin/credits',  [\App\Controllers\AdminController::class, 'creditsIndex']);
$router->post('/admin/credits/adjust', [\App\Controllers\AdminController::class, 'creditsAdjust']);
$router->get('/admin/payments', [\App\Controllers\AdminController::class, 'paymentsIndex']);

// Admin accounts CRUD
$router->get('/admin/admins',             [\App\Controllers\AdminController::class, 'adminsIndex']);
$router->get('/admin/admins/create',      [\App\Controllers\AdminController::class, 'adminsCreate']);
$router->post('/admin/admins',             [\App\Controllers\AdminController::class, 'adminsStore']);
$router->get('/admin/admins/{id}/edit',   [\App\Controllers\AdminController::class, 'adminsEdit']);
$router->post('/admin/admins/{id}/edit',   [\App\Controllers\AdminController::class, 'adminsUpdate']);
$router->post('/admin/admins/{id}/delete', [\App\Controllers\AdminController::class, 'adminsDelete']);

// Admin “My Profile”
$router->get('/admin/profile',            [\App\Controllers\AdminController::class, 'adminProfile']);
$router->post('/admin/profile',            [\App\Controllers\AdminController::class, 'adminProfileUpdate']);

$router->get('/admin/overview', [\App\Controllers\AdminController::class, 'overviewMetrics']);
$router->get('/admin/metrics',  [\App\Controllers\AdminController::class, 'overviewMetrics']); // alias
$router->get('/admin/metrics/export', [\App\Controllers\AdminController::class, 'metricsExport']); // CSV

$router->get('/admin/overview/export-all', [\App\Controllers\AdminController::class, 'overviewExportAll']);

// API: Users
$router->get('/api/users/{type}', [UserController::class, 'index']);
$router->get('/api/users/{type}/{id}', [UserController::class, 'show']);
$router->post('/api/users/{type}', [UserController::class, 'store']);

// API: Accounts
$router->get('/api/accounts/{type}/{id}', [AccountController::class, 'apiShow']);
$router->post('/api/accounts/{type}', [AccountController::class, 'apiCreate']);

// API: Generic CRUD exposure
$router->get('/api/{resource}', [ResourceController::class, 'index'], ['json' => true]);
$router->get('/api/{resource}/{id}', [ResourceController::class, 'show'], ['json' => true]);
$router->post('/api/{resource}', [ResourceController::class, 'store'], ['json' => true]);
$router->put('/api/{resource}/{id}', [ResourceController::class, 'update'], ['json' => true]);
$router->patch('/api/{resource}/{id}', [ResourceController::class, 'update'], ['json' => true]);
$router->delete('/api/{resource}/{id}', [ResourceController::class, 'destroy'], ['json' => true]);

$request = Request::fromGlobals(defined('BASE_URL') ? BASE_URL : null);
$response = $router->dispatch($request, $container);
$response->send();
