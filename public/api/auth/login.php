<?php
declare(strict_types=1);

use App\Controllers\Api\AuthApiController;

require dirname(__DIR__, 3) . '/app/bootstrap.php';

$controller = new AuthApiController();
$controller->login();
