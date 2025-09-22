<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Payment;
use App\Services\Payment\Events\GenericPaymentEvent;
use InvalidArgumentException;
use SplObjectStorage;

final class PaymentProcessor implements PaymentEventSubject
{
    public const EVENT_INVOICE_PAID = 'invoice_paid';
    public const EVENT_PAYMENT_FAILED = 'payment_failed';
    public const EVENT_PAYMENT_PENDING = 'payment_pending';

    /** @var array<string, SplObjectStorage<PaymentObserver, null>> */
    private array $observers = [];

    /**
     * @param array<string, iterable<PaymentObserver>> $observers
     */
    public function __construct(array $observers = [])
    {
        foreach ($observers as $event => $listeners) {
            foreach ($listeners as $observer) {
                $this->attach($event, $observer);
            }
        }
    }

    public static function withDefaultObservers(?string $logFile = null): self
    {
        $logFile ??= dirname(__DIR__, 3) . '/storage/logs/accounting.log';

        $invoiceUpdater = new \App\Services\Payment\Observers\InvoiceUpdater();
        $subscriptionManager = new \App\Services\Payment\Observers\SubscriptionStateManager();
        $accountingExporter = new \App\Services\Payment\Observers\AccountingExporter($logFile);

        $processor = new self();
        $processor->attach(self::EVENT_INVOICE_PAID, $invoiceUpdater);
        $processor->attach(self::EVENT_PAYMENT_FAILED, $invoiceUpdater);
        $processor->attach(self::EVENT_INVOICE_PAID, $subscriptionManager);
        $processor->attach(self::EVENT_INVOICE_PAID, $accountingExporter);
        $processor->attach(self::EVENT_PAYMENT_FAILED, $accountingExporter);

        return $processor;
    }

    public function attach(string $eventName, PaymentObserver $observer): void
    {
        $eventName = $this->normaliseEvent($eventName);
        $this->observers[$eventName] ??= new SplObjectStorage();
        $this->observers[$eventName]->attach($observer);
    }

    public function detach(string $eventName, PaymentObserver $observer): void
    {
        $eventName = $this->normaliseEvent($eventName);
        if (!isset($this->observers[$eventName])) {
            return;
        }

        $this->observers[$eventName]->detach($observer);
        if (count($this->observers[$eventName]) === 0) {
            unset($this->observers[$eventName]);
        }
    }

    public function notify(PaymentEvent $event): void
    {
        $names = [$this->normaliseEvent($event->name())];
        if (isset($this->observers['*'])) {
            $names[] = '*';
        }

        foreach ($names as $name) {
            if (!isset($this->observers[$name])) {
                continue;
            }

            /** @var PaymentObserver $observer */
            foreach ($this->observers[$name] as $observer) {
                $observer->handle($event);
            }
        }
    }

    /**
     * Process an incoming payment payload and broadcast the outcome.
     *
     * @param array<string, mixed> $attributes
     */
    public function process(array $attributes): PaymentEvent
    {
        $userId = $this->extractUserId($attributes);
        $userType = $this->normaliseUserType((string) ($attributes['user_type'] ?? ''));
        $purpose = $this->normalisePurpose((string) ($attributes['purpose'] ?? ''));
        $status = $this->normaliseStatus((string) ($attributes['transaction_status'] ?? ($attributes['status'] ?? '')));
        $amount = $this->normaliseAmount($attributes['amount'] ?? 0);
        $paymentMethod = $this->normaliseString($attributes['payment_method'] ?? 'manual');
        $transactionId = $this->normaliseTransactionId($attributes['transaction_id'] ?? null);
        $metadata = $this->normaliseMetadata($attributes['metadata'] ?? null);
        $processedAt = $attributes['processed_at'] ?? date('Y-m-d H:i:s');
        $billingId = isset($attributes['billing_id']) && is_numeric($attributes['billing_id'])
            ? (int) $attributes['billing_id']
            : null;

        $payment = Payment::create([
            'user_type' => $userType,
            'user_id' => $userId,
            'amount' => $amount,
            'purpose' => $purpose,
            'payment_method' => $paymentMethod,
            'transaction_status' => $status,
            'transaction_id' => $transactionId,
        ]);

        $eventName = $this->eventNameForStatus($status);

        $event = new GenericPaymentEvent($eventName, [
            'payment' => $payment,
            'user_id' => $userId,
            'user_type' => $userType,
            'amount' => $amount,
            'purpose' => $purpose,
            'raw_purpose' => $attributes['purpose'] ?? null,
            'payment_method' => $paymentMethod,
            'transaction_status' => $status,
            'raw_status' => $attributes['transaction_status'] ?? ($attributes['status'] ?? null),
            'transaction_id' => $transactionId,
            'metadata' => $metadata,
            'credits' => $this->extractCredits($attributes, $metadata),
            'processed_at' => $processedAt,
            'billing_id' => $billingId,
        ]);

        $this->notify($event);

        return $event;
    }

