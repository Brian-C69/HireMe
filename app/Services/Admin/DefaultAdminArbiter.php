<?php

declare(strict_types=1);

namespace App\Services\Admin;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonException;
use RuntimeException;
use Throwable;

final class DefaultAdminArbiter implements AdminArbiterInterface
{
    /** @var array<int, callable(string, array<string, mixed>): void> */
    private array $listeners = [];

    /** @var array<int, array{event: string, payload: array<string, mixed>}> */
    private array $queue = [];

    private string $logPath;

    public function __construct(?callable $listener = null, ?string $logPath = null)
    {
        $this->logPath = $logPath ?? dirname(__DIR__, 3) . '/storage/moderation/arbiter-events.log';
        $this->registerListener(function (string $event, array $payload): void {
            $this->writeLog($event, $payload);
        });

        if ($listener !== null) {
            $this->registerListener($listener);
        }
    }

    public function dispatch(string $event, array $payload = []): void
    {
        $this->queue[] = ['event' => $event, 'payload' => $payload];
    }

    public function flush(): void
    {
        while ($this->queue !== []) {
            $current = array_shift($this->queue);
            if ($current === null) {
                continue;
            }

            foreach ($this->listeners as $listener) {
                try {
                    $listener($current['event'], $current['payload']);
                } catch (Throwable $exception) {
                    error_log(sprintf(
                        'Admin arbiter listener failure for "%s": %s',
                        $current['event'],
                        $exception->getMessage()
                    ));
                }
            }
        }
    }

    public function registerListener(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    private function writeLog(string $event, array $payload): void
    {
        $record = [
            'event' => $event,
            'payload' => $payload,
            'dispatched_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM),
        ];

        $dir = dirname($this->logPath);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create moderation event directory.');
        }

        try {
            $json = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode admin arbiter event.', 0, $exception);
        }

        file_put_contents($this->logPath, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public function __destruct()
    {
        $this->flush();
    }
}
