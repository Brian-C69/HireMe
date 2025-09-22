<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\Services\Job\Contracts\JobAnalyticsInterface;
use App\Services\Job\Contracts\JobApplicationManagerInterface;
use App\Services\Job\Contracts\JobAuthorizerInterface;
use App\Services\Job\Contracts\JobNotifierInterface;
use App\Services\Job\Contracts\JobRepositoryInterface;
use App\Services\Job\Contracts\JobSearchInterface;
use App\Services\Job\Contracts\JobValidatorInterface;
use InvalidArgumentException;
use Throwable;

final class JobModuleFacade
{
    public function __construct(
        private readonly JobValidatorInterface $validator,
        private readonly JobAuthorizerInterface $authorizer,
        private readonly JobRepositoryInterface $repository,
        private readonly JobNotifierInterface $notifier,
        private readonly JobSearchInterface $search,
        private readonly JobAnalyticsInterface $analytics,
        private readonly JobApplicationManagerInterface $applications
    ) {
    }

    public static function buildDefault(): self
    {
        return new self(
            new JobInputValidator(),
            new JobAuthorizationService(),
            new JobRepository(),
            new JobNotificationService(),
            new JobSearchService(),
            new JobAnalyticsService(),
            new JobApplicationWorkflow()
        );
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public function validateJobInput(string $role, int $userId, array $input): array
    {
        $result = $this->validator->validateForPublish($role, $userId, $input);

        return [$result['data'], $result['errors']];
    }

    /**
     * @return array{0:int,1:array<string,string>}
     */
    public function publishJob(string $role, int $userId, array $input): array
    {
        [$data, $errors] = $this->validateJobInput($role, $userId, $input);
        if ($errors !== []) {
            return [0, $errors];
        }

        try {
            $this->authorizer->authorizePublish($role, $userId, $data);
        } catch (Throwable $e) {
            return [0, ['general' => $e->getMessage()]];
        }

        $questionIds = $data['question_ids'] ?? [];
        unset($data['question_ids']);

        try {
            $jobId = $this->repository->createJob($data, $questionIds);
        } catch (Throwable) {
            return [0, ['general' => 'Could not create job.']];
        }

        $this->notifier->jobPublished($jobId, $data);
        $this->search->refreshJob($jobId);
        $this->analytics->recordJobPublished($jobId, $data);

        return [$jobId, []];
    }

    /**
     * @return array{0:bool,1:array<string,string>}
     */
    public function updateJob(int $jobId, string $role, int $userId, array $input, array $existing = []): array
    {
        $result = $this->validator->validateForUpdate($role, $userId, $input, $existing);
        $data = $result['data'];
        $errors = $result['errors'];

        if ($errors !== []) {
            return [false, $errors];
        }

        try {
            $this->authorizer->authorizeUpdate($jobId, $role, $userId, $data);
        } catch (Throwable $e) {
            return [false, ['general' => $e->getMessage()]];
        }

        $questionIds = $data['question_ids'] ?? [];
        unset($data['question_ids']);

        try {
            $updated = $this->repository->updateJob($jobId, $data, $questionIds);
        } catch (Throwable) {
            return [false, ['general' => 'Could not update job.']];
        }

        if (!$updated) {
            return [false, ['general' => 'Job not found.']];
        }

        $this->notifier->jobUpdated($jobId, $data);
        $this->search->refreshJob($jobId);
        $this->analytics->recordJobUpdated($jobId, $data);

        return [true, []];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{jobs: array<int, array<string, mixed>>, count: int, filters: array<string, mixed|null>}
     */
    public function listJobs(array $filters = []): array
    {
        $normalised = $this->normaliseJobFilters($filters);
        $jobs = $this->search->search($normalised);

        return [
            'jobs' => $jobs,
            'count' => count($jobs),
            'filters' => [
                'status' => $normalised['status'] ?? null,
                'company_id' => $normalised['company_id'] ?? null,
                'recruiter_id' => $normalised['recruiter_id'] ?? null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function showJob(int $jobId): array
    {
        $job = $this->search->getJob($jobId);
        if ($job === null) {
            throw new InvalidArgumentException('Job posting not found.');
        }

        $applications = $this->applications->listForJob($jobId);
        $job['applications'] = $applications;
        $job['application_count'] = count($applications);

        return ['job' => $job];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{applications: array<int, array<string, mixed>>, count: int}
     */
    public function listApplications(array $filters = []): array
    {
        $applications = $this->applications->listApplications($filters);

        return [
            'applications' => $applications,
            'count' => count($applications),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function showApplication(int $applicationId, ?callable $candidateProfileResolver = null): array
    {
        $application = $this->applications->getApplication($applicationId);
        if ($application === null) {
            throw new InvalidArgumentException('Application record not found.');
        }

        if ($candidateProfileResolver !== null && isset($application['candidate']['candidate_id'])) {
            $candidateId = (int) $application['candidate']['candidate_id'];
            if ($candidateId > 0) {
                $application['profile'] = $candidateProfileResolver($candidateId);
            }
        }

        return ['application' => $application];
    }

    /**
     * @return array<string, mixed>
     */
    public function summarise(?callable $candidateProfileResolver = null): array
    {
        return $this->analytics->summarise($candidateProfileResolver);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function applyToJob(int $jobId, int $candidateId, array $input): array
    {
        try {
            $this->authorizer->authorizeApplication($jobId, $candidateId);
        } catch (Throwable $e) {
            return ['application_id' => 0, 'errors' => ['general' => $e->getMessage()]];
        }

        $result = $this->applications->applyToJob($jobId, $candidateId, $input);
        $applicationId = (int) ($result['application_id'] ?? 0);
        $errors = $result['errors'] ?? [];

        if ($errors === [] && $applicationId > 0) {
            $this->notifier->applicationSubmitted($applicationId);
        }

        $result['application_id'] = $applicationId;
        $result['errors'] = $errors;

        return $result;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function normaliseJobFilters(array $filters): array
    {
        $normalised = [];

        if (isset($filters['status']) && $filters['status'] !== '') {
            $normalised['status'] = $filters['status'];
        }

        if (isset($filters['company_id']) && (int) $filters['company_id'] > 0) {
            $normalised['company_id'] = (int) $filters['company_id'];
        }

        if (isset($filters['recruiter_id']) && (int) $filters['recruiter_id'] > 0) {
            $normalised['recruiter_id'] = (int) $filters['recruiter_id'];
        }

        if (isset($filters['scope']) && is_string($filters['scope'])) {
            $this->applyScopeFilter($normalised, $filters['scope']);
        }

        return $normalised;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyScopeFilter(array &$filters, string $scope): void
    {
        $scope = trim(strtolower($scope));
        if ($scope === '') {
            return;
        }

        if (in_array($scope, ['active', 'open'], true)) {
            $filters['status'] = $filters['status'] ?? 'active';
            return;
        }

        if (in_array($scope, ['closed', 'archived'], true)) {
            $filters['status'] = $filters['status'] ?? 'closed';
            return;
        }

        if (str_starts_with($scope, 'employer-')) {
            $companyId = substr($scope, strlen('employer-'));
            if (ctype_digit($companyId)) {
                $filters['company_id'] = (int) $companyId;
            }
            return;
        }

        if (str_starts_with($scope, 'recruiter-')) {
            $recruiterId = substr($scope, strlen('recruiter-'));
            if (ctype_digit($recruiterId)) {
                $filters['recruiter_id'] = (int) $recruiterId;
            }
        }
    }
}
