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
        $userSnapshot = $this->registry->call('user-management', 'users', 'all');
        $jobSnapshot = $this->registry->call('job-application', 'summary', 'all');
        $financeSnapshot = $this->registry->call('payment-billing', 'summary', 'all');

        $pendingVerifications = Candidate::query()->where('verified_status', 'pending')->count();
        $flaggedJobs = JobPosting::query()->whereIn('status', ['flagged', 'under_review'])->count();
        $failedPayments = Payment::query()->where('transaction_status', 'failed')->count();

        $suspensions = $this->suspensionStore?->count() ?? 0;

        return new ModerationCommandResult(
            $this->name(),
            'success',
            [
                'overview' => [
                    'users' => $userSnapshot['users'] ?? $userSnapshot,
                    'jobs' => $jobSnapshot['summary'] ?? [],
                    'finance' => $financeSnapshot['summary'] ?? [],
                    'pending_verifications' => $pendingVerifications,
                    'flagged_jobs' => $flaggedJobs,
                    'failed_payments' => $failedPayments,
                    'active_suspensions' => $suspensions,
                ],
            ],
            'Overview generated.'
        );
    }
}
