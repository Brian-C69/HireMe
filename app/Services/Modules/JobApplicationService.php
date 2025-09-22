<?php

declare(strict_types=1);

namespace App\Services\Modules;

use App\Core\Request;
use App\Services\Job\JobModuleFacade;
use InvalidArgumentException;

final class JobApplicationService extends AbstractModuleService
{
    private JobModuleFacade $facade;

    public function __construct(?JobModuleFacade $facade = null)
    {
        $this->facade = $facade ?? JobModuleFacade::buildDefault();
    }

    public function name(): string
    {
        return 'job-application';
    }

    public function handle(string $type, ?string $id, Request $request): array
    {
        return match (strtolower($type)) {
            'jobs' => $this->listJobs($request, $id),
            'job' => $this->showJob($id),
            'applications' => $this->listApplications($request),
            'application' => $this->showApplication($id),
            'summary' => $this->summarise(),
            default => throw new InvalidArgumentException(sprintf('Unknown job/application operation "%s".', $type)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function listJobs(Request $request, ?string $scope): array
    {
        $filters = [
            'status' => $this->query($request, 'status'),
            'scope' => $scope,
        ];

        if ($employer = $this->query($request, 'employer_id')) {
            if (ctype_digit($employer)) {
                $filters['company_id'] = (int) $employer;
            }
        }

        if ($recruiter = $this->query($request, 'recruiter_id')) {
            if (ctype_digit($recruiter)) {
                $filters['recruiter_id'] = (int) $recruiter;
            }
        }

        $result = $this->facade->listJobs($filters);

        return $this->respond($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function showJob(?string $id): array
    {
        $jobId = $this->requireIntId($id, 'A job identifier is required.');

        return $this->respond($this->facade->showJob($jobId));
    }

    /**
     * @return array<string, mixed>
     */
    private function listApplications(Request $request): array
    {
        $filters = [];

        if ($job = $this->query($request, 'job_id')) {
            if (ctype_digit($job)) {
                $filters['job_id'] = (int) $job;
            }
        }

        if ($candidate = $this->query($request, 'candidate_id')) {
            if (ctype_digit($candidate)) {
                $filters['candidate_id'] = (int) $candidate;
            }
        }

        $result = $this->facade->listApplications($filters);

        return $this->respond($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function showApplication(?string $id): array
    {
        $applicationId = $this->requireIntId($id, 'An application identifier is required.');

        $result = $this->facade->showApplication(
            $applicationId,
            fn (int $candidateId) => $this->forward('resume-profile', 'profile', (string) $candidateId)
        );

        return $this->respond($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function summarise(): array
    {
        $result = $this->facade->summarise(
            fn (int $candidateId) => $this->forward('resume-profile', 'profile', (string) $candidateId)
        );

        return $this->respond($result);
    }
}
