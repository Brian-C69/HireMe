# HireMe Modular API Integration Field Agreement (IFA)

## 1. Gateway & Transport Conventions

* **Protocol:** HTTPS REST with JSON payloads (UTF-8). Authentication is handled per-module (most endpoints expect admin-level guardianship headers) and all responses include a top-level `module` key naming the producer service.【F:app/Services/Modules/AbstractModuleService.php†L52-L59】
* **Canonical Route Pattern:**
  ```
  GET|POST|PUT|PATCH|DELETE https://{host}/public/api/{module}/{type}/{id?}
  ```
  The `ModuleGatewayController` resolves `{module}`, `{type}`, and optional `{id}`, routes the request to the registered module service, and converts exceptions into HTTP error responses.【F:app/Controllers/Api/ModuleGatewayController.php†L29-L49】
* **Module Registry:** `ModuleRegistry::boot()` wires the five functional services below and exposes the `call()` helper so modules can invoke each other through the same REST contract (see the `forward()` helper in `AbstractModuleService`).【F:app/Services/Modules/ModuleRegistry.php†L74-L135】【F:app/Services/Modules/AbstractModuleService.php†L18-L49】

Unless stated otherwise, optional query parameters must be provided as URL query strings for GET requests or JSON body fields for non-GET requests. Examples illustrate inter-module consumption through the registry.

---

## 2. User Management & Authentication Module

* **Base URL:** `https://{host}/public/api/user-management/{type}/{id?}`
* **Service Class:** `App\Services\Modules\UserManagementService`
* **Consumers:** Resume/Profile, Job Application, Payment/Billing, Administration (via guardian checks)

### 2.1 Service Exposure Matrix

| Webservice Mechanism | HTTP | URL Pattern | Function Name | Primary Purpose |
|----------------------|------|-------------|---------------|-----------------|
| User Directory | GET | `/public/api/user-management/users/{role?}` | `listUsers()` | List users across roles or within a specific role with counts.【F:app/Services/Modules/UserManagementService.php†L48-L107】 |
| User Snapshot | GET | `/public/api/user-management/user/{id}` | `showUser()` | Retrieve one user; optional `include` flag enriches the response with cross-module data.【F:app/Services/Modules/UserManagementService.php†L112-L175】【F:app/Services/Modules/UserManagementService.php†L182-L239】 |
| Authentication | POST/GET | `/public/api/user-management/authenticate` | `authenticateUser()` | Validate credentials and emit a sanitized user payload plus role metadata.【F:app/Services/Modules/UserManagementService.php†L240-L307】 |

#### 2.1.1 `listUsers`
* **Function Description:**
  1. When `{role}` is omitted or equals `all`, returns a role-indexed map of users plus per-role and total counts.
  2. When a supported role slug is provided (e.g., `candidates`, `employers`), returns only that cohort with a `count` field.
* **Source Module:** User Management
* **Target Modules:** Consumed by Resume/Profile (profile hydration), Job Application (employer dashboards), Payment/Billing (financial lookups), Admin Moderation (guardian audits).

**Request Parameters**

| Field | Type | Mandatory | Description | Example |
|-------|------|-----------|-------------|---------|
| `role` | Query/String or Path | Optional | Role filter: `all`, `candidates`, `employers`, `recruiters`, `admins` (aliases such as `talent` map to canonical roles). | `candidates` |

**Response Payload**

| Field | Type | Mandatory/Optional | Description | Format |
|-------|------|--------------------|-------------|--------|
| `module` | String | Mandatory | Module emitter identifier. | `user-management` |
| `role` | String | Mandatory | Resolved role or `all` when aggregating. | `candidates` |
| `users` | Array/Object | Mandatory | Array of user objects (single role) or role-keyed map (`role=all`). | `[ { "id": 5, "email": "talent@example.com" } ]` |
| `count` | Integer | Optional | Single-role total count. Omitted when `role=all`. | `42` |
| `counts` | Object | Optional | Per-role and total counts when `role=all`. | `{ "candidates": 30, "employers": 12, "total": 42 }` |

#### 2.1.2 `showUser`
* **Function Description:**
  1. Accepts a numeric `{id}` and optional `role` hint to constrain lookup.
  2. Supports comma-separated `include` values (`profile`, `resume`, `applications`, `jobs`, `payments`, `billing`) that trigger cross-module lookups via the registry.
