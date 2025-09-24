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

final class OverviewCommand implements ModerationCommand
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ?ModerationSuspensionStore $suspensionStore = null
    ) {
    }

    public function name(): string
    {
        return 'overview';
    }

    public function execute(): ModerationCommandResult
    {
        $userSnapshot = $this->snapshot('user-management', 'users', 'all');
        $jobSnapshot = $this->snapshot('job-application', 'summary', 'all');
        $financeSnapshot = $this->snapshot('payment-billing', 'summary', 'all');

        $userData = $userSnapshot['data'] ?? [];
        $jobData = $jobSnapshot['data'] ?? [];
        $financeData = $financeSnapshot['data'] ?? [];

        $userRecords = $userData['users'] ?? $userData;
        if (!is_array($userRecords)) {
            $userRecords = [];
        }

        $userCounts = $userData['counts'] ?? [];
        if (!is_array($userCounts)) {
            $userCounts = [];
        }

        $pendingVerifications = Candidate::query()->where('verified_status', 'pending')->count();
        $flaggedJobs = JobPosting::query()->whereIn('status', ['flagged', 'under_review'])->count();
        $failedPayments = Payment::query()->where('transaction_status', 'failed')->count();

        $suspensions = $this->suspensionStore?->count() ?? 0;

        $errors = array_filter([
            'user-management' => $userSnapshot['error'] ?? null,
            'job-application' => $jobSnapshot['error'] ?? null,
            'payment-billing' => $financeSnapshot['error'] ?? null,
        ]);

        return new ModerationCommandResult(
            $this->name(),
            'success',
            [
                'overview' => [
                    'users' => $userRecords,
                    'user_counts' => $userCounts,
                    'jobs' => $jobData['summary'] ?? $jobData,
                    'finance' => $financeData['summary'] ?? $financeData,
                    'pending_verifications' => $pendingVerifications,
                    'flagged_jobs' => $flaggedJobs,
                    'failed_payments' => $failedPayments,
                    'active_suspensions' => $suspensions,
                    'errors' => $errors,
                ],
            ],
            'Overview generated.'
        );
    }

    /**
     * @return array{data: array<string, mixed>|null, error: string|null}
     */
    private function snapshot(string $module, string $type, ?string $id = null): array
    {
        try {
            return [
                'data' => $this->registry->call($module, $type, $id),
                'error' => null,
            ];
        } catch (Throwable $exception) {
            error_log(sprintf(
                '[Moderation][WARN] %s:%s snapshot failed: %s',
                $module,
                $type,
                $exception->getMessage()
            ));

            return [
                'data' => null,
                'error' => sprintf('Failed to fetch %s snapshot.', $module),
            ];
        }
    }
}
