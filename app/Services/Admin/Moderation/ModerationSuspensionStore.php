<?php

declare(strict_types=1);

namespace App\Services\Admin\Moderation;

use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use RuntimeException;

final class ModerationSuspensionStore
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $base = $path ?? dirname(__DIR__, 4) . '/storage/moderation/suspensions.json';
        $this->path = $base;
    }

    /**
     * @return array<string, mixed>
     */
    public function suspend(
        string $role,
        int $userId,
        ?DateTimeInterface $until = null,
        ?string $reason = null,
        ?int $moderatorId = null
    ): array {
        $records = $this->load();
        $key = $this->key($role, $userId);
        $now = new DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $record = [
            'role' => strtolower($role),
            'user_id' => $userId,
            'reason' => $reason,
            'until' => $until?->format(DateTimeInterface::ATOM),
            'moderator_id' => $moderatorId,
            'created_at' => $records[$key]['created_at'] ?? $now->format(DateTimeInterface::ATOM),
            'updated_at' => $now->format(DateTimeInterface::ATOM),
        ];

        $records[$key] = $record;
        $this->persist($records);

        return $record;
    }

    public function reinstate(string $role, int $userId): ?array
    {
        $records = $this->load();
        $key = $this->key($role, $userId);
        if (!isset($records[$key])) {
            return null;
        }

        $previous = $records[$key];
        unset($records[$key]);
        $this->persist($records);

        return $previous;
    }

    public function get(string $role, int $userId): ?array
    {
        $records = $this->load();
        return $records[$this->key($role, $userId)] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_values($this->load());
    }

    public function count(): int
    {
        return count($this->load());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function load(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $contents = file_get_contents($this->path);
        if ($contents === false || $contents === '') {
            return [];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to decode suspension store.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Failed to decode suspension store.');
        }

        /** @var array<string, array<string, mixed>> $decoded */
        return $decoded;
    }

    /**
     * @param array<string, array<string, mixed>> $records
     */
    private function persist(array $records): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create moderation storage directory.');
        }

        $json = json_encode($records, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->path, $json, LOCK_EX);
    }

    private function key(string $role, int $userId): string
    {
        return strtolower($role) . ':' . $userId;
    }
}
