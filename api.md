# HireMe Modular API Interface Agreement (IFA)

## 1. Overview of the Service Mesh

The HireMe platform exposes every business module through a shared RESTful gateway. JSON is used for both
request and response payloads. Each web service is accessible under the predictable pattern:

```
GET|POST|PUT|PATCH|DELETE https://{host}/public/api/{function}/{type}/{id?}
```

* `{function}` identifies the module (for example `user-management`, `resume-profile`).
* `{type}` selects the specific operation within that module (`users`, `profile`, `payments`, etc.).
* `{id}` is optional and represents the resource identifier when required.

The `App\Controllers\Api\ModuleGatewayController` resolves these segments, looks up the appropriate module
in the `ModuleRegistry`, and dispatches the request to a dedicated module service. Each service uses Laravel's
Eloquent models under the hood and always returns a JSON document with a `module` attribute identifying the
producer module.

### Web Service Consumption Between Modules

All module classes extend `AbstractModuleService`, which provides a `forward()` helper. That helper simulates an
internal HTTP call by building a `Request` object and dispatching it through the gateway again. This ensures that
modules *consume* each other's REST endpoints instead of reaching directly into each other's databases. For
example, the Payment & Billing module uses `forward()` to enrich payments with user information:

```php
$payments = $this->forward('user-management', 'user', (string) $data['user_id'], [
    'role' => $role,
]);
```

Every module below follows the same pattern: it exposes web services through the gateway and consumes services
from peer modules where cross-module data is required.

---

## 2. User Management & Authentication Module

* **Base URL:** `https://{host}/public/api/user-management/{type}/{id?}`
* **Source Module:** User Management
* **Typical Consumers:** Resume/Profile, Job Application, Payment/Billing, Admin Moderation

### 2.1 Operation: `users`

| Attribute          | Value                                                       |
|--------------------|-------------------------------------------------------------|
| **Function Name**  | `listUsers`                                                 |
| **HTTP Method**    | `GET`                                                       |
| **URL**            | `/public/api/user-management/users/{role?}`                 |
| **Purpose**        | Lists users by role or returns an all-role snapshot.        |

**Request Parameters**

| Field Name | Type   | Mandatory | Description                                                   | Format                          |
|------------|--------|-----------|---------------------------------------------------------------|---------------------------------|
| `role`     | String | Optional  | Query string filter. `all` or one of `candidates`, `employers`, `recruiters`, `admins`. | Lowercase slug                  |
| `id`       | Path   | Optional  | When `{role}` path segment is used, it acts as the role filter. | `candidates` – `admins`       |

**Response Payload**

| Field Name | Type   | Description                                            |
|------------|--------|--------------------------------------------------------|
| `module`   | String | Always `user-management`.                              |
| `role`     | String | Resolved role key (`all`, `candidates`, `employers`, ...). |
| `users`    | Object | Map of role => user arrays when `role=all`; otherwise a flat array of user records. |
| `count(s)` | Object | Per-role counts and total when `role=all`.             |

*Example:* `GET /public/api/user-management/users/all`

### 2.2 Operation: `user`

| Attribute          | Value                                                       |
|--------------------|-------------------------------------------------------------|
| **Function Name**  | `showUser`                                                  |
| **HTTP Method**    | `GET`                                                       |
| **URL**            | `/public/api/user-management/user/{id}`                     |
| **Purpose**        | Retrieves a single user and can aggregate related data from other modules.

**Request Parameters**

| Field Name | Type   | Mandatory | Description                                                                       | Format          |
|------------|--------|-----------|-----------------------------------------------------------------------------------|-----------------|
| `id`       | Path   | Yes       | Numeric identifier (candidate_id, employer_id, recruiter_id, admin_id).          | Digits only     |
| `role`     | String | Optional  | Forces lookup within a specific role.                                            | Same as above   |
| `include`  | String | Optional  | Comma-separated related datasets to pull (`profile`, `resume`, `applications`, `jobs`, `payments`, `billing`). | Comma-separated |

**Response Payload**

| Field Name   | Type   | Description                                                                                   |
|--------------|--------|-----------------------------------------------------------------------------------------------|
| `module`     | String | `user-management`.                                                                            |
| `role`       | String | Resolved role.                                                                                |
| `user`       | Object | The user data. Password hashes are automatically excluded.                                   |
| `includes`   | Array  | Echo of the requested include list (only when provided).                                     |
| `related`    | Object | Enriched datasets fetched from other modules (keys mirror the `include` list).               |

