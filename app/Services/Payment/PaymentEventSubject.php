<?php

declare(strict_types=1);

namespace App\Services\Payment;

interface PaymentEventSubject
{
    public function attach(string $eventName, PaymentObserver $observer): void;

    public function detach(string $eventName, PaymentObserver $observer): void;

    public function notify(PaymentEvent $event): void;
}