* **Source Module:** User Management
* **Target Modules:** Resume/Profile (candidate dossier), Job Application (job/application summaries), Payment/Billing (financial history).

**Request Parameters**

| Field | Type | Mandatory | Description | Example |
|-------|------|-----------|-------------|---------|
| `id` | Path | Yes | Numeric user identifier. | `42` |
| `role` | Query/String | Optional | Restrict search to a specific role slug/alias. | `employers` |
| `include` | Query/String | Optional | Comma list of related datasets (`profile`, `resume`, `applications`, `jobs`, `payments`, `billing`). | `profile,resume` |

**Response Payload**

| Field | Type | Mandatory/Optional | Description | Format |
|-------|------|--------------------|-------------|--------|
| `module` | String | Mandatory | Module emitter identifier. | `user-management` |
| `role` | String | Mandatory | Role slug associated with the user. | `employers` |
| `user` | Object | Mandatory | Sanitized user resource without credentials. | `{ "id": 42, "email": "ceo@example.com" }` |
| `includes` | Array | Optional | Echo of processed include keys. | `["profile","resume"]` |
| `related` | Object | Optional | Keyed include payloads (profile, resume, applications, etc.). | `{ "profile": { ... }, "applications_count": 3 }` |
| `message` | String | Optional | Error or validation message on failure. | `"User not found"` |

#### 2.1.3 `authenticate`
* **Function Description:**
  1. Hash-verifies supplied credentials against all roles (or a hinted role).
  2. Audits attempts via the admin guardian/arbiter and returns role context on success.
* **Source Module:** User Management
* **Target Modules:** Authentication clients, Admin Moderation (event feed `admin.authenticated`).

**Request Parameters**

| Field | Type | Mandatory | Description | Example |
|-------|------|-----------|-------------|---------|
| `email` | Query/String or JSON | Yes | Login email. | `pat@example.com` |
| `password` | Query/String or JSON | Yes | Plaintext password to verify (hash comparison occurs internally). | `My$ecret` |
| `role` | Query/String or JSON | Optional | Restrict authentication to a role alias (same options as above). | `recruiter` |

**Response Payload**

| Field | Type | Mandatory/Optional | Description | Format |
|-------|------|--------------------|-------------|--------|
| `module` | String | Mandatory | Module emitter identifier. | `user-management` |
| `authenticated` | Boolean | Mandatory | Indicates whether authentication succeeded. | `true` / `false` |
| `role` | String | Optional | Role slug for the authenticated user. | `recruiter` |
| `user` | Object | Optional | Sanitized user resource when authentication succeeds. | `{ "id": 9, "email": "recruiter@example.com" }` |
| `message` | String | Optional | Failure reason returned on authentication errors. | `"Invalid credentials provided."` |

---

## 3. Resume & Profile Management Module

* **Base URL:** `https://{host}/public/api/resume-profile/{type}/{id?}`
* **Service Class:** `App\Services\Modules\ResumeProfileService`
* **Consumers:** User Management, Job Application, Admin Moderation

### 3.1 Service Exposure Matrix

| Webservice Mechanism | HTTP | URL Pattern | Function Name | Primary Purpose |
|----------------------|------|-------------|---------------|-----------------|
| Resume Catalogue | GET | `/public/api/resume-profile/resumes/{candidateId?}` | `listResumes()` | List resumes, optionally filtered by candidate, with embedded candidate info.【F:app/Services/Modules/ResumeProfileService.php†L44-L99】 |
| Resume Detail | GET | `/public/api/resume-profile/resume/{id}` | `showResume()` | Fetch a resume, candidate data, and rendered output format metadata.【F:app/Services/Modules/ResumeProfileService.php†L104-L132】 |
| Profile Directory | GET | `/public/api/resume-profile/profiles` | `listProfiles()` | List candidate profiles with optional verification/city filters.【F:app/Services/Modules/ResumeProfileService.php†L134-L159】 |
| Profile Dossier | GET | `/public/api/resume-profile/profile/{candidateId}` | `showProfile()` | Aggregate candidate profile, latest resume (rendered), and user account info.【F:app/Services/Modules/ResumeProfileService.php†L164-L198】 |

**Common Request Parameters**

