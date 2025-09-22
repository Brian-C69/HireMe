<?php

declare(strict_types=1);

namespace App\Services\Modules;

use App\Core\Request;
use App\Models\Billing;
use App\Models\Payment;
use InvalidArgumentException;

final class PaymentBillingService extends AbstractModuleService
{
    public function name(): string
    {
        return 'payment-billing';
    }

    public function handle(string $type, ?string $id, Request $request): array
    {
        return match (strtolower($type)) {
            'payments' => $this->listPayments($request, $id),
            'payment' => $this->showPayment($id),
            'billing' => $this->listBilling($request, $id),
            'summary' => $this->summarise(),
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
    private function showPayment(?string $id): array
    {
        $paymentId = $this->requireIntId($id, 'A payment identifier is required.');
        $payment = Payment::find($paymentId);
        if ($payment === null) {
            throw new InvalidArgumentException('Payment record not found.');
        }

        $data = $payment->toArray();
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

        $records = $query->orderByDesc('transaction_date')->get();

        return $this->respond([
            'billing' => $records->map(static fn (Billing $billing) => $billing->toArray())->all(),
            'count' => $records->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summarise(): array
    {
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
}
