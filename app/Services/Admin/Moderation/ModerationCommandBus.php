<?php

declare(strict_types=1);

namespace App\Services\Admin\Moderation;

use Throwable;

final class ModerationCommandBus
{
    /** @var array<int, ModerationCommand> */
    private array $queue = [];

    public function __construct(
        private readonly ModerationAuthorizerInterface $authorizer,
        private readonly ModerationLoggerInterface $logger
    ) {
    }

    public function dispatch(ModerationCommand $command): ModerationCommandResult
    {
        $this->authorizer->authorize($command);

        $this->logger->info(sprintf('Dispatching moderation command "%s".', $command->name()));

        try {
            $result = $command->execute();
            $this->logger->info(
                sprintf('Command "%s" completed with status "%s".', $command->name(), $result->status()),
                ['command' => $command->name(), 'status' => $result->status()]
            );

            return $result;
        } catch (Throwable $exception) {
            $this->logger->error(
                sprintf('Command "%s" failed: %s', $command->name(), $exception->getMessage()),
                ['command' => $command->name(), 'exception' => $exception::class]
            );

            throw $exception;
        }
    }

    public function queue(ModerationCommand $command): void
    {
        $this->authorizer->authorize($command);
        $this->logger->info(sprintf('Queueing moderation command "%s".', $command->name()));
        $this->queue[] = $command;
    }

    /**
     * @return array<int, ModerationCommandResult>
     */
    public function flushQueue(): array
    {
        $results = [];
        while ($command = array_shift($this->queue)) {
            $results[] = $this->dispatch($command);
        }

        return $results;
    }

    public function queuedCount(): int
    {
        return count($this->queue);
    }

    /**
     * @return array<int, ModerationCommand>
     */
    public function queuedCommands(): array
    {
        return $this->queue;
    }

    public function dispatchWithRetry(ModerationCommand $command, int $maxAttempts = 2): ModerationCommandResult
    {
        $attempts = max(1, $maxAttempts);
        $current = 0;

        do {
            try {
                return $this->dispatch($command);
            } catch (Throwable $exception) {
                $current++;
                if ($current >= $attempts) {
                    throw $exception;
                }

                $this->logger->info(
                    sprintf(
                        'Retrying command "%s" (%d/%d).',
                        $command->name(),
                        $current + 1,
                        $attempts
                    ),
                    ['command' => $command->name(), 'attempt' => $current + 1, 'max_attempts' => $attempts]
                );
            }
        } while (true);
    }
}
