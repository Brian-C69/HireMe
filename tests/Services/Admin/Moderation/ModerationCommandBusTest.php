<?php

declare(strict_types=1);

require __DIR__ . '/../../../../vendor/autoload.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../../../../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Services\Admin\Moderation\AllowAllModerationAuthorizer;
use App\Services\Admin\Moderation\ArrayModerationLogger;
use App\Services\Admin\Moderation\ModerationCommand;
use App\Services\Admin\Moderation\ModerationCommandBus;
use App\Services\Admin\Moderation\ModerationCommandResult;
use App\Services\Admin\Moderation\RequiresModerationAuthorization;

class StubCommand implements ModerationCommand
{
    public int $executed = 0;

    private string $name;

    /** @var callable|null */
    private $callback;

    private string $status;

    public function __construct(string $name, ?callable $callback = null, string $status = 'success')
    {
        $this->name = $name;
        $this->callback = $callback;
        $this->status = $status;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function execute(): ModerationCommandResult
    {
        $this->executed++;
        if ($this->callback !== null) {
            $result = ($this->callback)($this);
            if ($result instanceof ModerationCommandResult) {
                return $result;
            }

            if ($result instanceof Throwable) {
                throw $result;
            }
        }

        return new ModerationCommandResult(
            $this->name(),
            $this->status,
            ['executed' => $this->executed],
            'Executed'
        );
    }
}

final class SensitiveStubCommand extends StubCommand implements RequiresModerationAuthorization
{
}

$logger = new ArrayModerationLogger();
$bus = new ModerationCommandBus(new AllowAllModerationAuthorizer(), $logger);

// Dispatch execution should run immediately and log entries.
$command = new StubCommand('demo');
$result = $bus->dispatch($command);

assert($result->status() === 'success', 'Dispatch should return success status.');
assert($result->data()['executed'] === 1, 'Command should have executed exactly once.');
assert(count($logger->logs('info')) >= 2, 'Logger should record informational messages.');

// Queue two commands and flush them in FIFO order.
$first = new StubCommand('first');
$second = new StubCommand('second');
$bus->queue($first);
$bus->queue($second);

assert($bus->queuedCount() === 2, 'Two commands should be queued.');
$queuedResults = $bus->flushQueue();

assert(count($queuedResults) === 2, 'Two results should be returned after flushing the queue.');
assert($first->executed === 1 && $second->executed === 1, 'Each queued command should run exactly once.');

// Retry behaviour: fail on first attempt, succeed on second.
$attempts = 0;
$retryingCommand = new StubCommand('retry', function () use (&$attempts): ModerationCommandResult {
    $attempts++;
    if ($attempts === 1) {
        throw new \RuntimeException('Transient failure');
    }

    return new ModerationCommandResult('retry', 'success', ['attempts' => $attempts], 'Recovered');
});

$retryResult = $bus->dispatchWithRetry($retryingCommand, 2);
assert($attempts === 2, 'Retry logic should execute command twice.');
assert($retryResult->data()['attempts'] === 2, 'Result should include number of attempts.');

// Ensure authorization failures bubble up for sensitive commands.
$denyingBus = new ModerationCommandBus(
    new class implements \App\Services\Admin\Moderation\ModerationAuthorizerInterface {
        public function authorize(ModerationCommand $command): void
        {
            if ($command instanceof RequiresModerationAuthorization) {
                throw new \InvalidArgumentException('Denied');
            }
        }
    },
    new ArrayModerationLogger()
);

$restricted = new SensitiveStubCommand('restricted');
$denied = false;
try {
    $denyingBus->dispatch($restricted);
} catch (\InvalidArgumentException $exception) {
    $denied = $exception->getMessage() === 'Denied';
}

assert($denied, 'Authorization should prevent sensitive command execution.');

echo "ModerationCommandBus tests passed\n";
