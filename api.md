
# HireMe API Integration Contract

All modules are exposed through the Laravel gateway at `http://localhost/HireMe/public/api/{module}/{type}/{id?}` using RESTful JSON payloads. Each response automatically includes a `module` field identifying the producer service. Optional query parameters are supplied via query string for GET requests and JSON bodies for non-GET requests.

### Administration & Moderation Module Quick Calls

- `GET http://localhost/HireMe/api/admin-moderation/overview` — Moderation overview dashboard data.
- `GET http://localhost/HireMe/api/admin-moderation/metrics` — Moderation metrics feed.
- `GET http://localhost/HireMe/api/admin-moderation/audit` — Moderation audit log feed.

Use these endpoints to explore administrative insights without additional headers or command-line tooling. Adjust the path segments to interact with other modules as needed.

---

## 1. User Management & Authentication Module

### 1.1 Login Verification & Session Context

Webservice Mechanism Service Exposure: Login Verification & Session Context
##### Protocol: RESTFUL
##### Function Description: 1. Validates candidate/employer/recruiter/admin credentials across role models 2. Issues a sanitized user payload and role context when authentication succeeds
##### Source Module: User Management & Authentication Module
##### Target Module: Resume & Profile Management, Job Posting & Application, Payment & Billing, Administration & Moderation
##### URL: http://localhost/HireMe/public/api/user-management/authenticate
##### Function Name: authenticateUser()

Web Services Request Parameter (provide)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| email | string | mandatory | Login email checked against all role directories. | pat@example.com |
| password | string | mandatory | Plaintext password verified against stored hashes. | My$ecret |
| role | string | optional | Role hint to limit the lookup (`candidates`, `employers`, `recruiters`, `admins`). | recruiters |

Web Services Response Parameter (consume)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| module | string | mandatory | Service emitter identifier. | user-management |
| authenticated | boolean | mandatory | Indicates whether authentication succeeded. | true |
| role | string | optional | Resolved role slug for the authenticated user. | recruiters |
| user | object | optional | Sanitized user resource with identifiers and profile keys. | {"id": 9, "email": "pat@example.com"} |
| message | string | optional | Failure reason returned when authentication fails. | Invalid credentials provided. |

### 1.2 User Snapshot with Cross-Module Includes

Webservice Mechanism Service Exposure: User Snapshot with Cross-Module Includes
##### Protocol: RESTFUL
##### Function Description: 1. Retrieves a specific user record and optional role-scoped view 2. Enriches the response with resume, application, job, payment, or billing data through module forwarding
##### Source Module: User Management & Authentication Module
##### Target Module: Resume & Profile Management, Job Posting & Application, Payment & Billing
##### URL: http://localhost/HireMe/public/api/user-management/user/{userId}
##### Function Name: showUser()

Web Services Request Parameter (provide)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| userId | path integer | mandatory | Numeric identifier for the requested user record. | 42 |
| role | string | optional | Role slug/alias to constrain lookup (`candidates`, `employers`, etc.). | employers |
| include | string | optional | Comma-separated related datasets (`profile`, `resume`, `applications`, `jobs`, `payments`, `billing`). | profile,resume |

Web Services Response Parameter (consume)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| module | string | mandatory | Service emitter identifier. | user-management |
| role | string | mandatory | Resolved role slug for the returned user. | employers |
| user | object | mandatory | User attributes excluding credentials. | {"id": 42, "company_name": "Acme"} |
| includes | array | optional | Echo of processed include keys. | ["profile","resume"] |
| related.profile | object | optional | Candidate profile forwarded from Resume/Profile. | {"candidate_id": 42, "city": "New York"} |
| related.resume | object | optional | Resume artifact forwarded from Resume/Profile. | {"id": 77, "rendered_format": "pdf"} |
| related.applications | array | optional | Applications array forwarded from Job Application service. | [{"id":501,"job_id":88}] |
| related.jobs | array | optional | Employer/recruiter job listings via Job Application service. | [{"id":88,"title":"Backend Engineer"}] |
| related.payments | array | optional | Payments ledger retrieved from Payment & Billing service. | [{"id":301,"amount":199.99}] |
| related.billing | array | optional | Billing ledger retrieved from Payment & Billing service. | [{"id":11,"status":"active"}] |
| message | string | optional | Validation or error message on lookup failure. | User not found |