| Field | Applies To | Type | Mandatory | Description | Example |
|-------|------------|------|-----------|-------------|---------|
| `candidate_id` | `resumes`, `applications` include | Query/String | Optional | Filter resumes by candidate when using the list endpoint. | `15` |
| `verified_status` | `profiles` | Query/String | Optional | Filter by verification status (`pending`, `approved`, `rejected`). | `approved` |
| `city` | `profiles` | Query/String | Optional | Filter by city name. | `New York` |
| `id` | `resume`, `profile` | Path | Yes | Resume or candidate identifier. | `77` |

**Representative Response Fields**

| Field | Type | Mandatory/Optional | Description | Format |
|-------|------|--------------------|-------------|--------|
| `module` | String | Mandatory | Module emitter identifier. | `resume-profile` |
| `resumes` | Array | Optional | Resume list payload including candidate data. | `[ { "id": 77, "candidate": { ... } } ]` |
| `profiles` | Array | Optional | Profile directory payload. | `[ { "candidate_id": 15, "city": "New York" } ]` |
| `profile` | Object | Optional | Candidate dossier payload. | `{ "candidate_id": 15, "summary": "Full-stack developer" }` |
| `resume` | Object/Null | Optional | Latest resume with rendered artifacts. | `{ "id": 77, "rendered_format": "pdf" }` |
| `user` | Object/Null | Optional | User snapshot fetched from User Management. | `{ "id": 15, "email": "talent@example.com" }` |
| `count` | Integer | Optional | Record count accompanying list endpoints. | `12` |

---

## 4. Job Posting & Application Module

* **Base URL:** `https://{host}/public/api/job-application/{type}/{id?}`
* **Service Class:** `App\Services\Modules\JobApplicationService`
* **Consumers:** User Management (job includes), Resume/Profile (application enrichment), Admin Moderation (dashboards & approvals)

### 4.1 Service Exposure Matrix

| Webservice Mechanism | HTTP | URL Pattern | Function Name | Primary Purpose |
|----------------------|------|-------------|---------------|-----------------|
| Job Listings | GET | `/public/api/job-application/jobs/{scope?}` | `listJobs()` | Filterable listing by status, employer, recruiter, or custom scope hint.【F:app/Services/Modules/JobApplicationService.php†L29-L71】 |
| Job Detail | GET | `/public/api/job-application/job/{id}` | `showJob()` | Retrieve a single job posting with full facade-provided context.【F:app/Services/Modules/JobApplicationService.php†L73-L86】 |
| Application Listings | GET | `/public/api/job-application/applications` | `listApplications()` | Filterable by `job_id` and/or `candidate_id` and includes guardian auditing.【F:app/Services/Modules/JobApplicationService.php†L88-L118】 |
| Application Detail | GET | `/public/api/job-application/application/{id}` | `showApplication()` | Returns an application plus candidate dossier via Resume/Profile service.【F:app/Services/Modules/JobApplicationService.php†L120-L138】 |
| Jobs Summary | GET | `/public/api/job-application/summary/all` | `summarise()` | Aggregated reporting (counts, highlights) for dashboards with candidate enrichment.【F:app/Services/Modules/JobApplicationService.php†L140-L154】 |

**Request Parameters**

| Field | Type | Mandatory | Description | Example |
|-------|------|-----------|-------------|---------|
| `status` | Query/String | Optional | Job lifecycle filter (`active`, `closed`, etc.). | `active` |
| `scope` | Path/String | Optional | Free-form segment for custom views (e.g., `employer-portal`). | `employer-portal` |
| `employer_id` | Query/String | Optional | Restrict jobs to employer ID (numeric). | `12` |
| `recruiter_id` | Query/String | Optional | Restrict jobs to recruiter ID (numeric). | `9` |
| `job_id` | Query/String | Optional | Filter applications by job ID. | `101` |
| `candidate_id` | Query/String | Optional | Filter applications by candidate ID. | `55` |
| `id` | Path | Yes | Job or application identifier for detail endpoints. | `87` |

**Response Highlights**

| Field | Type | Mandatory/Optional | Description | Format |
|-------|------|--------------------|-------------|--------|
| `module` | String | Mandatory | Module emitter identifier. | `job-application` |
| `jobs` | Array | Optional | Job listing payload with related company data. | `[ { "id": 201, "title": "Backend Engineer" } ]` |
| `applications` | Array | Optional | Application records with embedded candidate/job info. | `[ { "id": 501, "candidate_id": 55 } ]` |
| `count` | Integer | Optional | Record count attached to listings. | `24` |
| `summary` | Object | Optional | Aggregated metrics for dashboards. | `{ "active_jobs": 12, "applications_today": 18 }` |

