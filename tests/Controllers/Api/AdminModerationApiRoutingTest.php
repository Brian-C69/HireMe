<?php

declare(strict_types=1);

use App\Controllers\Api\ResourceController;
use App\Core\Request;
use App\Services\Modules\ModuleRegistry;
use App\Services\Modules\ModuleServiceInterface;

require __DIR__ . '/../../../vendor/autoload.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../../../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative = substr($class, $len);
    $file = $baseDir . str_replace('\\\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

final class StubAdminModerationService implements ModuleServiceInterface
{
    public function name(): string
    {
        return 'admin-moderation';
    }

    public function handle(string $type, ?string $id, Request $request): array
    {
        return [
            'module' => 'admin-moderation',
            'type' => $type,
            'id' => $id,
        ];
    }
}

final class StubUserManagementService implements ModuleServiceInterface
{
    public function name(): string
    {
        return 'user-management';
    }

    public function handle(string $type, ?string $id, Request $request): array
    {
        return ['module' => 'user-management'];
    }
}

$request = new Request(
    [],
    [],
    [],
    [],
    [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/api/admin-moderation/overview',
        'HTTP_ACCEPT' => 'application/json',
        'REMOTE_ADDR' => '127.0.0.1',
    ]
);

$registry = new ModuleRegistry();
$registry->register(new StubAdminModerationService());
$registry->register(new StubUserManagementService());

$controller = new ResourceController();
$controller->setModuleRegistry($registry);

$response = $controller->show($request, 'admin-moderation', 'overview');

assert($response->status() === 200, 'Module fallback should return a successful response.');

$payload = json_decode($response->body(), true);
if (!is_array($payload)) {
    throw new RuntimeException('Module fallback response should be valid JSON.');
}

$assertedModule = $payload['data']['module'] ?? null;
$assertedType = $payload['data']['type'] ?? null;

assert($assertedModule === 'admin-moderation', 'Module fallback should expose the admin moderation module key.');
assert($assertedType === 'overview', 'Module fallback should forward the requested operation.');

echo "Admin moderation API routing test passed\n";
