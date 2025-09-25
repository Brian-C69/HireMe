<?php

declare(strict_types=1);

namespace App\Services\Modules;

use App\Core\Request;
use App\Services\Admin\AdminRoleAwareInterface;
use App\Services\Admin\AdminRoleAwareTrait;
use App\Services\Job\JobModuleFacade;
use InvalidArgumentException;

final class JobApplicationService extends AbstractModuleService implements AdminRoleAwareInterface
{
    use AdminRoleAwareTrait;

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
            'job' => $this->showJob($request, $id),
            'applications' => $this->listApplications($request),
            'application' => $this->showApplication($request, $id),
            'summary' => $this->summarise($request),
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

        $this->adminGuardian()->assertRead('jobs', $this->adminContext($request, [
            'action' => 'jobs.list',
            'filters' => array_filter($filters, static fn ($value) => $value !== null),
        ]));

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
    private function showJob(Request $request, ?string $id): array
    {
        $jobId = $this->requireIntId($id, 'A job identifier is required.');

        $this->adminGuardian()->assertRead('jobs', $this->adminContext($request, [
            'action' => 'jobs.show',
            'job_id' => $jobId,
        ]));

        return $this->respond($this->facade->showJob($jobId));
    }

    /**
     * @return array<string, mixed>
     */
    private function listApplications(Request $request): array
    {
        $filters = [];

        $context = [
            'action' => 'applications.list',
        ];

        if ($job = $this->query($request, 'job_id')) {
            if (ctype_digit($job)) {
                $filters['job_id'] = (int) $job;
                $context['job_id'] = (int) $job;
            }
        }

        if ($candidate = $this->query($request, 'candidate_id')) {
            if (ctype_digit($candidate)) {
                $filters['candidate_id'] = (int) $candidate;
                $context['candidate_id'] = (int) $candidate;
            }
        }

        $this->adminGuardian()->assertRead('applications', $this->adminContext($request, $context));

        $result = $this->facade->listApplications($filters);

        return $this->respond($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function showApplication(Request $request, ?string $id): array
    {
        $applicationId = $this->requireIntId($id, 'An application identifier is required.');

        $this->adminGuardian()->assertRead('applications', $this->adminContext($request, [
            'action' => 'applications.show',
            'application_id' => $applicationId,
        ]));

        $result = $this->facade->showApplication(
            $applicationId,
            fn (int $candidateId) => $this->forward('resume-profile', 'profile', (string) $candidateId)
        );

        return $this->respond($result);
    }

    /**
     * @return array<string, mixed>
     */
    private function summarise(Request $request): array
    {
        $this->adminGuardian()->assertRead('jobs', $this->adminContext($request, [
            'action' => 'jobs.summary',
        ]));

        $result = $this->facade->summarise(
            fn (int $candidateId) => $this->forward('resume-profile', 'profile', (string) $candidateId)
        );

        return $this->respond($result);
    }
}