#### Supporting Service Code

```php
// app/Services/Modules/UserManagementService.php
public function handle(string $type, ?string $id, Request $request): array
{
    return match (strtolower($type)) {
        'users' => $this->listUsers($request, $id),
        'user' => $this->showUser($request, $id),
        'authenticate', 'auth', 'login' => $this->authenticateUser($request),
        default => throw new InvalidArgumentException(sprintf('Unknown user management operation "%s".', $type)),
    };
}

/**
 * @return array<string, mixed>
 */
private function authenticateUser(Request $request): array
{
    $email = $this->query($request, 'email', null) ?? (string) $request->input('email', '');
    $password = $this->query($request, 'password', null) ?? (string) $request->input('password', '');
    if ($email === '' || $password === '') {
        throw new InvalidArgumentException('Email and password are required for authentication.');
    }

    $roleHint = $this->query($request, 'role') ?? (string) $request->input('role', '');
    $context = $this->adminContext($request, [
        'action' => 'users.authenticate',
        'email' => $email,
        'role_hint' => $roleHint,
    ]);

    $this->adminGuardian()->audit('users.authenticate.attempt', $context);

    $rolesToCheck = [];
    if ($roleHint !== '') {
        $role = $this->normaliseRole($roleHint);
        if ($role === null) {
            throw new InvalidArgumentException(sprintf('Unknown user role "%s".', $roleHint));
        }
        $rolesToCheck[] = $role;
    } else {
        $rolesToCheck = array_keys(self::ROLE_MODELS);
    }

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

            $userId = $data[$user->getKeyName()] ?? null;
            $successContext = $context + ['role' => $role];
            if ($userId !== null && (is_int($userId) || ctype_digit((string) $userId))) {
                $successContext['user_id'] = (int) $userId;
            }

            $this->adminGuardian()->assertRead('users', $successContext);
            $this->adminGuardian()->audit('users.authenticate.success', $successContext);
            $this->adminArbiter()->dispatch('admin.authenticated', [
                'status' => 'success',
                'role' => $role,
                'email' => $email,
                'user_id' => $successContext['user_id'] ?? null,
            ]);
            $this->adminArbiter()->flush();

            return $this->respond([
                'authenticated' => true,
                'role' => $role,
                'user' => $data,
            ]);
        }
    }

    $this->adminGuardian()->flag('users.authenticate', $context + ['status' => 'failed']);
    $this->adminArbiter()->dispatch('admin.authenticated', [
        'status' => 'failed',
        'email' => $email,
        'role_hint' => $roleHint,
    ]);
    $this->adminArbiter()->flush();

    return $this->respond([
        'authenticated' => false,
        'message' => 'Invalid credentials provided.',
    ]);
}
```

---

## 2. Resume & Profile Management Module

### 2.1 Resume Catalogue & Candidate Summary

Webservice Mechanism Service Exposure: Resume Catalogue & Candidate Summary
##### Protocol: RESTFUL
##### Function Description: 1. Lists resumes optionally filtered by candidate scope 2. Embeds candidate information for downstream enrichment
##### Source Module: Resume & Profile Management Module
##### Target Module: User Management & Authentication, Job Posting & Application, Administration & Moderation
##### URL: http://localhost/HireMe/public/api/resume-profile/resumes/{candidateId?}
##### Function Name: listResumes()

Web Services Request Parameter (provide)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| candidateId | path integer | optional | Restricts the listing to resumes owned by a specific candidate. | 15 |
| candidate_id | query string | optional | Alternate query filter for candidate scope. | 15 |

Web Services Response Parameter (consume)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| module | string | mandatory | Service emitter identifier. | resume-profile |
| resumes | array | mandatory | Resume records with embedded candidate data. | [{"id":77,"candidate":{"id":15,"full_name":"Jane Doe"}}] |
| count | integer | optional | Total resumes returned in this result set. | 3 |
| filters.candidate_id | integer | optional | Echo of the applied candidate filter. | 15 |

