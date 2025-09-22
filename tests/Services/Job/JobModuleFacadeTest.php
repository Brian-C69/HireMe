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
    public array $updateCalls = [];

    /** @var array<string, mixed>|null */
    public ?array $nextPublishResult = null;

    /** @var array<string, mixed>|null */
    public ?array $nextUpdateResult = null;

    public function validateForPublish(string $role, int $userId, array $input): array
    {
        $this->publishCalls[] = [$role, $userId, $input];

        if ($this->nextPublishResult !== null) {
            return $this->nextPublishResult;
        }

        return [
            'data' => [
                'company_id' => $userId,
                'recruiter_id' => ($role === 'Recruiter' ? $userId : null),
                'question_ids' => $input['question_ids'] ?? [],
                'job_title' => $input['job_title'] ?? 'Example',
            ],
            'errors' => [],
        ];
    }

    public function validateForUpdate(string $role, int $userId, array $input, array $existing = []): array
    {
        $this->updateCalls[] = [$role, $userId, $input, $existing];

        if ($this->nextUpdateResult !== null) {
            return $this->nextUpdateResult;
        }

        return [
            'data' => [
                'company_id' => $existing['company_id'] ?? $userId,
                'question_ids' => $input['question_ids'] ?? [],
                'job_title' => $input['job_title'] ?? 'Updated title',
            ],
            'errors' => [],
        ];
    }
}

final class StubAuthorizer implements JobAuthorizerInterface
{
    public array $publishCalls = [];
    public array $updateCalls = [];
    public array $applicationCalls = [];

    public ?Throwable $nextPublishException = null;
    public ?Throwable $nextUpdateException = null;
    public ?Throwable $nextApplicationException = null;

    public function authorizePublish(string $role, int $userId, array $jobData): void
    {
        $this->publishCalls[] = [$role, $userId, $jobData];
        if ($this->nextPublishException instanceof Throwable) {
            throw $this->nextPublishException;
        }
    }

    public function authorizeUpdate(int $jobId, string $role, int $userId, array $jobData): void
    {
        $this->updateCalls[] = [$jobId, $role, $userId, $jobData];
        if ($this->nextUpdateException instanceof Throwable) {
            throw $this->nextUpdateException;
        }
    }

    public function authorizeApplication(int $jobId, int $candidateId): void
    {
        $this->applicationCalls[] = [$jobId, $candidateId];
        if ($this->nextApplicationException instanceof Throwable) {
            throw $this->nextApplicationException;
        }
    }
}

final class StubRepository implements JobRepositoryInterface
{
    public array $createCalls = [];
    public array $updateCalls = [];
    public int $nextJobId = 99;

    public ?Throwable $nextCreateException = null;
    public ?Throwable $nextUpdateException = null;
    public bool $nextUpdateResult = true;

    public function createJob(array $data, array $questionIds = []): int
    {
        $this->createCalls[] = [$data, $questionIds];

        if ($this->nextCreateException instanceof Throwable) {
            throw $this->nextCreateException;
        }

        return $this->nextJobId;
    }

    public function updateJob(int $jobId, array $data, array $questionIds = []): bool
    {
        $this->updateCalls[] = [$jobId, $data, $questionIds];

        if ($this->nextUpdateException instanceof Throwable) {
            throw $this->nextUpdateException;
        }

        return $this->nextUpdateResult;
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
    public array $searchCalls = [];
    public array $getJobCalls = [];

    /** @var array<int, array<string, mixed>> */
    public array $nextSearchResult = [];

    /** @var array<int, array<string, mixed>> */
    public array $nextJobs = [];

    public ?array $defaultJob = null;

    public function refreshJob(int $jobId): void
    {
        $this->refreshCalls[] = $jobId;
    }

    public function search(array $filters = []): array
    {
        $this->searchCalls[] = $filters;
        return $this->nextSearchResult;
    }

    public function getJob(int $jobId): ?array
    {
        $this->getJobCalls[] = $jobId;
        if (isset($this->nextJobs[$jobId])) {
            return $this->nextJobs[$jobId];
        }

        return $this->defaultJob;
    }
}

final class StubAnalytics implements JobAnalyticsInterface
{
    public array $published = [];
    public array $updated = [];
    public array $summaryCalls = [];