    private function normaliseEvent(string $eventName): string
    {
        $eventName = trim(strtolower($eventName));
        return $eventName === '' ? '*' : $eventName;
    }

    private function extractUserId(array $attributes): int
    {
        $userId = $attributes['user_id'] ?? null;
        if ($userId === null || (!is_int($userId) && !ctype_digit((string) $userId))) {
            throw new InvalidArgumentException('A valid user identifier is required for payment processing.');
        }

        return (int) $userId;
    }

    private function normaliseUserType(string $userType): string
    {
        return match (strtolower(trim($userType))) {
            'employer', 'employers' => 'Employer',
            'recruiter', 'recruiters' => 'Recruiter',
            'candidate', 'candidates' => 'Candidate',
            default => $userType !== '' ? ucfirst(strtolower($userType)) : 'Candidate',
        };
    }

    private function normalisePurpose(string $purpose): string
    {
        $purpose = strtolower(trim($purpose));

        return match ($purpose) {
            'credit', 'credits', 'resume credits', 'resume_credit', 'credit_purchase', 'credit-purchase' => 'Resume Credits',
            'premium', 'premium badge', 'badge' => 'Premium Badge',
            'subscription', 'subscriptions', 'plan' => 'Subscription',
            default => $purpose === '' ? 'Subscription' : ucfirst($purpose),
        };
    }

    private function normaliseStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'success', 'succeeded', 'paid', 'completed' => 'Success',
            'failed', 'failure', 'declined', 'errored' => 'Failed',
            default => 'Pending',
        };
    }

    private function normaliseAmount(mixed $amount): float
    {
        if (is_string($amount)) {
            $clean = preg_replace('/[^0-9.\-]/', '', $amount);
            $amount = $clean === '' ? 0.0 : (float) $clean;
        }

        if (!is_numeric($amount)) {
            throw new InvalidArgumentException('A numeric amount is required for payment processing.');
        }

        return round((float) $amount, 2);
    }

    /**
     * @param array<string, mixed>|string|null $metadata
     *
     * @return array<string, mixed>
     */
    private function normaliseMetadata(array|string|null $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata) && $metadata !== '') {
            $decoded = json_decode($metadata, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function extractCredits(array $attributes, array $metadata): ?int
    {
        $credits = $attributes['credits'] ?? ($metadata['credits'] ?? null);
        if ($credits === null) {
            return null;
        }

        if (is_int($credits)) {
            return $credits;
        }

        if (is_numeric($credits)) {
            return (int) $credits;
        }

        return null;
    }

    private function eventNameForStatus(string $status): string
    {
        return match ($status) {
            'Success' => self::EVENT_INVOICE_PAID,
            'Failed' => self::EVENT_PAYMENT_FAILED,
            default => self::EVENT_PAYMENT_PENDING,
        };
    }

    private function normaliseTransactionId(mixed $transactionId): ?string
    {
        if ($transactionId === null) {
            return null;
        }

        $transactionId = trim((string) $transactionId);

        return $transactionId === '' ? null : $transactionId;
    }

    private function normaliseString(mixed $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? 'manual' : $value;
    }
}
