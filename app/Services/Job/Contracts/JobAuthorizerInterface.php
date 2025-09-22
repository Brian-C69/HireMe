<?php

declare(strict_types=1);

namespace App\Services\Job\Contracts;

interface JobAuthorizerInterface
{
    /**
     * @param array<string, mixed> $jobData
     */
    public function authorizePublish(string $role, int $userId, array $jobData): void;

    /**
     * @param array<string, mixed> $jobData
     */
    public function authorizeUpdate(int $jobId, string $role, int $userId, array $jobData): void;

    public function authorizeApplication(int $jobId, int $candidateId): void;
}