    /** @var array<string, mixed> */
    public array $nextSummary = ['summary' => []];

    public function recordJobPublished(int $jobId, array $jobData): void
    {
        $this->published[] = [$jobId, $jobData];
    }

    public function recordJobUpdated(int $jobId, array $jobData): void
    {
        $this->updated[] = [$jobId, $jobData];
    }

    public function summarise(?callable $candidateProfileResolver = null): array
    {
        $this->summaryCalls[] = $candidateProfileResolver;
        return $this->nextSummary;
    }
}

final class StubApplications implements JobApplicationManagerInterface
{
    public array $applyCalls = [];
    public array $listCalls = [];
    public array $getCalls = [];
    public array $listForJobCalls = [];

    /** @var array<string, mixed> */
    public array $nextApplyResult = ['application_id' => 42, 'errors' => []];

    /** @var array<int, array<string, mixed>> */
    public array $nextList = [];

    /** @var array<string, mixed>|null */
    public ?array $nextApplication = null;

    /** @var array<int, array<int, array<string, mixed>>> */
    public array $nextListForJob = [];

    public function applyToJob(int $jobId, int $candidateId, array $input): array
    {
        $this->applyCalls[] = [$jobId, $candidateId, $input];
        return $this->nextApplyResult;
    }

    public function listApplications(array $filters = []): array
    {
        $this->listCalls[] = $filters;
        return $this->nextList;
    }

    public function getApplication(int $applicationId): ?array
    {
        $this->getCalls[] = $applicationId;
        return $this->nextApplication;
    }