### 2.2 Profile Dossier with Latest Resume & User Snapshot

Webservice Mechanism Service Exposure: Profile Dossier with Latest Resume & User Snapshot
##### Protocol: RESTFUL
##### Function Description: 1. Aggregates candidate profile data with latest resume rendering 2. Forwards to User Management to attach the owning user account snapshot
##### Source Module: Resume & Profile Management Module
##### Target Module: User Management & Authentication, Job Posting & Application
##### URL: http://localhost/HireMe/public/api/resume-profile/profile/{candidateId}
##### Function Name: showProfile()

Web Services Request Parameter (provide)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| candidateId | path integer | mandatory | Candidate identifier used to load profile and resume. | 15 |

Web Services Response Parameter (consume)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| module | string | mandatory | Service emitter identifier. | resume-profile |
| profile | object | mandatory | Candidate profile attributes. | {"candidate_id":15,"summary":"Full-stack developer"} |
| resume | object | optional | Latest resume content plus rendered metadata. | {"id":77,"rendered_format":"pdf"} |
| user | object | optional | User snapshot fetched from User Management. | {"id":15,"email":"talent@example.com"} |

#### Supporting Service Code

```php
// app/Services/Modules/ResumeProfileService.php
public function handle(string $type, ?string $id, Request $request): array
{
    return match (strtolower($type)) {
        'resumes' => $this->listResumes($request, $id),
        'resume' => $this->showResume($request, $id),
        'profiles' => $this->listProfiles($request),
        'profile' => $this->showProfile($request, $id),
        default => throw new InvalidArgumentException(sprintf('Unknown resume/profile operation "%s".', $type)),
    };
}

/**
 * @return array<string, mixed>
 */
private function listResumes(Request $request, ?string $scope): array
{
    $query = Resume::query()->with('candidate');

    $candidateId = null;
    if ($scope !== null && $scope !== '' && $scope !== 'all' && ctype_digit($scope)) {
        $candidateId = (int) $scope;
    }

    $candidateQuery = $this->query($request, 'candidate_id');
    if ($candidateId === null && $candidateQuery !== null && ctype_digit($candidateQuery)) {
        $candidateId = (int) $candidateQuery;
    }

    if ($candidateId !== null) {
        $query->where('candidate_id', $candidateId);
    }

    $this->adminGuardian()->assertRead('resumes', $this->adminContext($request, [
        'action' => 'resumes.list',
        'candidate_id' => $candidateId,
    ]));

    $resumes = $query->orderByDesc('updated_at')->get();

    $items = $resumes->map(static function (Resume $resume): array {
        $data = $resume->toArray();
        $candidate = $resume->candidate;
        if ($candidate instanceof Model) {
            $data['candidate'] = $candidate->toArray();
        }
        return $data;
    })->all();

    return $this->respond([
        'resumes' => $items,
        'count' => count($items),
        'filters' => [
            'candidate_id' => $candidateId,
        ],
    ]);
}

/**
 * @return array<string, mixed>
 */
private function showProfile(Request $request, ?string $id): array
{
    $candidateId = $this->requireIntId($id, 'A candidate identifier is required.');
    $candidate = Candidate::find($candidateId);
    if ($candidate === null) {
        throw new InvalidArgumentException('Candidate profile not found.');
    }

    $this->adminGuardian()->assertRead('profiles', $this->adminContext($request, [
        'action' => 'profiles.show',
        'candidate_id' => $candidateId,
    ]));

    $resume = Resume::query()->where('candidate_id', $candidateId)->orderByDesc('updated_at')->first();

    $userDetails = $this->forward('user-management', 'user', (string) $candidateId, [
        'role' => 'candidates',
    ]);

    $resumeData = null;
    if ($resume !== null) {
        $resumeData = $resume->toArray();
        $rendered = $this->renderResumeOutput($resume);
        if ($rendered !== null) {
            $resumeData['rendered_resume'] = $rendered['output'];
            $resumeData['rendered_format'] = $rendered['format'];
        }
    }

    return $this->respond([
        'profile' => $candidate->toArray(),
        'resume' => $resumeData,
        'user' => $userDetails['user'] ?? null,
    ]);
}
```
| Webservice Mechanism | HTTP | URL Pattern | Function Name | Primary Purpose |
|----------------------|------|-------------|---------------|-----------------|
| Job Listings | GET | `/public/api/job-application/jobs/{scope?}` | `listJobs()` | Filterable listing by status, employer, recruiter, or custom scope hint.【F:app/Services/Modules/JobApplicationService.php†L29-L71】 |
| Job Detail | GET | `/public/api/job-application/job/{id}` | `showJob()` | Retrieve a single job posting with full facade-provided context.【F:app/Services/Modules/JobApplicationService.php†L73-L86】 |
| Application Listings | GET | `/public/api/job-application/applications` | `listApplications()` | Filterable by `job_id` and/or `candidate_id` and includes guardian auditing.【F:app/Services/Modules/JobApplicationService.php†L88-L118】 |
| Application Detail | GET | `/public/api/job-application/application/{id}` | `showApplication()` | Returns an application plus candidate dossier via Resume/Profile service.【F:app/Services/Modules/JobApplicationService.php†L120-L138】 |
| Jobs Summary | GET | `/public/api/job-application/summary/all` | `summarise()` | Aggregated reporting (counts, highlights) for dashboards with candidate enrichment.【F:app/Services/Modules/JobApplicationService.php†L140-L154】 |

