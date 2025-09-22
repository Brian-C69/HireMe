<?php

declare(strict_types=1);

namespace App\Services\Payment\Observers;

use App\Models\Billing;
use App\Services\Payment\PaymentEvent;
use App\Services\Payment\PaymentObserver;
use App\Services\Payment\PaymentProcessor;

final class InvoiceUpdater implements PaymentObserver
{
    public function handle(PaymentEvent $event): void
    {
        if (!in_array($event->name(), [
            PaymentProcessor::EVENT_INVOICE_PAID,
            PaymentProcessor::EVENT_PAYMENT_FAILED,
        ], true)) {
            return;
        }

        $payload = $event->payload();
        $status = $event->name() === PaymentProcessor::EVENT_INVOICE_PAID ? 'paid' : 'failed';
        $transactionDate = (string) ($payload['processed_at'] ?? date('Y-m-d H:i:s'));

        $attributes = [
            'user_id' => (int) ($payload['user_id'] ?? 0),
            'user_type' => (string) ($payload['user_type'] ?? ''),
        ];

        $reference = $payload['transaction_id'] ?? null;
        if (is_string($reference) && $reference !== '') {
            $attributes['reference_number'] = $reference;
        }

        $billing = $this->findExistingBilling($payload, $attributes);
        $billing->fill(array_merge($attributes, [
            'transaction_type' => $this->normaliseTransactionType((string) ($payload['purpose'] ?? '')),
            'amount' => (float) ($payload['amount'] ?? 0),
            'payment_method' => (string) ($payload['payment_method'] ?? 'manual'),
            'status' => $status,
            'transaction_date' => $transactionDate,
        ]));

        $billing->save();
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $attributes
     */
    private function findExistingBilling(array $payload, array $attributes): Billing
    {
        if (isset($payload['billing_id']) && is_numeric($payload['billing_id'])) {
            $existing = Billing::find((int) $payload['billing_id']);
            if ($existing instanceof Billing) {
                return $existing;
            }
        }

        $query = Billing::query()
            ->where('user_id', $attributes['user_id'] ?? 0)
            ->where('user_type', $attributes['user_type'] ?? '');

        if (isset($attributes['reference_number'])) {
            $query->where('reference_number', $attributes['reference_number']);
        }

        $existing = $query->orderByDesc('transaction_date')->first();
        if ($existing instanceof Billing) {
            return $existing;
        }

        $billing = new Billing();
        $billing->fill($attributes);
        $billing->setAttribute('transaction_date', date('Y-m-d H:i:s'));

        return $billing;
    }

    private function normaliseTransactionType(string $purpose): string
    {
        return match (strtolower($purpose)) {
            'resume credits', 'credits', 'credit', 'credit purchase' => 'Resume Credits',
            'premium badge', 'premium', 'badge' => 'Premium Badge',
            default => $purpose !== '' ? ucfirst($purpose) : 'Subscription',
        };
    }
}
