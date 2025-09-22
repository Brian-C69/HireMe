<?php

declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../../../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Services\Job\Contracts\JobAnalyticsInterface;
use App\Services\Job\Contracts\JobApplicationManagerInterface;
use App\Services\Job\Contracts\JobAuthorizerInterface;
use App\Services\Job\Contracts\JobNotifierInterface;
use App\Services\Job\Contracts\JobRepositoryInterface;
use App\Services\Job\Contracts\JobSearchInterface;
use App\Services\Job\Contracts\JobValidatorInterface;
use App\Services\Job\JobModuleFacade;

final class StubValidator implements JobValidatorInterface
{
    public array $publishCalls = [];

    public function validateForPublish(string $role, int $userId, array $input): array
    {
        $this->publishCalls[] = [$role, $userId, $input];

        return [
            'data' => [
                'company_id' => $userId,
                'recruiter_id' => ($role === 'Recruiter' ? $userId : null),
                'question_ids' => [1, 2, 3],
            ],
            'errors' => [],
        ];
    }

    public function validateForUpdate(string $role, int $userId, array $input, array $existing = []): array
    {
        return $this->validateForPublish($role, $userId, $input);
    }
}

final class StubAuthorizer implements JobAuthorizerInterface
{
    public array $publishCalls = [];
    public array $updateCalls = [];
    public array $applicationCalls = [];

    public function authorizePublish(string $role, int $userId, array $jobData): void
    {
        $this->publishCalls[] = [$role, $userId, $jobData];
    }

    public function authorizeUpdate(int $jobId, string $role, int $userId, array $jobData): void
    {
        $this->updateCalls[] = [$jobId, $role, $userId, $jobData];
    }

    public function authorizeApplication(int $jobId, int $candidateId): void
    {
        $this->applicationCalls[] = [$jobId, $candidateId];
    }
}

final class StubRepository implements JobRepositoryInterface
{
    public array $createCalls = [];
    public array $updateCalls = [];
    public int $nextJobId = 99;

    public function createJob(array $data, array $questionIds = []): int
    {
        $this->createCalls[] = [$data, $questionIds];
        return $this->nextJobId;
    }

    public function updateJob(int $jobId, array $data, array $questionIds = []): bool
    {
        $this->updateCalls[] = [$jobId, $data, $questionIds];
        return true;
    }
}

final class StubNotifier implements JobNotifierInterface
{
    public array $jobPublished = [];
    public array $jobUpdated = [];
    public array $applicationSubmitted = [];

    public function jobPublished(int $jobId, array $jobData): void
    {
        $this->jobPublished[] = [$jobId, $jobData];
    }

    public function jobUpdated(int $jobId, array $jobData): void
    {
        $this->jobUpdated[] = [$jobId, $jobData];
    }

    public function applicationSubmitted(int $applicationId): void
    {
        $this->applicationSubmitted[] = $applicationId;
    }
}

final class StubSearch implements JobSearchInterface
{
    public array $refreshCalls = [];

    public function refreshJob(int $jobId): void
    {
        $this->refreshCalls[] = $jobId;
    }

    public function search(array $filters = []): array
    {
        return [];
    }

    public function getJob(int $jobId): ?array
    {
        return null;
    }
}

final class StubAnalytics implements JobAnalyticsInterface
{
    public array $published = [];

    public function recordJobPublished(int $jobId, array $jobData): void
    {
        $this->published[] = ['published', $jobId, $jobData];
    }

    public function recordJobUpdated(int $jobId, array $jobData): void
    {
        $this->published[] = ['updated', $jobId, $jobData];
    }

    public function summarise(?callable $candidateProfileResolver = null): array
    {
        return ['summary' => []];
    }
}

final class StubApplications implements JobApplicationManagerInterface
{
    public array $applyCalls = [];
    public array $listCalls = [];
    public array $getCalls = [];
    public array $nextApplyResult = ['application_id' => 42, 'errors' => []];

    public function applyToJob(int $jobId, int $candidateId, array $input): array
    {
        $this->applyCalls[] = [$jobId, $candidateId, $input];
        return $this->nextApplyResult;
    }

    public function listApplications(array $filters = []): array
    {
        $this->listCalls[] = $filters;
        return [];
    }

    public function getApplication(int $applicationId): ?array
    {
        $this->getCalls[] = $applicationId;
        return null;
    }

    public function listForJob(int $jobId): array
    {
        return [];
    }
}

// --- publish job triggers ---
$validator = new StubValidator();
$authorizer = new StubAuthorizer();
$repository = new StubRepository();
$notifier = new StubNotifier();
$search = new StubSearch();
$analytics = new StubAnalytics();
$applications = new StubApplications();

$facade = new JobModuleFacade($validator, $authorizer, $repository, $notifier, $search, $analytics, $applications);

[$jobId, $errors] = $facade->publishJob('Employer', 7, ['job_title' => 'Example']);

assert($jobId === 99, 'Repository job ID should be returned.');
assert($errors === [], 'No errors expected when publish succeeds.');
assert(count($validator->publishCalls) === 1, 'Validator should be invoked once.');
assert(count($authorizer->publishCalls) === 1, 'Authorizer should be invoked once.');
assert(count($repository->createCalls) === 1, 'Repository create should be called.');
assert($notifier->jobPublished === [[99, $repository->createCalls[0][0]]], 'Notifier should receive job data.');
assert($search->refreshCalls === [99], 'Search refresh should be triggered.');
assert($analytics->published[0][0] === 'published', 'Analytics publish event recorded.');

// --- apply to job notifications ---
$applications->nextApplyResult = ['application_id' => 555, 'errors' => []];
$applyResult = $facade->applyToJob(10, 20, ['answers' => []]);
assert($applications->applyCalls[0][0] === 10, 'Application manager should receive job ID.');
assert($applyResult['application_id'] === 555, 'Application ID from manager should be returned.');
assert($notifier->applicationSubmitted === [555], 'Notifier should be called on success.');

// --- apply failure should not notify ---
$applications->nextApplyResult = ['application_id' => 777, 'errors' => ['general' => 'Already applied']];
$facade->applyToJob(10, 20, []);
assert(count($notifier->applicationSubmitted) === 1, 'Notifier should not run when errors exist.');

echo "JobModuleFacade tests passed\n";