---

## 3. Job Posting & Application Module

| Webservice Mechanism | HTTP | URL Pattern | Function Name | Primary Purpose |
|----------------------|------|-------------|---------------|-----------------|
| Job Listings | GET | `/public/api/job-application/jobs/{scope?}` | `listJobs()` | Filterable listing by status, employer, recruiter, or custom scope hint. |
| Job Detail | GET | `/public/api/job-application/job/{id}` | `showJob()` | Retrieve a single job posting with full facade-provided context. |
| Application Listings | GET | `/public/api/job-application/applications` | `listApplications()` | Filterable by `job_id` and/or `candidate_id` and includes guardian auditing. |
| Application Detail | GET | `/public/api/job-application/application/{id}` | `showApplication()` | Returns an application plus candidate dossier via Resume/Profile service. |
| Jobs Summary | GET | `/public/api/job-application/summary/all` | `summarise()` | Aggregated reporting (counts, highlights) for dashboards with candidate enrichment. |

### 3.1 Job Listings with Recruiter/Employer Filters

Webservice Mechanism Service Exposure: Job Listings with Recruiter/Employer Filters
##### Protocol: RESTFUL
##### Function Description: 1. Provides job listings filtered by status, employer, recruiter, or scope 2. Supports admin guardian auditing for job directory access
##### Source Module: Job Posting & Application Module
##### Target Module: Administration & Moderation, User Management & Authentication, Resume & Profile Management
##### URL: http://localhost/HireMe/public/api/job-application/jobs/{scope?}
##### Function Name: listJobs()

Web Services Request Parameter (provide)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| scope | path string | optional | Custom listing scope segment (e.g., portal views). | employer-portal |
| status | query string | optional | Job lifecycle filter (`active`, `closed`, etc.). | active |
| employer_id | query string | optional | Restrict jobs to a numeric employer account. | 12 |
| recruiter_id | query string | optional | Restrict jobs to a numeric recruiter account. | 9 |

Web Services Response Parameter (consume)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| module | string | mandatory | Service emitter identifier. | job-application |
| jobs | array | mandatory | Job posting data with company and status fields. | [{"id":201,"title":"Backend Engineer","status":"active"}] |
| count | integer | optional | Total jobs returned (when supplied by facade). | 24 |
| filters | object | optional | Echo of filters applied to the listing. | {"status":"active"} |

### 3.2 Application Detail with Candidate Dossier

