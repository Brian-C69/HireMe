# Design Patterns for HireMe Modules

The HireMe codebase organises cross-cutting features (user management, resumes, jobs, payments, and administration) as discrete **module services**. Each service extends `AbstractModuleService` and is registered with the `ModuleRegistry`, which forwards in-process requests between modules. Rather than the Strategy/Builder/Facade/Observer/Command patterns that were originally proposed, the production code primarily relies on the **Transaction Script** style (as defined by Fowler) backed by Laravel's Active Record models and a lightweight service locator (`ModuleRegistry`). This document reflects the actual implementation choices in the repository.

## 1. User Management & Authentication Module — Transaction Script with Registry Collaboration (ZX)

### Design Pattern
`UserManagementService` (`app/Services/Modules/UserManagementService.php`) processes each inbound request as an isolated transaction script. The `handle()` dispatcher examines the requested operation (`users`, `user`, `authenticate`) and defers to focused helper methods that perform the necessary database work via Eloquent models. Whenever additional context is required (profiles, resumes, payments), the module relies on `forward()` from `AbstractModuleService`, which delegates to `ModuleRegistry`—a service-locator style registry that mediates module-to-module calls.

Authentication is implemented directly inside the same transaction script. The service iterates the configured role models, loads a record by e-mail, and applies PHP's `password_verify` to the stored hash. The separate `AuthService` (`app/Services/Auth/AuthService.php`) is a utility responsible for issuing, hashing, and revoking API tokens; it does not select strategies at runtime.

### Implementation & Coding
```php
public function handle(string $type, ?string $id, Request $request): array
{
    return match (strtolower($type)) {
        'users' => $this->listUsers($request, $id),
        'user' => $this->showUser($request, $id),
        'authenticate', 'auth', 'login' => $this->authenticateUser($request),
        default => throw new InvalidArgumentException(sprintf('Unknown user management operation "%s".', $type)),
    };
}
```

```php
foreach ($rolesToCheck as $role) {
    $modelClass = $this->modelForRole($role);
    if ($modelClass === null) {
        continue;
    }

    /** @var Model|null $user */
    $user = $modelClass::query()->where('email', $email)->first();
    if ($user === null) {
        continue;
    }

    $data = $user->toArray();
    $hash = $data['password_hash'] ?? null;
    if (is_string($hash) && $hash !== '' && password_verify($password, $hash)) {
        unset($data['password_hash']);

        return $this->respond([
            'authenticated' => true,
            'role' => $role,
            'user' => $data,
        ]);
    }
}
```

```php
final class AuthService
{
    public function issueToken(User $user): ?string
    {
        $token = $this->generateToken();
        if ($token === null) {
            return null;
        }

        return $this->persistToken($user, $this->hashToken($token)) ? $token : null;
    }

    public function userByToken(?string $token): ?User
    {
        $normalised = $this->normaliseToken($token);
        if ($normalised === null) {
            return null;
        }

        $hashed = $this->hashToken($normalised);
        return $this->findUserByStoredToken($hashed) ?: $this->findUserByStoredToken($normalised);
    }
}
```

### Justification
The transaction-script approach keeps the user module easy to reason about: each API-style operation is encoded in a single method that orchestrates validation, querying, and response shaping. Because user records span multiple polymorphic tables (`Candidate`, `Employer`, `Recruiter`, `Admin`), the imperative loop across role models provides a simple, testable way to normalise credentials without over-engineering abstractions. Leveraging `ModuleRegistry` to call other modules avoids circular dependencies while still sharing data for dashboards and related resources.

---

## 2. Resume & Profile Management Module — Transaction Script over Active Record (YX)

### Design Pattern
The resume/profile functionality lives in `ResumeProfileService` (`app/Services/Modules/ResumeProfileService.php`) and the supporting `ResumeService` (`app/Services/ResumeService.php`). Both follow the same transaction-script paradigm: each public operation (`resumes`, `resume`, `profiles`, `profile`) maps to a method that queries the relevant Eloquent models, assembles arrays, and returns a payload. Generated resume files are produced imperatively inside `ResumeService::generate()`—the service opens a database transaction, calls helper methods to render HTML, writes the file to disk, and records metadata.

