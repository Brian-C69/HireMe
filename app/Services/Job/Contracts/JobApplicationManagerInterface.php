<?php

declare(strict_types=1);

namespace App\Services\Job\Contracts;

interface JobApplicationManagerInterface
{
    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function applyToJob(int $jobId, int $candidateId, array $input): array;

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function listApplications(array $filters = []): array;

    /**
     * @return array<string, mixed>|null
     */
    public function getApplication(int $applicationId): ?array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForJob(int $jobId): array;
}
