<?php

declare(strict_types=1);

namespace App\Services\Job\Contracts;

interface JobRepositoryInterface
{
    /**
     * @param array<string, mixed> $data
     * @param array<int, int> $questionIds
     */
    public function createJob(array $data, array $questionIds = []): int;

    /**
     * @param array<string, mixed> $data
     * @param array<int, int> $questionIds
     */
    public function updateJob(int $jobId, array $data, array $questionIds = []): bool;
}
