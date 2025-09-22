<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\Modules\ModuleRegistry;
use InvalidArgumentException;
use Throwable;

final class ModuleGatewayController extends ApiController
{
    private ModuleRegistry $registry;

    public function __construct(?ModuleRegistry $registry = null)
    {
        parent::__construct();

        if ($registry === null || $registry->get('user-management') === null) {
            $this->registry = ModuleRegistry::boot();
            return;
        }

        $this->registry = $registry;
    }

    public function handle(Request $request, string $function, string $type, ?string $id = null): Response
    {
        $service = $this->registry->get($function);
        if ($service === null) {
            return $this->error(sprintf('Unknown API module "%s".', $function), 404);
        }

        try {
            $payload = $service->handle($type, $id, $request);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (Throwable $e) {
            return $this->error('Module error: ' . $e->getMessage(), 500);
        }

        return $this->success($payload);
    }
}
