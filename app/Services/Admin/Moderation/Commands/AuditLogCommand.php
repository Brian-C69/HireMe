<?php

declare(strict_types=1);

namespace App\Services\Admin\Moderation\Commands;

use App\Models\Candidate;
use App\Models\JobPosting;
use App\Models\Payment;
use App\Services\Admin\Moderation\ModerationCommand;
use App\Services\Admin\Moderation\ModerationCommandResult;
use App\Services\Admin\Moderation\ModerationSuspensionStore;
use App\Services\Modules\ModuleRegistry;
use Throwable;

final class AuditLogCommand implements ModerationCommand
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ?ModerationSuspensionStore $suspensionStore = null
    ) {
    }

    public function name(): string
    {
        return 'audit';
    }

    public function execute(): ModerationCommandResult
    {
        $pendingCandidates = Candidate::query()
            ->where('verified_status', 'pending')
            ->orderByDesc('created_at')
            ->take(10)
            ->get()
            ->map(function (Candidate $candidate): array {
                $user = $this->safeCall('user-management', 'user', (string) $candidate->candidate_id, [
                    'role' => 'candidates',
                ]);

                return [
                    'candidate' => $candidate->toArray(),
                    'user' => $user['user'] ?? null,
                ];
            })
            ->all();

        $flaggedJobs = JobPosting::query()
            ->whereIn('status', ['flagged', 'under_review'])
            ->orderByDesc('updated_at')
            ->take(10)
            ->get()
            ->map(function (JobPosting $job): array {
                $employer = null;
                if ($job->company_id !== null) {
                    $employer = $this->safeCall('user-management', 'user', (string) $job->company_id, [
                        'role' => 'employers',
                    ]);
                }

                return [
                    'job' => $job->toArray(),
                    'employer' => $employer['user'] ?? null,
                ];
            })
            ->all();

        $failedPayments = Payment::query()
            ->where('transaction_status', 'failed')
            ->orderByDesc('created_at')
            ->take(10)
            ->get()
            ->map(function (Payment $payment): array {
                $user = null;
                if ($payment->user_id !== null) {
                    $role = $this->roleForUserType($payment->user_type);
                    if ($role !== null) {
                        $user = $this->safeCall('user-management', 'user', (string) $payment->user_id, [
                            'role' => $role,
                        ]);
                    }
                }

                return [
                    'payment' => $payment->toArray(),
                    'user' => $user['user'] ?? null,
                ];
            })
            ->all();

        return new ModerationCommandResult(
            $this->name(),
            'success',
            [
                'audit' => [
                    'candidates' => $pendingCandidates,
                    'jobs' => $flaggedJobs,
                    'payments' => $failedPayments,
                    'suspensions' => $this->suspensionStore?->all() ?? [],
                ],
            ],
            'Audit log prepared.'
        );
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
     * @param array<string, mixed> $query
     * @return array<string, mixed>|null
     */
    private function safeCall(string $module, string $type, ?string $id = null, array $query = []): ?array
    {
        try {
            return $this->registry->call($module, $type, $id, $query);
        } catch (Throwable $exception) {
            error_log(sprintf(
                '[Moderation][WARN] %s:%s lookup failed: %s',
                $module,
                $type,
                $exception->getMessage()
            ));

            return null;
        }
    }
}