##### Webservice Mechanism Service Exposure: Application Detail with Candidate Dossier
##### Protocol: RESTFUL
##### Function Description: 1. Retrieves a specific job application record 2. Invokes Resume/Profile service to attach candidate dossier data
##### Source Module: Job Posting & Application Module
##### Target Module: Resume & Profile Management, Administration & Moderation
##### URL: http://localhost/HireMe/public/api/job-application/application/{applicationId}
##### Function Name: showApplication()

Web Services Request Parameter (provide)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| applicationId | path integer | mandatory | Unique identifier for the application record. | 501 |

Web Services Response Parameter (consume)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| module | string | mandatory | Service emitter identifier. | job-application |
| application | object | mandatory | Application attributes including status and job references. | {"id":501,"job_id":88,"status":"submitted"} |
| candidate | object | optional | Candidate dossier retrieved from Resume/Profile. | {"candidate_id":55,"full_name":"Jane Doe"} |
| job | object | optional | Job metadata forwarded from job facade. | {"id":88,"title":"Backend Engineer"} |

#### Supporting Service Code

```php
// app/Services/Modules/JobApplicationService.php
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
```

---

## 4. Payment & Billing Module

### 4.1 Payment Ledger & Status Breakdown

Webservice Mechanism Service Exposure: Payment Ledger & Status Breakdown
##### Protocol: RESTFUL
##### Function Description: 1. Returns payments filtered by status, user type, or scoped identifier 2. Provides counts and filter echoes for financial reconciliations
##### Source Module: Payment & Billing Module
##### Target Module: Administration & Moderation, User Management & Authentication
##### URL: http://localhost/HireMe/public/api/payment-billing/payments/{scope?}
##### Function Name: listPayments()

Web Services Request Parameter (provide)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| scope | path string | optional | Status segment (`pending`, `completed`, etc.) or `user-{id}` shortcut. | user-24 |
| status | query string | optional | Explicit transaction status filter. | completed |
| user_type | query string | optional | Role associated with the payment (`candidates`, `employers`, etc.). | employers |
| user_id | query string | optional | Numeric account identifier when filtering ledger entries. | 24 |

Web Services Response Parameter (consume)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| module | string | mandatory | Service emitter identifier. | payment-billing |
| payments | array | mandatory | Payment records returned for the filters. | [{"id":301,"amount":199.99,"transaction_status":"completed"}] |
| count | integer | optional | Total number of payments in the response. | 8 |
| filters.status | string | optional | Echo of the applied status filter. | completed |

### 4.2 Charge Processing & Billing Linkage

Webservice Mechanism Service Exposure: Charge Processing & Billing Linkage
##### Protocol: RESTFUL
##### Function Description: 1. Validates and processes a payment charge request through the payment processor 2. Emits payment and billing data plus webhook event name for downstream systems
##### Source Module: Payment & Billing Module
##### Target Module: Administration & Moderation, User Management & Authentication, External Billing Dashboards
##### URL: http://localhost/HireMe/public/api/payment-billing/charge
##### Function Name: charge()

Web Services Request Parameter (provide)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| user_id | integer | mandatory | Identifier of the paying account. | 24 |
| user_type | string | mandatory | Role of the paying account (`candidates`, `employers`, etc.). | employers |
| amount | number | mandatory | Amount to charge in decimal currency. | 199.99 |
| purpose | string | optional | Narrative reason for the charge. | Subscription renewal |
| payment_method | string | optional | Payment method label or gateway. | stripe |
| transaction_status | string | optional | Overrides resulting status (`success`, `failed`, etc.). | success |
| metadata | object/string | optional | Arbitrary JSON metadata (credits, invoice, etc.). | {"invoice":"INV-1001"} |
| billing_id | integer | optional | Existing billing record to associate with payment. | 17 |
| credits | integer | optional | Credits purchased or applied with the charge. | 10 |

Web Services Response Parameter (consume)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| module | string | mandatory | Service emitter identifier. | payment-billing |
| event | string | mandatory | Payment processor event fired for observers. | payment.processed |
| payment | object | mandatory | Stored payment entity data. | {"id":301,"amount":199.99,"user_type":"employers"} |
| billing | object | optional | Related billing record resolved or created. | {"id":17,"status":"active"} |

