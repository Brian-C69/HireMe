<?php

declare(strict_types=1);

namespace App\Services\Modules;


use App\Core\Request;

use App\Models\Candidate;
use App\Models\Employer;
use App\Models\JobPosting;
use App\Models\Payment;
use InvalidArgumentException;

final class AdminModerationService extends AbstractModuleService
{
    public function name(): string
    {
        return 'admin-moderation';
    }

    public function handle(string $type, ?string $id, Request $request): array
    {
        return match (strtolower($type)) {
            'overview' => $this->overview(),
            'metrics' => $this->metrics(),
            'audit' => $this->auditLog(),
            default => throw new InvalidArgumentException(sprintf('Unknown administration operation "%s".', $type)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function overview(): array
    {
        $userSnapshot = $this->forward('user-management', 'users', 'all');
        $jobSnapshot = $this->forward('job-application', 'summary', 'all');
        $financeSnapshot = $this->forward('payment-billing', 'summary', 'all');

        $pendingVerifications = Candidate::query()->where('verified_status', 'pending')->count();
        $flaggedJobs = JobPosting::query()->whereIn('status', ['flagged', 'under_review'])->count();

        return $this->respond([
            'overview' => [
                'users' => $userSnapshot,
                'jobs' => $jobSnapshot['summary'] ?? [],
                'finance' => $financeSnapshot['summary'] ?? [],
                'pending_verifications' => $pendingVerifications,
                'flagged_jobs' => $flaggedJobs,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function metrics(): array
    {
        $candidateCount = Candidate::query()->count();
        $employerCount = Employer::query()->count();
        $jobCount = JobPosting::query()->count();
        $activeJobs = JobPosting::query()->whereIn('status', ['active', 'open'])->count();
        $failedPayments = Payment::query()->where('transaction_status', 'failed')->count();

        return $this->respond([
            'metrics' => [
                'users' => [
                    'candidates' => $candidateCount,
                    'employers' => $employerCount,
                ],
                'jobs' => [
                    'total' => $jobCount,
                    'active' => $activeJobs,
                ],
                'payments' => [
                    'failed' => $failedPayments,
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function auditLog(): array
    {
        $pendingCandidates = Candidate::query()
            ->where('verified_status', 'pending')
            ->orderByDesc('created_at')
            ->take(10)
            ->get()
            ->map(function (Candidate $candidate): array {
                $user = $this->forward('user-management', 'user', (string) $candidate->candidate_id, [
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
                    $employer = $this->forward('user-management', 'user', (string) $job->company_id, [
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
                        $user = $this->forward('user-management', 'user', (string) $payment->user_id, [
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

        return $this->respond([
            'audit' => [
                'candidates' => $pendingCandidates,
                'jobs' => $flaggedJobs,
                'payments' => $failedPayments,
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