**Consumption Example:** The Resume/Profile module calls this endpoint to populate candidate profile responses:

```php
$userDetails = $this->forward('user-management', 'user', (string) $candidateId, [
    'role' => 'candidates',
]);
```

The User Management service itself can now also request related data by forwarding to other modules whenever `include` is set:

```php
$profile = $this->forward('resume-profile', 'profile', (string) $userId);
$applications = $this->forward('job-application', 'applications', null, [
    'candidate_id' => (string) $userId,
]);
```

### 2.3 Operation: `authenticate`

| Attribute          | Value                                                    |
|--------------------|----------------------------------------------------------|
| **Function Name**  | `authenticateUser`                                       |
| **HTTP Method**    | `POST` (also accepts `GET` with query parameters)        |
| **URL**            | `/public/api/user-management/authenticate`               |
| **Purpose**        | Validates email/password credentials and identifies the role.

**Request Parameters**

| Field Name | Type   | Mandatory | Description                                      | Format               |
|------------|--------|-----------|--------------------------------------------------|----------------------|
| `email`    | String | Yes       | User email address.                              | Valid email          |
| `password` | String | Yes       | Plaintext password to verify.                    | Any string           |
| `role`     | String | Optional  | Restrict authentication to a specific role.      | Same as `role` above |

**Response Payload**

| Field Name      | Type    | Description                                              |
|-----------------|---------|----------------------------------------------------------|
| `module`        | String  | `user-management`.                                        |
| `authenticated` | Boolean | `true` when credentials match.                            |
| `role`          | String  | Role of the authenticated account (when successful).      |
| `user`          | Object  | Sanitised user record (without password hashes).          |
| `message`       | String  | Error message when authentication fails.                  |

---

## 3. Resume & Profile Management Module

* **Base URL:** `https://{host}/public/api/resume-profile/{type}/{id?}`
* **Source Module:** Resume/Profile
* **Typical Consumers:** Job Application, Admin Moderation, User Management

| Type         | HTTP | URL Pattern                                           | Description                                                    |
|--------------|------|--------------------------------------------------------|----------------------------------------------------------------|
| `resumes`    | GET  | `/public/api/resume-profile/resumes/{candidateId?}`    | Lists resumes, optionally filtered by candidate.               |
| `resume`     | GET  | `/public/api/resume-profile/resume/{id}`               | Returns a single resume with candidate context.                |
| `profiles`   | GET  | `/public/api/resume-profile/profiles`                  | Lists candidate profiles with optional filters.                |
| `profile`    | GET  | `/public/api/resume-profile/profile/{candidateId}`     | Full candidate dossier (profile + resume + linked user record).|

### Request Fields (IFA)

| Field Name        | Type   | Mandatory | Description                                            | Format                |
|-------------------|--------|-----------|--------------------------------------------------------|-----------------------|
| `candidate_id`    | Query  | Optional  | Filters `resumes` or `profiles` endpoints.            | Digits                |
| `verified_status` | Query  | Optional  | `profiles` filter for KYC/verification status.        | `pending`, `approved`, `rejected` |
| `city`            | Query  | Optional  | `profiles` city filter.                               | Free text             |
| `id`              | Path   | Required for `resume`/`profile` endpoints.              | Digits                |

### Response Fields

| Field Name | Type   | Description                                                                  |
|------------|--------|------------------------------------------------------------------------------|
| `module`   | String | `resume-profile`.                                                            |
| `resumes`  | Array  | Array of resume objects (for `resumes`).                                     |
| `profiles` | Array  | Array of candidate objects (for `profiles`).                                 |
| `profile`  | Object | Candidate data (for `profile`).                                              |
| `resume`   | Object | Latest resume for candidate (for `profile`/`resume`).                        |
| `user`     | Object | User data fetched from User Management (`profile` endpoint).                 |

**Consumption Example:**

```php
$profile = $this->forward('resume-profile', 'profile', $candidateId);
```

Both the Job Application and User Management modules rely on this service to enrich candidate-centric responses.

---

## 4. Job Posting & Application Module

* **Base URL:** `https://{host}/public/api/job-application/{type}/{id?}`
* **Source Module:** Job Posting & Applications
* **Typical Consumers:** Admin Moderation, User Management, Resume/Profile