#### Supporting Service Code

```php
// app/Services/Modules/PaymentBillingService.php
public function handle(string $type, ?string $id, Request $request): array
{
    return match (strtolower($type)) {
        'payments' => $this->listPayments($request, $id),
        'payment' => $this->showPayment($request, $id),
        'billing' => $this->listBilling($request, $id),
        'charge' => $this->charge($request),
        'summary' => $this->summarise($request),
        default => throw new InvalidArgumentException(sprintf('Unknown payment/billing operation "%s".', $type)),
    };
}

/**
 * @return array<string, mixed>
 */
private function listPayments(Request $request, ?string $scope): array
{
    $query = Payment::query();

    $status = $this->query($request, 'status');
    if ($scope !== null && $scope !== '') {
        $scopeLower = strtolower($scope);
        if (in_array($scopeLower, ['pending', 'completed', 'failed', 'refunded'], true)) {
            $status = $status ?? $scopeLower;
        } elseif (str_starts_with($scopeLower, 'user-')) {
            $identifier = substr($scopeLower, 5);
            if (ctype_digit($identifier)) {
                $query->where('user_id', (int) $identifier);
            }
        }
    }

    if ($status !== null && $status !== '') {
        $query->where('transaction_status', $status);
    }

    if ($userType = $this->query($request, 'user_type')) {
        $query->where('user_type', $userType);
    }

    if ($userId = $this->query($request, 'user_id')) {
        if (ctype_digit($userId)) {
            $query->where('user_id', (int) $userId);
        }
    }

    $this->adminGuardian()->assertRead('payments', $this->adminContext($request, [
        'action' => 'payments.list',
        'status' => $status,
        'scope' => $scope,
    ]));

    $payments = $query->orderByDesc('created_at')->get();

    return $this->respond([
        'payments' => $payments->map(static fn (Payment $payment) => $payment->toArray())->all(),
        'count' => $payments->count(),
        'filters' => [
            'status' => $status,
        ],
    ]);
}

/**
 * @return array<string, mixed>
 */
private function charge(Request $request): array
{
    $payload = $request->all();

    $userId = $payload['user_id'] ?? null;
    if (!is_int($userId) && !ctype_digit((string) $userId)) {
        throw new InvalidArgumentException('A valid user identifier is required to process a payment.');
    }

    $userType = (string) ($payload['user_type'] ?? '');
    if ($userType === '') {
        throw new InvalidArgumentException('A user type is required to process a payment.');
    }

    if (!array_key_exists('amount', $payload)) {
        throw new InvalidArgumentException('A payment amount must be provided.');
    }

    $context = $this->adminContext($request, [
        'action' => 'payments.charge',
        'user_id' => (int) $userId,
        'user_type' => $userType,
        'amount' => $payload['amount'],
    ]);

    $this->adminGuardian()->assertWrite('payments', $context);

    $metadata = $this->normaliseMetadata($payload['metadata'] ?? null);
    $event = $this->processor->process([
        'user_id' => (int) $userId,
        'user_type' => $userType,
        'amount' => $payload['amount'],
        'purpose' => $payload['purpose'] ?? '',
        'payment_method' => $payload['payment_method'] ?? 'manual',
        'transaction_status' => $payload['transaction_status'] ?? ($payload['status'] ?? 'success'),
        'transaction_id' => $payload['transaction_id'] ?? null,
        'metadata' => $metadata,
        'credits' => $this->normaliseCredits($payload['credits'] ?? null, $metadata),
        'billing_id' => $this->normaliseBillingId($payload['billing_id'] ?? null),
    ]);

    $eventPayload = $event->payload();
    $payment = $eventPayload['payment'] ?? null;
    $paymentData = $payment instanceof Payment ? $payment->toArray() : (array) $payment;

    $billing = $this->resolveBillingRecord(
        $eventPayload,
        (int) $userId,
        (string) ($eventPayload['user_type'] ?? $userType)
    );

    $response = [
        'event' => $event->name(),
        'payment' => $paymentData,
        'billing' => $billing?->toArray(),
    ];

    $this->adminArbiter()->dispatch('payments.processed', [
        'event' => $event->name(),
        'user_id' => (int) $userId,
        'user_type' => $userType,
        'amount' => $payload['amount'],
        'payment' => $paymentData,
    ]);
    $this->adminArbiter()->flush();

    return $this->respond($response);
}
```

