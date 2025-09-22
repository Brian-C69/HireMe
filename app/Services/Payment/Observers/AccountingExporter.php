<?php

declare(strict_types=1);

namespace App\Services\Payment\Observers;

use App\Services\Payment\PaymentEvent;
use App\Services\Payment\PaymentObserver;
use App\Services\Payment\PaymentProcessor;

final class AccountingExporter implements PaymentObserver
{
    public function __construct(private readonly string $logFile)
    {
    }

    public function handle(PaymentEvent $event): void
    {
        if (!in_array($event->name(), [
            PaymentProcessor::EVENT_INVOICE_PAID,
            PaymentProcessor::EVENT_PAYMENT_FAILED,
        ], true)) {
            return;
        }

        $this->ensureDirectory();

        $payload = $event->payload();
        $record = [
            'event' => $event->name(),
            'user_id' => $payload['user_id'] ?? null,
            'user_type' => $payload['user_type'] ?? null,
            'amount' => $payload['amount'] ?? null,
            'purpose' => $payload['purpose'] ?? null,
            'transaction_status' => $payload['transaction_status'] ?? null,
            'transaction_id' => $payload['transaction_id'] ?? null,
            'recorded_at' => date('c'),
        ];

        file_put_contents($this->logFile, json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
    }

    private function ensureDirectory(): void
    {
        $directory = dirname($this->logFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }
}