| Type            | HTTP | URL Pattern                                             | Description                                                 |
|-----------------|------|----------------------------------------------------------|-------------------------------------------------------------|
| `jobs`          | GET  | `/public/api/job-application/jobs/{scope?}`             | Lists job postings with optional employer/recruiter filters.|
| `job`           | GET  | `/public/api/job-application/job/{id}`                 | Detailed job posting with applications.                    |
| `applications`  | GET  | `/public/api/job-application/applications`             | Lists applications with optional job/candidate filters.    |
| `application`   | GET  | `/public/api/job-application/application/{id}`         | Single application plus candidate/job context.             |
| `summary`       | GET  | `/public/api/job-application/summary/all`              | Aggregated statistics for admins/dashboards.               |

### Request Fields (IFA)

| Field Name     | Type   | Mandatory | Description                                                     | Format                          |
|----------------|--------|-----------|-----------------------------------------------------------------|---------------------------------|
| `status`       | Query  | Optional  | Filters jobs by lifecycle (`active`, `closed`, etc.).           | Enum                            |
| `employer_id`  | Query  | Optional  | Restricts jobs to a specific employer.                          | Digits                          |
| `recruiter_id` | Query  | Optional  | Restricts jobs to a recruiter.                                  | Digits                          |
| `job_id`       | Query  | Optional  | Filters applications by job.                                    | Digits                          |
| `candidate_id` | Query  | Optional  | Filters applications by candidate.                              | Digits                          |
| `id`           | Path   | Required for `job` and `application` endpoints.                         | Digits                          |

### Response Fields

| Field Name        | Type   | Description                                                         |
|-------------------|--------|---------------------------------------------------------------------|
| `module`          | String | `job-application`.                                                  |
| `jobs`            | Array  | Jobs with embedded employer/recruiter data.                         |
| `applications`    | Array  | Application records with candidate/job context.                      |
| `summary`         | Object | Aggregate metrics (counts, top candidates).                          |
| `profile`/`resume`| Object | Included in application detail responses via Resume/Profile module.  |

**Consumption Examples:**

* Admin Moderation service pulls system-wide dashboards:

  ```php
  $jobSnapshot = $this->forward('job-application', 'summary', 'all');
  ```

* User Management service can enrich an employer record when `include=jobs`:

  ```php
  $jobs = $this->forward('job-application', 'jobs', null, [
      'employer_id' => (string) $userId,
  ]);
  ```

---

## 5. Payment & Billing Module

* **Base URL:** `https://{host}/public/api/payment-billing/{type}/{id?}`
* **Source Module:** Payment & Billing
* **Typical Consumers:** Admin Moderation, User Management

| Type       | HTTP | URL Pattern                                           | Description                                              |
|------------|------|--------------------------------------------------------|----------------------------------------------------------|
| `payments` | GET  | `/public/api/payment-billing/payments/{scope?}`        | Lists payments, supporting status and user filters.       |
| `payment`  | GET  | `/public/api/payment-billing/payment/{id}`             | Single payment enriched with user information.            |
| `billing`  | GET  | `/public/api/payment-billing/billing/{scope?}`         | Billing records for invoicing/credits.                    |
| `summary`  | GET  | `/public/api/payment-billing/summary/all`              | Aggregated finance dashboard.                             |

### Request Fields (IFA)

| Field Name   | Type   | Mandatory | Description                                      | Format                  |
|--------------|--------|-----------|--------------------------------------------------|-------------------------|
| `status`     | Query  | Optional  | Filters by transaction status.                   | Enum (`pending`, `paid`, ... ) |
| `user_type`  | Query  | Optional  | Filters by user role (`candidates`, `employers`, ...). | Lowercase slug          |
| `user_id`    | Query  | Optional  | Filters payments/billing to a specific account.  | Digits                  |
| `id`         | Path   | Required for `payment` detail.                           | Digits                  |

### Response Fields

| Field Name    | Type   | Description                                                               |
|---------------|--------|---------------------------------------------------------------------------|
| `module`      | String | `payment-billing`.                                                        |
| `payments`    | Array  | Payment records (for list endpoint).                                      |
| `billing`     | Array  | Billing records.                                                          |
| `summary`     | Object | Aggregated totals, latest payments, top payers.                           |
| `payment`     | Object | Detail view including `user` data fetched from User Management.           |

**Consumption Examples:**

```php
$user = $this->forward('user-management', 'user', (string) $payment->user_id, [
    'role' => $role,
]);
```

The User Management service can request financial context via `include=payments,billing` when returning an employer or candidate profile.

---

## 6. Administration & Moderation Module

