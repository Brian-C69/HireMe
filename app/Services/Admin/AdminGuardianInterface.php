<?php

declare(strict_types=1);

namespace App\Services\Admin;

/**
 * The synchronous guardian role that enforces policy, moderation and audit
 * checks for administrative interactions.
 */
interface AdminGuardianInterface
{
    /**
     * Assert that the current actor may read from the given resource.
     *
     * @param array<string, mixed> $context
     */
    public function assertRead(string $resource, array $context = []): void;

    /**
     * Assert that the current actor may write to the given resource.
     *
     * @param array<string, mixed> $context
     */
    public function assertWrite(string $resource, array $context = []): void;

    /**
     * Record an audit event for informational purposes.
     *
     * @param array<string, mixed> $context
     */
    public function audit(string $action, array $context = []): void;

    /**
     * Raise a flag for follow-up review.
     *
     * @param array<string, mixed> $context
     */
    public function flag(string $resource, array $context = []): void;
}
