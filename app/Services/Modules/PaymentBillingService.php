<?php

declare(strict_types=1);

namespace App\Services\Modules;

use App\Core\Request;
use App\Models\Billing;
use App\Models\Payment;
use App\Services\Admin\AdminRoleAwareInterface;
use App\Services\Admin\AdminRoleAwareTrait;
use App\Services\Payment\PaymentProcessor;
use InvalidArgumentException;

final class PaymentBillingService extends AbstractModuleService implements AdminRoleAwareInterface
{
    use AdminRoleAwareTrait;

    private PaymentProcessor $processor;

    public function __construct(?PaymentProcessor $processor = null)
    {
        $this->processor = $processor ?? PaymentProcessor::withDefaultObservers();
    }

    public function name(): string
    {
        return 'payment-billing';
    }

    public function handle(string $type, ?string $id, Request $request): array
    {
        return match (strtolower($type)) {
            'payments' => $this->listPayments($request, $id),
            'payment' => $this->showPayment($request, $id),
            'billing' => $this->listBilling($request, $id),
            'charge' => $this->charge($request),
            'summary' => $this->summarise($request),
            default => throw new InvalidArgumentException(sprintf('Unknown payment/billing operation "%s".', $type)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayments(Request $request, ?string $scope): array
    {
        $query = Payment::query();

        $status = $this->query($request, 'status');
        if ($scope !== null && $scope !== '') {
            $scopeLower = strtolower($scope);
            if (in_array($scopeLower, ['pending', 'completed', 'failed', 'refunded'], true)) {
                $status = $status ?? $scopeLower;
            } elseif (str_starts_with($scopeLower, 'user-')) {
                $identifier = substr($scopeLower, 5);
                if (ctype_digit($identifier)) {
                    $query->where('user_id', (int) $identifier);
                }
            }
        }

        if ($status !== null && $status !== '') {
            $query->where('transaction_status', $status);
        }

        if ($userType = $this->query($request, 'user_type')) {
            $query->where('user_type', $userType);
        }

        if ($userId = $this->query($request, 'user_id')) {
            if (ctype_digit($userId)) {
                $query->where('user_id', (int) $userId);
            }
        }

        $this->adminGuardian()->assertRead('payments', $this->adminContext($request, [
            'action' => 'payments.list',
            'status' => $status,
            'scope' => $scope,
        ]));

        $payments = $query->orderByDesc('created_at')->get();

        return $this->respond([
            'payments' => $payments->map(static fn (Payment $payment) => $payment->toArray())->all(),
            'count' => $payments->count(),
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function showPayment(Request $request, ?string $id): array
    {
        $paymentId = $this->requireIntId($id, 'A payment identifier is required.');
        $payment = Payment::find($paymentId);
        if ($payment === null) {
            throw new InvalidArgumentException('Payment record not found.');
        }

        $data = $payment->toArray();
        $this->adminGuardian()->assertRead('payments', $this->adminContext($request, [
            'action' => 'payments.show',
            'payment_id' => $paymentId,
            'user_type' => $data['user_type'] ?? null,
            'user_id' => $data['user_id'] ?? null,
        ]));

        $role = $this->roleForUserType($data['user_type'] ?? null);
        if ($role !== null && isset($data['user_id']) && is_numeric($data['user_id'])) {
            $user = $this->forward('user-management', 'user', (string) $data['user_id'], [
                'role' => $role,
            ]);
            $data['user'] = $user['user'] ?? null;
        }

        return $this->respond([
            'payment' => $data,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listBilling(Request $request, ?string $scope): array
    {
        $query = Billing::query();

        if ($scope !== null && $scope !== '') {
            $scopeLower = strtolower($scope);
            if (in_array($scopeLower, ['pending', 'paid', 'failed'], true)) {
                $query->where('status', $scopeLower);
            } elseif (str_starts_with($scopeLower, 'user-')) {
                $identifier = substr($scopeLower, 5);
                if (ctype_digit($identifier)) {
                    $query->where('user_id', (int) $identifier);
                }
            }
        }

        if ($status = $this->query($request, 'status')) {
            $query->where('status', $status);
        }

        if ($userType = $this->query($request, 'user_type')) {
            $query->where('user_type', $userType);
        }

        if ($userId = $this->query($request, 'user_id')) {
            if (ctype_digit($userId)) {
                $query->where('user_id', (int) $userId);
            }
        }

        $this->adminGuardian()->assertRead('billing', $this->adminContext($request, [
            'action' => 'billing.list',
            'scope' => $scope,
        ]));

        $records = $query->orderByDesc('transaction_date')->get();

        return $this->respond([
            'billing' => $records->map(static fn (Billing $billing) => $billing->toArray())->all(),
            'count' => $records->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summarise(Request $request): array
    {
        $this->adminGuardian()->assertRead('payments', $this->adminContext($request, [
            'action' => 'payments.summary',
        ]));

        $totalPayments = Payment::query()->sum('amount');
        $paymentCount = Payment::query()->count();

        $statusBreakdown = Payment::query()
            ->selectRaw('transaction_status, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('transaction_status')
            ->get()
            ->map(static function ($row): array {
                return [
                    'status' => $row->transaction_status,
                    'count' => (int) $row->count,
                    'total_amount' => (float) $row->total_amount,
                ];
            })
            ->all();

        $topUsers = Payment::query()
            ->selectRaw('user_type, user_id, SUM(amount) as total_amount, COUNT(*) as payments')
            ->whereNotNull('user_id')
            ->groupBy('user_type', 'user_id')
            ->orderByDesc('total_amount')
            ->take(5)
            ->get()
            ->map(function ($row): array {
                $role = $this->roleForUserType($row->user_type);
                $userDetails = null;
                if ($role !== null && $row->user_id !== null) {
                    $userDetails = $this->forward('user-management', 'user', (string) $row->user_id, [
                        'role' => $role,
                    ]);
                }

                return [
                    'user_id' => (int) $row->user_id,
                    'user_type' => $row->user_type,
                    'total_amount' => (float) $row->total_amount,
                    'payments' => (int) $row->payments,
                    'user' => $userDetails['user'] ?? null,
                ];
            })
            ->all();

        $latestPayments = Payment::query()->orderByDesc('created_at')->take(5)->get()
            ->map(static fn (Payment $payment) => $payment->toArray())
            ->all();

        $billingCount = Billing::query()->count();

        return $this->respond([
            'summary' => [
                'payments' => [
                    'total_amount' => (float) $totalPayments,
                    'count' => $paymentCount,
                    'by_status' => $statusBreakdown,
                    'latest' => $latestPayments,
                ],
                'billing' => [
                    'count' => $billingCount,
                ],
                'top_payers' => $topUsers,
            ],
        ]);
    }

    private function roleForUserType(mixed $userType): ?string
    {
        if (!is_string($userType) || $userType === '') {
            return null;
        }

        return match (strtolower($userType)) {
            'candidate', 'candidates' => 'candidates',
            'employer', 'employers' => 'employers',
            'recruiter', 'recruiters' => 'recruiters',
            'admin', 'admins' => 'admins',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function charge(Request $request): array
    {
        $payload = $request->all();

        $userId = $payload['user_id'] ?? null;
        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            throw new InvalidArgumentException('A valid user identifier is required to process a payment.');
        }

        $userType = (string) ($payload['user_type'] ?? '');
        if ($userType === '') {
            throw new InvalidArgumentException('A user type is required to process a payment.');
        }

        if (!array_key_exists('amount', $payload)) {
            throw new InvalidArgumentException('A payment amount must be provided.');
        }

        $context = $this->adminContext($request, [
            'action' => 'payments.charge',
            'user_id' => (int) $userId,
            'user_type' => $userType,
            'amount' => $payload['amount'],
        ]);

        $this->adminGuardian()->assertWrite('payments', $context);

        $metadata = $this->normaliseMetadata($payload['metadata'] ?? null);
        $event = $this->processor->process([
            'user_id' => (int) $userId,
            'user_type' => $userType,
            'amount' => $payload['amount'],
            'purpose' => $payload['purpose'] ?? '',
            'payment_method' => $payload['payment_method'] ?? 'manual',
            'transaction_status' => $payload['transaction_status'] ?? ($payload['status'] ?? 'success'),
            'transaction_id' => $payload['transaction_id'] ?? null,
            'metadata' => $metadata,
            'credits' => $this->normaliseCredits($payload['credits'] ?? null, $metadata),
            'billing_id' => $this->normaliseBillingId($payload['billing_id'] ?? null),
        ]);

        $eventPayload = $event->payload();
        $payment = $eventPayload['payment'] ?? null;
        $paymentData = $payment instanceof Payment ? $payment->toArray() : (array) $payment;

        $billing = $this->resolveBillingRecord(
            $eventPayload,
            (int) $userId,
            (string) ($eventPayload['user_type'] ?? $userType)
        );

        $response = [
            'event' => $event->name(),
            'payment' => $paymentData,
            'billing' => $billing?->toArray(),
        ];

        $this->adminArbiter()->dispatch('payments.processed', [
            'event' => $event->name(),
            'user_id' => (int) $userId,
            'user_type' => $userType,
            'amount' => $payload['amount'],
            'payment' => $paymentData,
        ]);
        $this->adminArbiter()->flush();

        return $this->respond($response);
    }

    /**
     * @param array<string, mixed> $eventPayload
     */
    private function resolveBillingRecord(array $eventPayload, int $userId, string $userType): ?Billing
    {
        $billingId = $eventPayload['billing_id'] ?? null;
        if (is_numeric($billingId)) {
            $existing = Billing::find((int) $billingId);
            if ($existing instanceof Billing) {
                return $existing;
            }
        }

        $query = Billing::query()
            ->where('user_id', $userId)
            ->where('user_type', $userType);

        $reference = $eventPayload['transaction_id'] ?? null;
        if (is_string($reference) && $reference !== '') {
            $query->where('reference_number', $reference);
        }

        return $query->orderByDesc('transaction_date')->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function normaliseMetadata(mixed $metadata): array
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

    /**
     * @param array<string, mixed> $metadata
     */
    private function normaliseCredits(mixed $credits, array $metadata): ?int
    {
        if ($credits === null && isset($metadata['credits'])) {
            $credits = $metadata['credits'];
        }

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

    private function normaliseBillingId(mixed $billingId): ?int
    {
        if ($billingId === null) {
            return null;
        }

        if (is_int($billingId)) {
            return $billingId;
        }

        return ctype_digit((string) $billingId) ? (int) $billingId : null;
    }
}