---

## 5. Administration & Moderation Module

### 5.1 Moderation Overview Dashboard Feed

Webservice Mechanism Service Exposure: Moderation Overview Dashboard Feed
##### Protocol: RESTFUL
##### Function Description: 1. Aggregates cross-module snapshots for users, jobs, finance, and suspensions 2. Surfaces dashboard-friendly counts for pending verifications, flagged postings, and failed payments
##### Source Module: Administration & Moderation Module
##### Target Module: User Management & Authentication, Job Posting & Application, Payment & Billing
##### URL: http://localhost/HireMe/public/api/admin-moderation/overview
##### Function Name: handle('overview')

Web Services Request Parameter (provide)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| (none) | — | — | This endpoint requires no query string or body parameters. | — |

Web Services Response Parameter (consume)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| module | string | mandatory | Service emitter identifier. | admin-moderation |
| overview | object | mandatory | Consolidated moderation dashboard payload. | {"users":[],"pending_verifications":3} |
| overview.users | array | optional | Normalised user snapshots forwarded from User Management. | [{"id":12,"role":"candidate"}] |
| overview.user_counts | object | optional | Aggregated counts per user type. | {"candidates":120,"employers":32} |
| overview.jobs | array\|object | optional | Job summary metrics sourced from Job Application. | {"total":85,"under_review":5} |
| overview.finance | array\|object | optional | Finance summary derived from Payment & Billing. | {"revenue":10999.50} |
| overview.pending_verifications | integer | mandatory | Count of candidates awaiting verification. | 7 |
| overview.flagged_jobs | integer | mandatory | Number of job postings flagged or under review. | 4 |
| overview.failed_payments | integer | mandatory | Number of failed payments detected. | 2 |
| overview.active_suspensions | integer | mandatory | Active suspension total sourced from the moderation store. | 6 |
| overview.errors | object | optional | Map of snapshot failures keyed by upstream module. | {"payment-billing":"Failed to fetch payment-billing snapshot."} |

### 5.2 Moderation Metrics Feed

Webservice Mechanism Service Exposure: Moderation Metrics Feed
##### Protocol: RESTFUL
##### Function Description: 1. Computes real-time counts for core moderation KPIs 2. Tracks suspended accounts, active jobs, and failed transactions
##### Source Module: Administration & Moderation Module
##### Target Module: User Management & Authentication, Job Posting & Application, Payment & Billing
##### URL: http://localhost/HireMe/public/api/admin-moderation/metrics
##### Function Name: handle('metrics')

Web Services Request Parameter (provide)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| (none) | — | — | No query parameters or headers are required. | — |

Web Services Response Parameter (consume)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| module | string | mandatory | Service emitter identifier. | admin-moderation |
| metrics | object | mandatory | Structured KPI payload for dashboards. | {"users":{"candidates":120}} |
| metrics.users.candidates | integer | mandatory | Total registered candidates. | 120 |
| metrics.users.employers | integer | mandatory | Total registered employers. | 34 |
| metrics.jobs.total | integer | mandatory | Total job postings tracked. | 215 |
| metrics.jobs.active | integer | mandatory | Count of active or open job postings. | 167 |
| metrics.payments.failed | integer | mandatory | Number of failed payments detected. | 9 |
| metrics.moderation.active_suspensions | integer | mandatory | Total active suspensions recorded. | 12 |

### 5.3 Moderation Audit Log Feed