    public function listForJob(int $jobId): array
    {
        $this->listForJobCalls[] = $jobId;
        return $this->nextListForJob[$jobId] ?? [];
    }
}

/**
 * @return array{0: JobModuleFacade, 1: array{
 *     validator: StubValidator,
 *     authorizer: StubAuthorizer,
 *     repository: StubRepository,
 *     notifier: StubNotifier,
 *     search: StubSearch,
 *     analytics: StubAnalytics,
 *     applications: StubApplications
 * }}
 */
function makeFacade(): array
{
    $validator = new StubValidator();
    $authorizer = new StubAuthorizer();
    $repository = new StubRepository();
    $notifier = new StubNotifier();
    $search = new StubSearch();
    $analytics = new StubAnalytics();
    $applications = new StubApplications();

    $facade = new JobModuleFacade($validator, $authorizer, $repository, $notifier, $search, $analytics, $applications);

    return [$facade, compact('validator', 'authorizer', 'repository', 'notifier', 'search', 'analytics', 'applications')];
}

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function test_validate_job_input_delegates_to_validator(): void
{
    [$facade, $doubles] = makeFacade();
    $doubles['validator']->nextPublishResult = ['data' => ['foo' => 'bar'], 'errors' => ['baz' => 'qux']];

    [$data, $errors] = $facade->validateJobInput('Employer', 7, ['job_title' => 'Example']);

    expect($data === ['foo' => 'bar'], 'Validation data should be returned.');
    expect($errors === ['baz' => 'qux'], 'Validation errors should be returned.');
    expect($doubles['validator']->publishCalls === [['Employer', 7, ['job_title' => 'Example']]], 'Validator publish call expected.');
}

function test_publish_job_success_triggers_collaborators(): void
{
    [$facade, $doubles] = makeFacade();
    $doubles['validator']->nextPublishResult = [
        'data' => [
            'company_id' => 7,
            'recruiter_id' => null,
            'question_ids' => [5, 6],
            'job_title' => 'Example job',
        ],
        'errors' => [],
    ];
    $doubles['repository']->nextJobId = 123;

    [$jobId, $errors] = $facade->publishJob('Employer', 7, ['job_title' => 'Example job']);

    expect($jobId === 123, 'Repository job ID should be returned.');
    expect($errors === [], 'No errors expected when publish succeeds.');
    expect(count($doubles['validator']->publishCalls) === 1, 'Validator should be invoked once.');
    expect(count($doubles['authorizer']->publishCalls) === 1, 'Authorizer should be invoked once.');
    expect(count($doubles['repository']->createCalls) === 1, 'Repository create should be called.');
    expect($doubles['notifier']->jobPublished === [[123, $doubles['repository']->createCalls[0][0]]], 'Notifier should receive job data.');
    expect($doubles['search']->refreshCalls === [123], 'Search refresh should be triggered.');
    expect($doubles['analytics']->published === [[123, $doubles['repository']->createCalls[0][0]]], 'Analytics publish event recorded.');
}

function test_publish_job_validation_errors_short_circuit(): void
{
    [$facade, $doubles] = makeFacade();
    $doubles['validator']->nextPublishResult = ['data' => [], 'errors' => ['title' => 'Required']];

    [$jobId, $errors] = $facade->publishJob('Employer', 7, []);

    expect($jobId === 0, 'Job ID should be zero on validation failure.');
    expect($errors === ['title' => 'Required'], 'Validation errors should be returned.');
    expect($doubles['repository']->createCalls === [], 'Repository should not be called when validation fails.');
    expect($doubles['notifier']->jobPublished === [], 'Notifier should not be called when validation fails.');
}

function test_publish_job_authorization_failure(): void
{
    [$facade, $doubles] = makeFacade();
    $doubles['authorizer']->nextPublishException = new RuntimeException('Denied');

    [$jobId, $errors] = $facade->publishJob('Employer', 7, ['job_title' => 'Example']);

    expect($jobId === 0, 'Job ID should be zero on authorization failure.');
    expect($errors === ['general' => 'Denied'], 'Authorization message should be surfaced.');
    expect($doubles['repository']->createCalls === [], 'Repository should not be invoked if authorization fails.');
}

function test_publish_job_repository_failure(): void
{
    [$facade, $doubles] = makeFacade();
    $doubles['repository']->nextCreateException = new RuntimeException('DB error');

    [$jobId, $errors] = $facade->publishJob('Employer', 7, ['job_title' => 'Example']);

    expect($jobId === 0, 'Job ID should be zero on repository failure.');
    expect($errors === ['general' => 'Could not create job.'], 'Generic repository error should be returned.');
    expect($doubles['notifier']->jobPublished === [], 'Notifier should not run when repository fails.');
}

function test_update_job_success_triggers_collaborators(): void
{
    [$facade, $doubles] = makeFacade();
    $doubles['validator']->nextUpdateResult = [
        'data' => [
            'company_id' => 1,
            'question_ids' => [1, 2],
            'job_title' => 'Updated',
        ],
        'errors' => [],
    ];

    [$updated, $errors] = $facade->updateJob(55, 'Employer', 9, ['job_title' => 'Updated'], ['company_id' => 1]);

    expect($updated === true, 'Update should succeed.');
    expect($errors === [], 'No errors expected on update.');
    expect(count($doubles['validator']->updateCalls) === 1, 'Validator update should be called once.');
    expect(count($doubles['authorizer']->updateCalls) === 1, 'Authorizer update should be called once.');
    expect(count($doubles['repository']->updateCalls) === 1, 'Repository update should be called once.');
    expect($doubles['notifier']->jobUpdated === [[55, $doubles['repository']->updateCalls[0][1]]], 'Notifier should receive update payload.');
    expect($doubles['search']->refreshCalls === [55], 'Search refresh should occur for job update.');
    expect($doubles['analytics']->updated === [[55, $doubles['repository']->updateCalls[0][1]]], 'Analytics update event recorded.');
}

function test_update_job_validation_errors_short_circuit(): void
{
    [$facade, $doubles] = makeFacade();
    $doubles['validator']->nextUpdateResult = ['data' => [], 'errors' => ['title' => 'Required']];

    [$updated, $errors] = $facade->updateJob(55, 'Employer', 9, []);

    expect($updated === false, 'Update should fail.');
    expect($errors === ['title' => 'Required'], 'Validation errors should be returned.');
    expect($doubles['repository']->updateCalls === [], 'Repository should not be called when validation fails.');
}

function test_update_job_authorization_failure(): void
{
    [$facade, $doubles] = makeFacade();
    $doubles['authorizer']->nextUpdateException = new RuntimeException('Forbidden');

    [$updated, $errors] = $facade->updateJob(55, 'Employer', 9, ['job_title' => 'X']);

    expect($updated === false, 'Update should fail when authorization fails.');
    expect($errors === ['general' => 'Forbidden'], 'Authorization message should be surfaced.');
    expect($doubles['repository']->updateCalls === [], 'Repository should not be called when authorization fails.');
}

function test_update_job_repository_failure(): void
{
    [$facade, $doubles] = makeFacade();
    $doubles['repository']->nextUpdateException = new RuntimeException('DB error');

    [$updated, $errors] = $facade->updateJob(55, 'Employer', 9, ['job_title' => 'X']);

    expect($updated === false, 'Update should fail when repository throws.');
    expect($errors === ['general' => 'Could not update job.'], 'Generic repository error should be returned.');
}

function test_update_job_not_found_returns_error(): void
{
    [$facade, $doubles] = makeFacade();
    $doubles['repository']->nextUpdateResult = false;

    [$updated, $errors] = $facade->updateJob(55, 'Employer', 9, ['job_title' => 'X']);

    expect($updated === false, 'Update should fail when repository returns false.');
    expect($errors === ['general' => 'Job not found.'], 'Not found error expected.');
    expect($doubles['notifier']->jobUpdated === [], 'Notifier should not run when update fails.');
}

function test_list_jobs_delegates_to_search(): void
{
    [$facade, $doubles] = makeFacade();
    $doubles['search']->nextSearchResult = [
        ['job_posting_id' => 1],
        ['job_posting_id' => 2],
    ];

    $result = $facade->listJobs([
        'status' => 'active',
        'company_id' => '5',
        'scope' => 'recruiter-8',
    ]);

    expect($doubles['search']->searchCalls === [[
        'status' => 'active',
        'company_id' => 5,
        'recruiter_id' => 8,
    ]], 'Search filters should be normalised and passed to search service.');
    expect($result['jobs'] === $doubles['search']->nextSearchResult, 'Search results should be returned.');
    expect($result['count'] === 2, 'Count should match number of jobs.');
    expect($result['filters'] === ['status' => 'active', 'company_id' => 5, 'recruiter_id' => 8], 'Filters should be echoed back.');
}

function test_show_job_fetches_applications(): void
{
    [$facade, $doubles] = makeFacade();
    $doubles['search']->nextJobs[42] = ['job_posting_id' => 42, 'title' => 'Example'];
    $doubles['applications']->nextListForJob[42] = [
        ['application_id' => 1],
        ['application_id' => 2],
    ];

    $result = $facade->showJob(42);

    expect($doubles['search']->getJobCalls === [42], 'Search should be asked for the job.');
    expect($doubles['applications']->listForJobCalls === [42], 'Applications should be loaded for job.');
    expect($result['job']['application_count'] === 2, 'Application count should be attached.');
    expect(count($result['job']['applications']) === 2, 'Applications array should be included.');
}

function test_show_job_not_found_throws(): void
{
    [$facade] = makeFacade();

    try {
        $facade->showJob(99);
        expect(false, 'Expected exception when job missing.');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage() === 'Job posting not found.', 'Expected not found message.');
    }
}

