<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Registry for shared middleware callables.
 */
final class Middleware
{
    /** @var array<int,callable> */
    private static array $stack = [];

    /**
     * Add a middleware to the global stack.
     */
    public static function add(callable $middleware): void
    {
        self::$stack[] = $middleware;
    }

    /**
     * Run all registered middleware.
     */
    public static function run(): void
    {
        foreach (self::$stack as $mw) {
            $mw();
        }
    }
}
