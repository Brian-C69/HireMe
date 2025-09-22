<?php

declare(strict_types=1);

namespace App\Services\Job\Contracts;

interface JobNotifierInterface
{
    /**
     * @param array<string, mixed> $jobData
     */
    public function jobPublished(int $jobId, array $jobData): void;

    /**
     * @param array<string, mixed> $jobData
     */
    public function jobUpdated(int $jobId, array $jobData): void;

    public function applicationSubmitted(int $applicationId): void;
}
