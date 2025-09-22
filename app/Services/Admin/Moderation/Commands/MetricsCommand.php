<?php

declare(strict_types=1);

namespace App\Services\Admin\Moderation\Commands;

use App\Models\Candidate;
use App\Models\Employer;
use App\Models\JobPosting;
use App\Models\Payment;
use App\Services\Admin\Moderation\ModerationCommand;
use App\Services\Admin\Moderation\ModerationCommandResult;
use App\Services\Admin\Moderation\ModerationSuspensionStore;

final class MetricsCommand implements ModerationCommand
{
    public function __construct(private readonly ?ModerationSuspensionStore $suspensionStore = null)
    {
    }

    public function name(): string
    {
        return 'metrics';
    }

    public function execute(): ModerationCommandResult
    {
        $candidateCount = Candidate::query()->count();
        $employerCount = Employer::query()->count();
        $jobCount = JobPosting::query()->count();
        $activeJobs = JobPosting::query()->whereIn('status', ['active', 'open'])->count();
        $failedPayments = Payment::query()->where('transaction_status', 'failed')->count();
        $activeSuspensions = $this->suspensionStore?->count() ?? 0;

        return new ModerationCommandResult(
            $this->name(),
            'success',
            [
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
                    'moderation' => [
                        'active_suspensions' => $activeSuspensions,
                    ],
                ],
            ],
            'Metrics calculated.'
        );
    }
}