No builder abstraction orchestrates resume assembly. Instead, helper methods like `renderResumeTemplate()` concatenate HTML strings, and `buildGeneratedResume()` manages filesystem concerns directly. Optional joins to other modules use `forward()` so the resume module can embed user or application information without direct coupling.

### Implementation & Coding
```php
public function handle(string $type, ?string $id, Request $request): array
{
    return match (strtolower($type)) {
        'resumes' => $this->listResumes($request, $id),
        'resume' => $this->showResume($id),
        'profiles' => $this->listProfiles($request),
        'profile' => $this->showProfile($id),
        default => throw new InvalidArgumentException(sprintf('Unknown resume/profile operation "%s".', $type)),
    };
}
```

```php
return $this->entityManager->transaction(function () use ($candidateId, $data) {
    $relativePath = $this->buildGeneratedResume($candidateId, $data);

    $builder = $this->builders->create([
        'candidate_id' => $candidateId,
        'template' => $data['template'] ?? 'modern',
        'data' => $data,
        'generated_path' => $relativePath,
    ]);

    $resume = $this->resumes->create([
        'candidate_id' => $candidateId,
        'title' => $data['title'] ?? 'Generated Resume',
        'file_path' => $relativePath,
        'content' => $this->encodeResumeData($data),
        'is_generated' => true,
        'visibility' => $data['visibility'] ?? 'private',
    ]);

    $this->notifications->notify($candidateId, 'Resume generated', [
        'resume_id' => $resume->getKey(),
        'builder_id' => $builder->getKey(),
    ]);

    return $resume;
});
```

```php
$contactParts = [];
foreach (['email', 'phone', 'location'] as $field) {
    if (!empty($data[$field])) {
        $contactParts[] = '<span>' . $this->escape((string) $data[$field]) . '</span>';
    }
}
$contact = $contactParts ? '<div class="contact">' . implode(' • ', $contactParts) . '</div>' : '';

$experienceItems = '';
foreach ((array) ($data['experience'] ?? []) as $item) {
    $role = trim((string) ($item['role'] ?? ($item['title'] ?? '')));
    if ($role === '') {
        continue;
    }
    // ...compose list entries...
}
```

### Justification
Resumes are primarily persisted as Active Record rows and stored HTML blobs; the transaction-script model matches that use case. Each method reads or writes a handful of tables, handles notifications, and finishes. Introducing a Builder pattern would add indirection without reducing complexity, whereas the current imperative code keeps template rendering, file management, and repository writes together in one workflow. Using `ModuleRegistry` for lookups (e.g., fetching candidate details when showing a profile) keeps cross-module queries consistent with the rest of the system.

---

## 3. Job Posting & Application Module — Transaction Script Aggregator (FW)

### Design Pattern
`JobApplicationService` (`app/Services/Modules/JobApplicationService.php`) exposes job and application data. As with other modules, `handle()` routes each request type (`jobs`, `job`, `applications`, `application`, `summary`) to an imperative method that directly queries the corresponding Eloquent models. These methods eagerly load relations (employer, recruiter, candidate) and map them into associative arrays. When richer context is required—such as embedding a candidate's resume in an application view—the module relies on `forward('resume-profile', ...)` to invoke another module.

### Implementation & Coding
```php
private function showJob(?string $id): array
{
    $jobId = $this->requireIntId($id, 'A job identifier is required.');
    $job = JobPosting::query()->with(['employer', 'recruiter'])->find($jobId);
    if ($job === null) {
        throw new InvalidArgumentException('Job posting not found.');
    }

    $payload = $job->toArray();
    $employer = $job->employer;
    if ($employer instanceof Model) {
        $payload['employer'] = $employer->toArray();
    }
    $recruiter = $job->recruiter;
    if ($recruiter instanceof Model) {
        $payload['recruiter'] = $recruiter->toArray();
    }

    $applications = Application::query()
        ->where('job_posting_id', $jobId)
        ->with('candidate')
        ->get();

    $payload['applications'] = $applications->map(static function (Application $application): array {
        $data = $application->toArray();
        $candidate = $application->candidate;
        if ($candidate instanceof Model) {
            $data['candidate'] = $candidate->toArray();
        }
        return $data;
    })->all();
    $payload['application_count'] = $applications->count();

    return $this->respond([
        'job' => $payload,
    ]);
}
```

