<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Base controller providing convenience helpers for dependency resolution.
 *
 * Controllers that are resolved through the router will have the application
 * container injected automatically allowing child classes to fetch
 * dependencies when required. The container is optional so controllers can
 * still be manually instantiated in tests or scripts.
 */
abstract class Controller
{
    protected ?Container $container = null;

    public function __construct(?Container $container = null)
    {
        if ($container !== null) {
            $this->setContainer($container);
        }
    }

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    protected function hasContainer(): bool
    {
        return $this->container instanceof Container;
    }

    protected function container(): Container
    {
        if (!$this->hasContainer()) {
            throw new RuntimeException(sprintf('No container has been set on %s.', static::class));
        }

        return $this->container;
    }

    protected function make(string $id): mixed
    {
        return $this->container()->make($id);
    }

    protected function resolve(string $id): mixed
    {
        $container = $this->container();

        if ($container->has($id)) {
            return $container->get($id);
        }

        return $container->make($id);
    }
}
