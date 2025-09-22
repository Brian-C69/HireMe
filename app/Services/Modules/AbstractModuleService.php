<?php

declare(strict_types=1);

namespace App\Services\Modules;

use App\Core\Request;
use InvalidArgumentException;
use RuntimeException;

abstract class AbstractModuleService implements ModuleServiceInterface, RegistryAwareInterface
{
    protected ModuleRegistry $registry;

    public function setRegistry(ModuleRegistry $registry): void
    {
        $this->registry = $registry;
    }

    /**
     * Forward a call to another module via the registry.
     *
     * @param string      $module Target module key or alias.
     * @param string      $type   Operation to perform on the target module.
     * @param string|null $id     Optional identifier to pass through.
     * @param array<string, mixed> $query Optional query parameters.
     * @param array<string, mixed> $body  Optional payload/body data.
     * @param string $method HTTP method to emulate when calling the target service.
     *
     * @return array<string, mixed>
     */
    protected function forward(
        string $module,
        string $type,
        ?string $id = null,
        array $query = [],
        array $body = [],
        string $method = 'GET'
    ): array {
        $this->ensureRegistry();

        return $this->registry->call($module, $type, $id, $query, $body, $method);
    }

    /**
     * Ensure that the registry has been injected before attempting to use it.
     */
    protected function ensureRegistry(): void
    {
        if (!isset($this->registry)) {
            throw new RuntimeException('Module registry has not been initialised for ' . static::class);
        }
    }

    /**
     * Helper for building a data response with an automatic module identifier.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    protected function respond(array $payload): array
    {
        $payload['module'] = $this->name();
        return $payload;
    }

    /**
     * Resolve an ID passed via the URL.
     */
    protected function requireId(?string $id, string $message = 'A resource identifier is required.'): string
    {
        if ($id === null || $id === '') {
            throw new InvalidArgumentException($message);
        }

        return $id;
    }

    /**
     * Convert an ID into an integer and assert it is valid.
     */
    protected function requireIntId(?string $id, string $message = 'A valid identifier is required.'): int
    {
        $id = $this->requireId($id, $message);
        if (!ctype_digit($id)) {
            throw new InvalidArgumentException($message);
        }

        return (int) $id;
    }

    /**
     * Convenience helper for accessing query parameters with a fallback.
     */
    protected function query(Request $request, string $key, ?string $fallback = null): ?string
    {
        $value = $request->query($key);
        if ($value === null) {
            return $fallback;
        }

        return is_string($value) ? $value : $fallback;
    }
}