function test_list_applications_delegates_to_manager(): void
{
    [$facade, $doubles] = makeFacade();
    $doubles['applications']->nextList = [
        ['application_id' => 1],
    ];

    $result = $facade->listApplications(['job_id' => 55]);

    expect($doubles['applications']->listCalls === [['job_id' => 55]], 'Application manager should receive filters.');
    expect($result['applications'] === $doubles['applications']->nextList, 'Application list should be returned.');
    expect($result['count'] === 1, 'Application count should be returned.');
}

function test_show_application_invokes_profile_resolver(): void
{
    [$facade, $doubles] = makeFacade();
    $doubles['applications']->nextApplication = [
        'application_id' => 71,
        'candidate' => ['candidate_id' => 88],
    ];

    $resolvedIds = [];
    $result = $facade->showApplication(71, function (int $candidateId) use (&$resolvedIds) {
        $resolvedIds[] = $candidateId;
        return ['id' => $candidateId, 'bio' => 'Example'];
    });

    expect($doubles['applications']->getCalls === [71], 'Application manager should be asked for application.');
    expect($resolvedIds === [88], 'Resolver should be invoked with candidate ID.');
    expect($result['application']['profile'] === ['id' => 88, 'bio' => 'Example'], 'Resolved profile should be attached.');
}