* **Base URL:** `https://{host}/public/api/admin-moderation/{type}`
* **Source Module:** Administration & Moderation
* **Typical Consumers:** Admin dashboards, reporting tools, **and every feature module via the shared `ModuleGatewayController`**.

The Administration module now provides two roles:

1. **Guardian APIs** – synchronous policy, moderation, and risk checks that other modules must call during their own read/write operations.
2. **Arbiter Events** – asynchronous webhooks that inform modules about review outcomes so they can enforce decisions after the fact.

### Guardian Endpoints

| Type               | HTTP | URL Pattern                                                   | Description |
|--------------------|------|----------------------------------------------------------------|-------------|
| `overview`         | GET  | `/public/api/admin-moderation/overview`                       | Top-level dashboard combining snapshots from all modules. |
| `metrics`          | GET  | `/public/api/admin-moderation/metrics`                        | Key performance indicators across the system. |
| `audit`            | GET  | `/public/api/admin-moderation/audit`                          | Centralised audit log spanning users, jobs, billing, and moderation. |
| `moderation-scan`  | POST | `/public/api/admin-moderation/moderation/scan`                | Runs policy checks for jobs, resumes, profiles, or messages before they are persisted. |
| `moderation-status`| GET  | `/public/api/admin-moderation/moderation/status`              | Returns latest moderation verdict for a given resource. |
| `enforcement-user` | GET  | `/public/api/admin-moderation/enforcement/user/{id}`          | Inline ban/suspension check for account-level actions. |
| `enforcement-org`  | GET  | `/public/api/admin-moderation/enforcement/org/{id}`           | Organisation enforcement status for billing or job posting. |
| `audit-log-write`  | POST | `/public/api/admin-moderation/audit/logs`                     | Appends structured audit entries from other services. |
| `flags`            | GET  | `/public/api/admin-moderation/flags{/{key}}`                  | Retrieves the global feature flag map or a single key. |
| `flags-evaluate`   | POST | `/public/api/admin-moderation/flags/evaluate`                 | Resolves conditional flag overrides for a contextual request. |
| `risk-score`       | POST | `/public/api/admin-moderation/risk/score`                     | Executes fraud heuristics and returns `{score, advise}` guidance. |

All guardian responses include a `module` field (`admin-moderation`) plus endpoint-specific payloads such as `allowed`, `actions`, `score`, and `advise` so that callers can enforce policy inline.

### Arbiter Events

After manual or automated reviews, the module dispatches webhook events:

| Event Key                  | Trigger                                 | Typical Consumers |
|----------------------------|-----------------------------------------|-------------------|
| `admin.user.banned`        | User suspended or banned                | User Management, Authentication |
| `admin.job.taken_down`     | Job removed following moderation        | Job Application, Payment/Billing |
| `admin.profile.masked`     | Resume/profile redacted                 | Resume/Profile |
| `admin.refund.approved`    | Refund approved during audit            | Payment/Billing |
| `admin.flags.threshold.hit`| Flag rules breached                     | All modules monitoring feature thresholds |

Webhooks include correlation identifiers that map back to guardian responses (e.g., moderation scan IDs) so consumers can reconcile asynchronous outcomes.

### Request & Response Examples

```php
// Job publishing workflow (synchronous guardian call)
$verdict = $this->forward('admin-moderation', 'moderation-scan', 'jobs', [
    'job_id' => $jobId,
    'payload' => $jobDraft,
]);

if ($verdict['allowed'] === false) {
    return $this->abort(409, $verdict['actions'] ?? []);
}

// Later, respond to the arbiter event asynchronously
$this->on('admin.job.taken_down', function (array $event) {
    $this->jobs->delist($event['job_id']);
});
```

User Management, Job Application, Payment/Billing, and Resume services now forward to these guardian endpoints during their read/write flows and subscribe to arbiter webhooks to react to policy decisions.

---

## 7. Testing the Endpoints

1. Ensure dependencies are installed: `composer install`
2. Start the PHP development server: `php -S 0.0.0.0:8000 -t public`
3. Call any endpoint with your preferred HTTP client, e.g.

```
curl "http://localhost:8000/public/api/user-management/user/12?include=profile,applications"
```

All responses are JSON-formatted and include a `module` field so clients can confirm which service produced the payload.

---

## 8. Change Log

* Added an `include` contract to the User Management service so that it can consume other modules on demand.
* Documented every module's public API with request/response IFA details and cross-module consumption examples.

