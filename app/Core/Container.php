<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Simple dependency injection container.
 */
final class Container
{
    /** @var array<string,mixed> */
    private array $entries = [];

    /**
     * Register a value or factory with the container.
     *
     * @param string $id
     * @param mixed $value
     */
    public function set(string $id, mixed $value): void
    {
        $this->entries[$id] = $value;
    }

    /**
     * Retrieve a value from the container.
     *
     * @param string $id
     * @return mixed
     */
    public function get(string $id): mixed
    {
        return $this->entries[$id] ?? null;
    }
}