function test_show_application_not_found_throws(): void
{
    [$facade] = makeFacade();

    try {
        $facade->showApplication(123);
        expect(false, 'Expected exception when application missing.');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage() === 'Application record not found.', 'Expected application not found message.');
    }
}

function test_summarise_delegates_to_analytics(): void
{
    [$facade, $doubles] = makeFacade();
    $doubles['analytics']->nextSummary = ['summary' => ['jobs' => 3]];

    $result = $facade->summarise(fn (int $candidateId) => ['id' => $candidateId]);

    expect(count($doubles['analytics']->summaryCalls) === 1, 'Analytics summarise should be called once.');
    expect($result === ['summary' => ['jobs' => 3]], 'Summary payload should be returned.');
}

function test_apply_to_job_success_notifies(): void
{
    [$facade, $doubles] = makeFacade();
    $doubles['applications']->nextApplyResult = ['application_id' => 555, 'errors' => []];

    $result = $facade->applyToJob(10, 20, ['answers' => []]);

    expect($doubles['authorizer']->applicationCalls === [[10, 20]], 'Authorizer should be consulted for application.');
    expect($doubles['applications']->applyCalls[0][0] === 10, 'Application manager should receive job ID.');
    expect($result['application_id'] === 555, 'Application ID should propagate.');
    expect($doubles['notifier']->applicationSubmitted === [555], 'Notifier should be triggered on success.');
}

function test_apply_to_job_returns_errors_without_notifying(): void
{
    [$facade, $doubles] = makeFacade();
    $doubles['applications']->nextApplyResult = ['application_id' => 777, 'errors' => ['general' => 'Already applied']];

    $result = $facade->applyToJob(10, 20, []);

    expect($result['errors'] === ['general' => 'Already applied'], 'Errors from manager should pass through.');
    expect($doubles['notifier']->applicationSubmitted === [], 'Notifier should not trigger when errors exist.');
}

function test_apply_to_job_authorization_failure_short_circuits(): void
{
    [$facade, $doubles] = makeFacade();
    $doubles['authorizer']->nextApplicationException = new RuntimeException('Not allowed');

    $result = $facade->applyToJob(10, 20, []);

    expect($result['application_id'] === 0, 'Application ID should be zero when authorization fails.');
    expect($result['errors'] === ['general' => 'Not allowed'], 'Authorization message should be returned.');
    expect($doubles['applications']->applyCalls === [], 'Application manager should not be called when authorization fails.');
}

$tests = [
    'test_validate_job_input_delegates_to_validator',
    'test_publish_job_success_triggers_collaborators',
    'test_publish_job_validation_errors_short_circuit',
    'test_publish_job_authorization_failure',
    'test_publish_job_repository_failure',
    'test_update_job_success_triggers_collaborators',
    'test_update_job_validation_errors_short_circuit',
    'test_update_job_authorization_failure',
    'test_update_job_repository_failure',
    'test_update_job_not_found_returns_error',
    'test_list_jobs_delegates_to_search',
    'test_show_job_fetches_applications',
    'test_show_job_not_found_throws',
    'test_list_applications_delegates_to_manager',
    'test_show_application_invokes_profile_resolver',
    'test_show_application_not_found_throws',
    'test_summarise_delegates_to_analytics',
    'test_apply_to_job_success_notifies',
    'test_apply_to_job_returns_errors_without_notifying',
    'test_apply_to_job_authorization_failure_short_circuits',
];

foreach ($tests as $test) {
    $test();
}

echo "JobModuleFacade tests passed\n";