---

## 5. Payment & Billing Module

* **Base URL:** `https://{host}/public/api/payment-billing/{type}/{id?}`
* **Service Class:** `App\Services\Modules\PaymentBillingService`
* **Consumers:** User Management (financial includes), Admin Moderation (guardian write checks), external billing dashboards

### 5.1 Service Exposure Matrix

| Webservice Mechanism | HTTP | URL Pattern | Function Name | Primary Purpose |
|----------------------|------|-------------|---------------|-----------------|
| Payment Ledger | GET | `/public/api/payment-billing/payments/{scope?}` | `listPayments()` | Filterable list by status, user role, or embedded `user-{id}` scope segment.【F:app/Services/Modules/PaymentBillingService.php†L31-L92】 |
| Payment Detail | GET | `/public/api/payment-billing/payment/{id}` | `showPayment()` | Retrieve a payment and enrich with user snapshot via User Management.【F:app/Services/Modules/PaymentBillingService.php†L94-L123】 |
| Billing Ledger | GET | `/public/api/payment-billing/billing/{scope?}` | `listBilling()` | List billing records by status, user type, or scope hints.【F:app/Services/Modules/PaymentBillingService.php†L124-L170】 |
| Process Charge | POST | `/public/api/payment-billing/charge` | `charge()` | Validate payload, process payment through `PaymentProcessor`, dispatch webhook events.【F:app/Services/Modules/PaymentBillingService.php†L31-L330】 |
| Finance Summary | GET | `/public/api/payment-billing/summary/all` | `summarise()` | Aggregate totals, status breakdown, latest payments, billing count, and top payers with user enrichment.【F:app/Services/Modules/PaymentBillingService.php†L172-L243】 |

**Key Request Parameters**

| Field | Applies To | Type | Mandatory | Description | Example |
|-------|------------|------|-----------|-------------|---------|
| `status` | Payments/Billing | Query/String | Optional | Filter by transaction status (e.g., `pending`, `completed`). | `completed` |
| `user_type` | Payments/Billing/Charge | Query/String | Optional (GET), Required (POST) | Role of the paying account (`candidates`, `employers`, `recruiters`, `admins`). | `employers` |
| `user_id` | Payments/Billing/Charge | Query/String or JSON | Optional (GET), Required (POST) | Numeric account identifier. | `24` |
| `amount` | Charge | JSON/Number | Yes | Amount to process. | `199.99` |
| `payment_method` | Charge | JSON/String | Optional | Payment method label (`manual`, gateway name). | `stripe` |
| `transaction_status` | Charge | JSON/String | Optional | Overrides resulting status (`success`, `failed`, etc.). | `success` |
| `metadata` | Charge | JSON/Object or String | Optional | Additional context (credits, invoice references). | `{ "invoice": "INV-1001" }` |

**Response Highlights**

| Field | Type | Mandatory/Optional | Description | Format |
|-------|------|--------------------|-------------|--------|
| `module` | String | Mandatory | Module emitter identifier. | `payment-billing` |
| `payments` / `billing` | Array | Optional | Ledger entries returned for list endpoints. | `[ { "id": 301, "status": "completed" } ]` |
| `count` | Integer | Optional | Record count accompanying list responses. | `8` |
| `payment` | Object | Optional | Detailed payment resource (detail/charge responses). | `{ "id": 301, "amount": 199.99 }` |
| `summary` | Object | Optional | Aggregated finance metrics. | `{ "total_revenue": 1299.50, "top_payers": [ ... ] }` |
| `event` | String | Optional | Event label raised by `charge()`. | `payment.processed` |

---

## 6. Administration & Moderation Module

* **Base URL:** `https://{host}/public/api/admin-moderation/{type}`
* **Service Class:** `App\Services\Modules\AdminModerationService`
* **Consumers:** All modules (guardian assertions and arbiter webhooks), admin dashboards

### 6.1 Service Exposure Matrix