```php
private function summarise(): array
{
    $totalJobs = JobPosting::query()->count();
    $activeJobs = JobPosting::query()->whereIn('status', ['active', 'open'])->count();
    $closedJobs = JobPosting::query()->whereIn('status', ['closed', 'archived'])->count();

    $topCandidates = Application::query()
        ->selectRaw('candidate_id, COUNT(*) as total_applications')
        ->whereNotNull('candidate_id')
        ->groupBy('candidate_id')
        ->orderByDesc('total_applications')
        ->take(5)
        ->get()
        ->map(function ($row) {
            $candidateId = (string) $row->candidate_id;
            $profile = $this->forward('resume-profile', 'profile', $candidateId);
            return [
                'candidate_id' => (int) $row->candidate_id,
                'applications' => (int) $row->total_applications,
                'profile' => $profile['profile'] ?? null,
                'resume' => $profile['resume'] ?? null,
            ];
        })
        ->all();

    return $this->respond([
        'summary' => [
            'jobs' => [
                'total' => $totalJobs,
                'active' => $activeJobs,
                'closed' => $closedJobs,
            ],
            'applications' => [
                'total' => Application::query()->count(),
                'recent' => Application::query()->orderByDesc('created_at')->take(5)->get()->map(static fn (Application $application) => $application->toArray())->all(),
            ],
            'top_candidates' => $topCandidates,
        ],
    ]);
}
```

### Justification
The job module's responsibilities revolve around assembling dashboards and listings. Each endpoint translates to a single set of SQL queries followed by light transformation, which fits the transaction-script approach. Controllers or external clients receive denormalised payloads without needing to coordinate multiple repositories. By pushing cross-cutting lookups through `forward()`, the module stays loosely coupled while still presenting comprehensive views of jobs and applications.

---

## 4. Payment & Billing Module — Transaction Script with Cross-Module Enrichment (TC)

### Design Pattern
`PaymentBillingService` (`app/Services/Modules/PaymentBillingService.php`) implements payment and billing read models using the same pattern. Methods such as `listPayments()`, `showPayment()`, `listBilling()`, and `summarise()` are imperative scripts that filter `Payment` and `Billing` Active Record models based on query parameters, compute aggregates, and return the data in associative arrays. Observer-style event broadcasting is not present; instead, any enrichment (for example, attaching user details to a payment) is performed synchronously via module forwarding.

### Implementation & Coding
```php
private function showPayment(?string $id): array
{
    $paymentId = $this->requireIntId($id, 'A payment identifier is required.');
    $payment = Payment::find($paymentId);
    if ($payment === null) {
        throw new InvalidArgumentException('Payment record not found.');
    }

    $data = $payment->toArray();
    $role = $this->roleForUserType($data['user_type'] ?? null);
    if ($role !== null && isset($data['user_id']) && is_numeric($data['user_id'])) {
        $user = $this->forward('user-management', 'user', (string) $data['user_id'], [
            'role' => $role,
        ]);
        $data['user'] = $user['user'] ?? null;
    }

    return $this->respond([
        'payment' => $data,
    ]);
}
```

