<?php

declare(strict_types=1);

namespace App\Services\Job\Contracts;

interface JobAnalyticsInterface
{
    /**
     * @param array<string, mixed> $jobData
     */
    public function recordJobPublished(int $jobId, array $jobData): void;

    /**
     * @param array<string, mixed> $jobData
     */
    public function recordJobUpdated(int $jobId, array $jobData): void;

    /**
     * @param callable|null $candidateProfileResolver Resolver that accepts a candidate ID and returns profile data.
     *
     * @return array<string, mixed>
     */
    public function summarise(?callable $candidateProfileResolver = null): array;
}