| Webservice Mechanism | HTTP | URL Pattern | Function Name | Primary Purpose |
|----------------------|------|-------------|---------------|-----------------|
| Moderation Overview | GET | `/public/api/admin-moderation/overview` | `handle('overview')` | Consolidate cross-module metrics for dashboards via command bus.【F:app/Services/Modules/AdminModerationService.php†L38-L59】 |
| Moderation Metrics | GET | `/public/api/admin-moderation/metrics` | `handle('metrics')` | KPI snapshots (counts, trends) via `MetricsCommand`.【F:app/Services/Modules/AdminModerationService.php†L38-L59】 |
| Audit Trail | GET | `/public/api/admin-moderation/audit` | `handle('audit')` | Centralised audit log aggregator. 【F:app/Services/Modules/AdminModerationService.php†L38-L59】|
| Approve Job | POST | `/public/api/admin-moderation/approve-job/{jobId}` | `makeApproveJobCommand()` | Approve a job posting with moderator attribution. 【F:app/Services/Modules/AdminModerationService.php†L61-L125】|
| Suspend User | POST | `/public/api/admin-moderation/suspend-user` | `makeSuspendUserCommand()` | Suspend a user with optional expiry and reason metadata. 【F:app/Services/Modules/AdminModerationService.php†L66-L147】|
| Reinstate User | POST | `/public/api/admin-moderation/reinstate-user` | `makeReinstateUserCommand()` | Lift a suspension for a specific user role/id. 【F:app/Services/Modules/AdminModerationService.php†L69-L172】|

**Common Request Headers**

| Header | Purpose |
|--------|---------|
| `X-Admin-Id` / `X-Moderator-Id` | Used to identify the acting moderator for auditing and guardian checks.【F:app/Services/Modules/AdminModerationService.php†L193-L215】 |
| `X-Admin-Role` | Supplies the actor role for guardian context (fallback to payload fields).【F:app/Services/Modules/AbstractModuleService.php†L200-L233】 |

**Key Request Parameters**

| Field | Applies To | Type | Mandatory | Description | Example |
|-------|------------|------|-----------|-------------|---------|
| `id` (path) | Approve Job | Path | Yes | Job identifier to approve. | `88` |
| `role` / `user_role` | Suspend/Reinstate | JSON/String | Yes | Target user role (aliases accepted). | `employer` |
| `user_id` / `id` / `target_id` | Suspend/Reinstate | JSON/String or Number | Yes | Target user identifier. | `35` |
| `until` / `suspend_until` | Suspend | JSON/String | Optional | ISO-8601 timestamp for suspension expiry. | `2024-06-30T23:59:59Z` |
| `reason` / `note` | Suspend | JSON/String | Optional | Moderation note stored with suspension. | `Fraudulent activity` |

**Response Highlights**

| Field | Type | Mandatory/Optional | Description | Format |
|-------|------|--------------------|-------------|--------|
| `module` | String | Mandatory | Module emitter identifier. | `admin-moderation` |
| `result` | Object | Optional | Outcome details for write commands. | `{ "status": "approved", "job_id": 88 }` |
| `overview` / `metrics` / `audit` | Object | Optional | Command-specific datasets returned for reads. | `{ "pending_jobs": 5, "suspensions": 2 }` |

All administrative endpoints assert guardian permissions (`assertRead`/`assertWrite`) and emit arbiter events prefixed with `admin.moderation.` for downstream enforcement.【F:app/Services/Modules/AdminModerationService.php†L85-L112】

---

## 7. Inter-Module Consumption Examples

* The User Management module requests candidate dossiers through Resume/Profile when `include=profile,resume` and attaches application/job/payment data via their respective services.【F:app/Services/Modules/UserManagementService.php†L182-L239】
* The Payment & Billing module enriches payment summaries with user snapshots fetched from User Management before returning responses or producing webhook events.【F:app/Services/Modules/PaymentBillingService.php†L113-L219】
* The Job Application module augments application detail and summary responses with Resume/Profile data by forwarding through the registry.【F:app/Services/Modules/JobApplicationService.php†L132-L153】

---

## 8. Testing the API Locally

1. Install PHP dependencies: `composer install`
2. Start the built-in server: `php -S 0.0.0.0:8000 -t public`
3. Exercise endpoints, e.g. `curl "http://localhost:8000/public/api/user-management/user/12?include=profile,applications"`

All responses are JSON with HTTP status codes managed by the gateway controller. Errors return an `error` message and status-specific code.
