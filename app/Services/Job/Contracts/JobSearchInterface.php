<?php

declare(strict_types=1);

namespace App\Services\Job\Contracts;

interface JobSearchInterface
{
    public function refreshJob(int $jobId): void;

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array;

    /**
     * @return array<string, mixed>|null
     */
    public function getJob(int $jobId): ?array;
}
