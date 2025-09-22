<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Describes a payment-related event emitted by the payment processor.
 */
interface PaymentEvent
{
    /**
     * Name of the event being emitted (e.g. "invoice_paid").
     */
    public function name(): string;

    /**
     * Contextual payload associated with the event.
     *
     * @return array<string, mixed>
     */
    public function payload(): array;
}
