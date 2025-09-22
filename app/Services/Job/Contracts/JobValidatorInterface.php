<?php

declare(strict_types=1);

namespace App\Services\Job\Contracts;

interface JobValidatorInterface
{
    /**
     * Validate and normalise data for publishing a job.
     *
     * @param array<string, mixed> $input
     *
     * @return array{data: array<string, mixed>, errors: array<string, string>}
     */
    public function validateForPublish(string $role, int $userId, array $input): array;

    /**
     * Validate and normalise data for updating a job.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $existing
     *
     * @return array{data: array<string, mixed>, errors: array<string, string>}
     */
    public function validateForUpdate(string $role, int $userId, array $input, array $existing = []): array;
}
