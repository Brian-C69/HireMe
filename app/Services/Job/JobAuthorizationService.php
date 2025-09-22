<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\Services\Job\Contracts\JobAuthorizerInterface;
use RuntimeException;

final class JobAuthorizationService implements JobAuthorizerInterface
{
    public function authorizePublish(string $role, int $userId, array $jobData): void
    {
        if (!in_array($role, ['Employer', 'Recruiter'], true)) {
            throw new RuntimeException('Not authorized to publish jobs.');
        }

        $companyId = (int)($jobData['company_id'] ?? 0);
        if ($companyId <= 0) {
            throw new RuntimeException('A company must be specified for the job posting.');
        }

        if ($role === 'Employer' && $companyId !== $userId) {
            throw new RuntimeException('Employers may only publish jobs for their own company.');
        }

        if ($role === 'Recruiter') {
            $recruiterId = (int)($jobData['recruiter_id'] ?? 0);
            if ($recruiterId !== $userId) {
                throw new RuntimeException('Recruiter context mismatch.');
            }
        }
    }

    public function authorizeUpdate(int $jobId, string $role, int $userId, array $jobData): void
    {
        $this->authorizePublish($role, $userId, $jobData);
        if ($jobId <= 0) {
            throw new RuntimeException('Invalid job identifier.');
        }
    }

    public function authorizeApplication(int $jobId, int $candidateId): void
    {
        if ($jobId <= 0) {
            throw new RuntimeException('Invalid job identifier.');
        }
        if ($candidateId <= 0) {
            throw new RuntimeException('Authentication required.');
        }
    }
}
