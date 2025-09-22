<?php

declare(strict_types=1);

namespace App\Services\Admin\Moderation;

final class ArrayModerationLogger implements ModerationLoggerInterface
{
    /** @var array<int, array{level: string, message: string, context: array<string, mixed>}> */
    private array $logs = [];

    public function info(string $message, array $context = []): void
    {
        $this->logs[] = ['level' => 'info', 'message' => $message, 'context' => $context];
    }

    public function error(string $message, array $context = []): void
    {
        $this->logs[] = ['level' => 'error', 'message' => $message, 'context' => $context];
    }

    /**
     * @return array<int, array{level: string, message: string, context: array<string, mixed>}> 
     */
    public function logs(?string $level = null): array
    {
        if ($level === null) {
            return $this->logs;
        }

        return array_values(array_filter(
            $this->logs,
            static fn (array $entry): bool => $entry['level'] === strtolower($level)
        ));
    }
}
