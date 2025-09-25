<?php

declare(strict_types=1);

namespace App\Services\Admin;

/**
 * The asynchronous arbiter role that fans out moderation decisions to the rest
 * of the platform via events or webhooks.
 */
interface AdminArbiterInterface
{
    /**
     * Queue an event for asynchronous dispatch.
     *
     * @param array<string, mixed> $payload
     */
    public function dispatch(string $event, array $payload = []): void;

    /**
     * Flush any queued events to their listeners.
     */
    public function flush(): void;
}
