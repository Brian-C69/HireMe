<?php

declare(strict_types=1);

namespace App\Services\Payment\Events;

use App\Services\Payment\PaymentEvent;

/**
 * Basic immutable payment event implementation used by the processor.
 */
final class GenericPaymentEvent implements PaymentEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(private readonly string $name, private readonly array $payload)
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function payload(): array
    {
        return $this->payload;
    }
}
