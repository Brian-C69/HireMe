<?php

declare(strict_types=1);

namespace App\Core\Security;

final class RateLimiter
{
    private const STORAGE_DIR = '/storage/cache/ratelimiter';

    /**
     * Register a hit for the given key.
     *
     * @return array{allowed:bool, attempts:int, remaining:int, retry_after:int}
     */
    public static function hit(string $key, int $maxAttempts, int $decaySeconds): array
    {
        $maxAttempts = max(1, $maxAttempts);
        $decaySeconds = max(1, $decaySeconds);
        $now = time();
        $file = self::filePath($key);
        $data = self::read($file);

        if ($data !== null && ($data['expires_at'] ?? 0) <= $now) {
            $data = null;
            @unlink($file);
        }

        if ($data === null) {
            $data = [
                'attempts' => 0,
                'expires_at' => $now + $decaySeconds,
            ];
        }

        if ($data['attempts'] >= $maxAttempts) {
            $retryAfter = max(1, ($data['expires_at'] ?? $now) - $now);
            return [
                'allowed' => false,
                'attempts' => (int) $data['attempts'],
                'remaining' => 0,
                'retry_after' => $retryAfter,
            ];
        }

        $data['attempts']++;
        self::write($file, $data);

        $retryAfter = max(1, ($data['expires_at'] ?? $now) - $now);
        $remaining = max(0, $maxAttempts - (int) $data['attempts']);

        return [
            'allowed' => true,
            'attempts' => (int) $data['attempts'],
            'remaining' => $remaining,
            'retry_after' => $retryAfter,
        ];
    }

    private static function storageDirectory(): string
    {
        $dir = dirname(__DIR__, 3) . self::STORAGE_DIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private static function filePath(string $key): string
    {
        return self::storageDirectory() . '/' . hash('sha256', $key) . '.json';
    }

    /**
     * @return array{attempts:int, expires_at:int}|null
     */
    private static function read(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }

        $contents = file_get_contents($file);
        if ($contents === false || $contents === '') {
            return null;
        }

        $data = json_decode($contents, true);
        if (!is_array($data)) {
            return null;
        }

        $attempts = isset($data['attempts']) ? (int) $data['attempts'] : 0;
        $expiresAt = isset($data['expires_at']) ? (int) $data['expires_at'] : 0;

        if ($attempts < 0) {
            $attempts = 0;
        }

        return [
            'attempts' => $attempts,
            'expires_at' => $expiresAt,
        ];
    }

    private static function write(string $file, array $data): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $json = json_encode([
            'attempts' => (int) ($data['attempts'] ?? 0),
            'expires_at' => (int) ($data['expires_at'] ?? 0),
        ], JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return;
        }

        file_put_contents($file, $json, LOCK_EX);
    }
}
