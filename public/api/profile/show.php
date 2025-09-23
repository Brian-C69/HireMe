<?php
declare(strict_types=1);

use App\Controllers\Api\ProfileApiController;

require dirname(__DIR__, 3) . '/app/bootstrap.php';

$controller = new ProfileApiController();
$controller->show();