Webservice Mechanism Service Exposure: Moderation Audit Log Feed
##### Protocol: RESTFUL
##### Function Description: 1. Streams the latest flagged entities and failed transactions for moderator review 2. Enriches listings with related user lookups to accelerate triage
##### Source Module: Administration & Moderation Module
##### Target Module: User Management & Authentication, Job Posting & Application, Payment & Billing
##### URL: http://localhost/HireMe/public/api/admin-moderation/audit
##### Function Name: handle('audit')

Web Services Request Parameter (provide)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| (none) | — | — | No headers or filters are needed to consume the audit stream. | — |

Web Services Response Parameter (consume)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| module | string | mandatory | Service emitter identifier. | admin-moderation |
| audit | object | mandatory | Container for the moderated entity feeds. | {"candidates":[],"jobs":[],"payments":[]} |
| audit.candidates | array | mandatory | Pending candidate verifications with embedded user data. | [{"candidate":{...},"user":{...}}] |
| audit.jobs | array | mandatory | Flagged or under-review jobs with employer context. | [{"job":{...},"employer":{...}}] |
| audit.payments | array | mandatory | Failed payment events with related account lookups. | [{"payment":{...},"user":{...}}] |
| audit.suspensions | array | optional | Full suspension ledger returned by the moderation store. | [{"role":"employer","user_id":35}] |

#### Supporting Service Code

```php
// app/Services/Modules/AdminModerationService.php
public function handle(string $type, ?string $id, Request $request): array
{
    $bus = $this->makeCommandBus($request);
    $type = strtolower($type);

    $context = $this->adminContext($request, [
        'operation' => $type,
        'target_id' => $id,
    ]);

    return match ($type) {
        'overview' => $this->respondReadData(
            fn () => $bus->dispatch(new OverviewCommand($this->registry, $this->suspensionStore()))->data(),
            $context + ['action' => 'overview']
        ),
        'metrics' => $this->respondReadData(
            fn () => $bus->dispatch(new MetricsCommand($this->suspensionStore()))->data(),
            $context + ['action' => 'metrics']
        ),
        'audit' => $this->respondReadData(
            fn () => $bus->dispatch(new AuditLogCommand($this->registry, $this->suspensionStore()))->data(),
            $context + ['action' => 'audit']
        ),
        'approve-job' => $this->respondFromResult(
            $bus->dispatch($this->makeApproveJobCommand($request, $id, $context)),
            $context
        ),
        'suspend-user' => $this->respondFromResult(
            $bus->dispatch($this->makeSuspendUserCommand($request, $context)),
            $context
        ),
        'reinstate-user' => $this->respondFromResult(
            $bus->dispatch($this->makeReinstateUserCommand($request, $context)),
            $context
        ),
        default => throw new InvalidArgumentException(sprintf('Unknown administration operation "%s".', $type)),
    };
}

private function makeApproveJobCommand(Request $request, ?string $id, array &$context): ApproveJobCommand
{
    $jobId = $this->requireIntId($id, 'A job identifier is required.');

    $context['action'] = 'approve-job';
    $context['job_id'] = $jobId;
    $this->adminGuardian()->assertWrite('moderation', $context);
    $this->adminGuardian()->audit('moderation.approve-job', $context);

    return new ApproveJobCommand($jobId, $this->moderatorId($request));
}

private function makeSuspendUserCommand(Request $request, array &$context): SuspendUserCommand
{
    $role = $this->requireUserRole($request);
    $userId = $this->requireUserId($request);
    $until = $this->parseSuspensionUntil($request);
    $reason = $this->suspensionReason($request);

    $context['action'] = 'suspend-user';
    $context['target_role'] = $role;
    $context['target_user_id'] = $userId;
    $context['suspend_until'] = $until?->format(DateTimeInterface::ATOM);
    if ($reason !== null) {
        $context['reason'] = $reason;
    }

    $this->adminGuardian()->assertWrite('moderation', $context);
    $this->adminGuardian()->audit('moderation.suspend-user', $context);

    return new SuspendUserCommand(
        $role,
        $userId,
        $this->suspensionStore(),
        $this->userLookup(),
        $until,
        $reason,
        $this->moderatorId($request)
    );
}
```

