<?php

declare(strict_types=1);

namespace App\Services\Admin\Moderation\Commands;

use App\Models\JobPosting;
use App\Services\Admin\Moderation\ModerationCommand;
use App\Services\Admin\Moderation\ModerationCommandResult;
use App\Services\Admin\Moderation\RequiresModerationAuthorization;
use InvalidArgumentException;

final class ApproveJobCommand implements ModerationCommand, RequiresModerationAuthorization
{
    public function __construct(
        private readonly int $jobId,
        private readonly ?int $moderatorId = null
    ) {
    }

    public function name(): string
    {
        return 'approve-job';
    }

    public function execute(): ModerationCommandResult
    {
        $job = JobPosting::query()->find($this->jobId);
        if ($job === null) {
            throw new InvalidArgumentException('Job posting not found.');
        }

        $previousStatus = (string) $job->status;
        $job->status = 'Open';
        $job->save();

        return new ModerationCommandResult(
            $this->name(),
            'success',
            [
                'job_id' => $job->job_posting_id,
                'previous_status' => $previousStatus,
                'status' => (string) $job->status,
                'moderator_id' => $this->moderatorId,
                'updated_at' => $job->updated_at?->format('c') ?? (string) $job->updated_at,
            ],
            'Job approved.'
        );
    }
}
