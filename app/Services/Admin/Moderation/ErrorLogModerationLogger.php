<?php

declare(strict_types=1);

namespace App\Services\Admin\Moderation;

final class ErrorLogModerationLogger implements ModerationLoggerInterface
{
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function write(string $level, string $message, array $context): void
    {
        $contextString = $context === [] ? '' : ' ' . json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        error_log(sprintf('[Moderation][%s] %s%s', $level, $message, $contextString));
    }
}
