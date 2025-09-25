<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionNamedType;
use RuntimeException;

/**
 * Simple dependency injection container capable of resolving class
 * dependencies via reflection. Supports registering shared instances and
 * singleton factories while remaining lightweight for the application.
 */
final class Container
{
    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, array{factory: Closure, singleton: bool}> */
    private array $bindings = [];

    /**
     * Register a concrete value with the container.
     */
    public function set(string $id, mixed $value): void
    {
        $this->instances[$id] = $value;
        unset($this->bindings[$id]);
    }

    /**
     * Determine whether the container knows about the given id.
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances) || array_key_exists($id, $this->bindings);
    }

    /**
     * Retrieve a value from the container.
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (array_key_exists($id, $this->bindings)) {
            return $this->make($id);
        }

        return null;
    }

    /**
     * Register a shared instance with the container.
     */
    public function instance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
        unset($this->bindings[$id]);
    }

    /**
     * Register a singleton factory.
     */
    public function singleton(string $id, callable $factory): void
    {
        $this->bindings[$id] = [
            'factory' => $this->normalizeFactory($factory),
            'singleton' => true,
        ];
    }

    /**
     * Register a factory that creates a new instance each time.
     */
    public function bind(string $id, callable $factory): void
    {
        $this->bindings[$id] = [
            'factory' => $this->normalizeFactory($factory),
            'singleton' => false,
        ];
    }

    /**
     * Resolve an entry from the container.
     *
     * @throws RuntimeException When the entry cannot be resolved.
     */
    public function make(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (isset($this->bindings[$id])) {
            $binding = $this->bindings[$id];
            $value = $this->callFactory($binding['factory']);
            if ($binding['singleton']) {
                $this->instances[$id] = $value;
            }

            return $value;
        }

        if (!class_exists($id)) {
            throw new RuntimeException(sprintf('Class or binding "%s" is not resolvable.', $id));
        }

        $reflection = new ReflectionClass($id);
        if (!$reflection->isInstantiable()) {
            throw new RuntimeException(sprintf('Class "%s" is not instantiable.', $id));
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            $instance = $reflection->newInstance();
            $this->instances[$id] = $instance;

            return $instance;
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $name = $type->getName();

                if ($this->has($name)) {
                    $arguments[] = $this->make($name);
                    continue;
                }

                if (class_exists($name)) {
                    $arguments[] = $this->make($name);
                    continue;
                }

                if (interface_exists($name)) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $arguments[] = $parameter->getDefaultValue();
                        continue;
                    }

                    if ($type->allowsNull()) {
                        $arguments[] = null;
                        continue;
                    }

                    throw new RuntimeException(sprintf(
                        'Unable to resolve interface "%s" for "%s".',
                        $name,
                        $id
                    ));
                }

                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();
                    continue;
                }

                if ($type->allowsNull()) {
                    $arguments[] = null;
                    continue;
                }
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new RuntimeException(sprintf(
                'Unable to resolve parameter "%s" for "%s".',
                $parameter->getName(),
                $id
            ));
        }

        $instance = $reflection->newInstanceArgs($arguments);
        $this->instances[$id] = $instance;

        return $instance;
    }

    /**
     * Normalise a callable to a closure for later execution.
     */
    private function normalizeFactory(callable $factory): Closure
    {
        return $factory instanceof Closure ? $factory : Closure::fromCallable($factory);
    }

    /**
     * Execute a stored factory closure, injecting the container when required.
     */
    private function callFactory(Closure $factory): mixed
    {
        $reflection = new ReflectionFunction($factory);
        $arguments = $reflection->getNumberOfParameters() > 0 ? [$this] : [];

        return $factory(...$arguments);
    }
}
