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

use App\Core\Container;
use App\Core\Request;
use App\Core\Router;

final class LegacyParamsController
{
    public array $lastParams = [];

    public function index(array $params = []): string
    {
        $this->lastParams = $params;

        return 'jobs ok';
    }

    public function show(array $params = []): string
    {
        $this->lastParams = $params;

        return 'job ' . ($params['id'] ?? '');
    }
}

$container = new Container();
$router = new Router();

$controller = new LegacyParamsController();
$container->instance(LegacyParamsController::class, $controller);

$router->get('/jobs', [LegacyParamsController::class, 'index']);
$router->get('/jobs/{id}', [LegacyParamsController::class, 'show']);

$baseServer = [
    'SCRIPT_NAME' => '/index.php',
    'SERVER_PROTOCOL' => 'HTTP/1.1',
    'HTTP_ACCEPT' => 'text/html',
];

$requestIndex = new Request(
    [],
    [],
    [],
    [],
    $baseServer + [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/jobs',
    ]
);

$responseIndex = $router->dispatch($requestIndex, $container);

assert($responseIndex->status() === 200, 'Legacy index controller should return a successful response.');
assert($responseIndex->body() === 'jobs ok', 'Legacy index controller should return its string response.');
assert($controller->lastParams === [], 'Legacy index controller should receive an empty params array.');

$requestShow = new Request(
    [],
    [],
    [],
    [],
    $baseServer + [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/jobs/123',
    ]
);

$responseShow = $router->dispatch($requestShow, $container);

assert($responseShow->status() === 200, 'Legacy show controller should return a successful response.');
assert($responseShow->body() === 'job 123', 'Legacy show controller should embed the route parameter.');
assert($controller->lastParams === ['id' => '123'], 'Legacy show controller should receive route parameters in the params array.');

echo "Router legacy params test passed\n";