```php
$statusBreakdown = Payment::query()
    ->selectRaw('transaction_status, COUNT(*) as count, SUM(amount) as total_amount')
    ->groupBy('transaction_status')
    ->get()
    ->map(static function ($row): array {
        return [
            'status' => $row->transaction_status,
            'count' => (int) $row->count,
            'total_amount' => (float) $row->total_amount,
        ];
    })
    ->all();

$topUsers = Payment::query()
    ->selectRaw('user_type, user_id, SUM(amount) as total_amount, COUNT(*) as payments')
    ->whereNotNull('user_id')
    ->groupBy('user_type', 'user_id')
    ->orderByDesc('total_amount')
    ->take(5)
    ->get()
    ->map(function ($row): array {
        $role = $this->roleForUserType($row->user_type);
        $userDetails = $role !== null
            ? $this->forward('user-management', 'user', (string) $row->user_id, ['role' => $role])
            : null;

        return [
            'user_id' => (int) $row->user_id,
            'user_type' => $row->user_type,
            'total_amount' => (float) $row->total_amount,
            'payments' => (int) $row->payments,
            'user' => $userDetails['user'] ?? null,
        ];
    })
    ->all();
```

### Justification
Payment analytics in HireMe focus on reporting rather than asynchronous event handling, so the observer pattern would add overhead without concrete benefit. The current design keeps data retrieval linear and predictable: one method runs the queries, shapes the response, and (if necessary) enriches records by calling other modules. This provides a clear surface for dashboards while avoiding the complexity of maintaining observer registries or event buses.

---

## 5. Administration & Moderation Module — Transaction Script Dashboard (ZC)

### Design Pattern
`AdminModerationService` (`app/Services/Modules/AdminModerationService.php`) powers administrative dashboards through three scripts: `overview()`, `metrics()`, and `audit()`. Each script aggregates counts from core tables (`Candidate`, `Employer`, `JobPosting`, `Payment`) and stitches in cross-module data via `forward()` to user, job, or payment services. There is no command bus or discrete command objects; moderation actions are represented as read-only snapshots for administrators.

### Implementation & Coding
```php
private function overview(): array
{
    $userSnapshot = $this->forward('user-management', 'users', 'all');
    $jobSnapshot = $this->forward('job-application', 'summary', 'all');
    $financeSnapshot = $this->forward('payment-billing', 'summary', 'all');

    $pendingVerifications = Candidate::query()->where('verified_status', 'pending')->count();
    $flaggedJobs = JobPosting::query()->whereIn('status', ['flagged', 'under_review'])->count();

    return $this->respond([
        'overview' => [
            'users' => $userSnapshot,
            'jobs' => $jobSnapshot['summary'] ?? [],
            'finance' => $financeSnapshot['summary'] ?? [],
            'pending_verifications' => $pendingVerifications,
            'flagged_jobs' => $flaggedJobs,
        ],
    ]);
}
```

```php
$failedPayments = Payment::query()
    ->where('transaction_status', 'failed')
    ->orderByDesc('created_at')
    ->take(10)
    ->get()
    ->map(function (Payment $payment): array {
        $user = null;
        if ($payment->user_id !== null) {
            $role = $this->roleForUserType($payment->user_type);
            if ($role !== null) {
                $user = $this->forward('user-management', 'user', (string) $payment->user_id, [
                    'role' => $role,
                ]);
            }
        }

        return [
            'payment' => $payment->toArray(),
            'user' => $user['user'] ?? null,
        ];
    })
    ->all();
```

### Justification
Administrative reporting involves collating many small datasets rather than orchestrating complex write operations. The transaction-script methods encapsulate each report in a single location, making it easy to adjust queries or add additional fields. Because moderation workflows primarily consume data generated elsewhere, introducing a command pattern would not provide meaningful value; the current design keeps the admin module focused on read models while delegating any required mutations back to the appropriate module service.

---

## Shared Infrastructure — ModuleRegistry as a Service Locator

Across all modules, `ModuleRegistry` (`app/Services/Modules/ModuleRegistry.php`) acts as a central registry that wires services together. During bootstrapping, `ModuleRegistry::boot()` instantiates each module service, registers aliases, and injects the registry into services implementing `RegistryAwareInterface`. Subsequent calls to `forward()` leverage this registry to synchronously invoke other modules using a synthetic `Request` object built by `makeRequest()`.

This arrangement resembles the **Service Locator** pattern: modules look up one another by name at runtime instead of depending on compile-time interfaces. While service locators are often debated, in the current codebase they provide a simple way to share read-model data between modules without introducing HTTP calls or deep coupling.
