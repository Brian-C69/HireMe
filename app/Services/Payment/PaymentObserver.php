<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * Contract for observers that want to react to payment events.
 */
interface PaymentObserver
{
    public function handle(PaymentEvent $event): void;
}
