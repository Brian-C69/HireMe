<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Services\Admin\Moderation\ErrorLogModerationLogger;
use App\Services\Admin\Moderation\ModerationAuthorizationException;
use App\Services\Admin\Moderation\ModerationLoggerInterface;
use App\Services\Admin\Moderation\ModerationSuspensionStore;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class DefaultAdminGuardian implements AdminGuardianInterface
{
    public function __construct(
        private readonly ModerationSuspensionStore $suspensions = new ModerationSuspensionStore(),
        private readonly ModerationLoggerInterface $logger = new ErrorLogModerationLogger()
    ) {
    }

    public function assertRead(string $resource, array $context = []): void
    {
        $this->audit(sprintf('read:%s', $resource), $context);
        $this->guardAgainstSuspension($context, $resource, 'read');
    }

    public function assertWrite(string $resource, array $context = []): void
    {
        $this->audit(sprintf('write:%s', $resource), $context);
        $this->guardAgainstSuspension($context, $resource, 'write');
    }

    public function audit(string $action, array $context = []): void
    {
        $this->logger->info('admin.guardian.' . $action, $context);
    }

    public function flag(string $resource, array $context = []): void
    {
        $this->logger->error(sprintf('admin.guardian.flag:%s', $resource), $context);
    }

    private function guardAgainstSuspension(array $context, string $resource, string $operation): void
    {
        $role = $this->normaliseRole($context['actor_role'] ?? $context['role'] ?? null);
        $userId = $this->normaliseId($context['actor_id'] ?? $context['user_id'] ?? null);
        if ($role === null || $userId === null) {
            return;
        }

        $record = $this->suspensions->get($role, $userId);
        if ($record === null) {
            return;
        }

        if ($this->suspensionExpired($record)) {
            $this->suspensions->reinstate($role, $userId);
            $this->logger->info('admin.guardian.suspension-expired', [
                'role' => $role,
                'user_id' => $userId,
                'resource' => $resource,
                'operation' => $operation,
            ] + $context);
            return;
        }

        $this->logger->error('admin.guardian.blocked', [
            'role' => $role,
            'user_id' => $userId,
            'resource' => $resource,
            'operation' => $operation,
            'suspension' => $record,
        ] + $context);

        throw new ModerationAuthorizationException('This action is blocked pending administrative review.');
    }

    /**
     * @param array<string, mixed> $record
     */
    private function suspensionExpired(array $record): bool
    {
        $expiresAt = $record['until'] ?? null;
        if (!is_string($expiresAt) || trim($expiresAt) === '') {
            return false;
        }

        try {
            $until = new DateTimeImmutable($expiresAt, new DateTimeZone('UTC'));
        } catch (Throwable) {
            return false;
        }

        return $until < new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    private function normaliseRole(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : strtolower($trimmed);
    }

    private function normaliseId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && ctype_digit($value)) {
            $int = (int) $value;
            return $int > 0 ? $int : null;
        }

        return null;
    }
}
