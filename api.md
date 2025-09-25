
# HireMe API Integration Contract

All modules are exposed through the Laravel gateway at `http://localhost:8000/public/api/{module}/{type}/{id?}` using RESTful JSON payloads. Each response automatically includes a `module` field identifying the producer service. Optional query parameters are supplied via query string for GET requests and JSON bodies for non-GET requests.

---

## 1. User Management & Authentication Module

### 1.1 Login Verification & Session Context

Webservice Mechanism Service Exposure: Login Verification & Session Context
# Protocol: RESTFUL
# Function Description: 1. Validates candidate/employer/recruiter/admin credentials across role models 2. Issues a sanitized user payload and role context when authentication succeeds
# Source Module: User Management & Authentication Module
# Target Module: Resume & Profile Management, Job Posting & Application, Payment & Billing, Administration & Moderation
# URL: http://localhost/HireMe/public/api/user-management/authenticate
# Function Name: authenticateUser()

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
Protocol: RESTFUL
Function Description: 1. Retrieves a specific user record and optional role-scoped view 2. Enriches the response with resume, application, job, payment, or billing data through module forwarding
Source Module: User Management & Authentication Module
Target Module: Resume & Profile Management, Job Posting & Application, Payment & Billing
URL: http://localhost/HireMe/public/api/user-management/user/{userId}
Function Name: showUser()

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

---

## 2. Resume & Profile Management Module

### 2.1 Resume Catalogue & Candidate Summary

Webservice Mechanism Service Exposure: Resume Catalogue & Candidate Summary
Protocol: RESTFUL
Function Description: 1. Lists resumes optionally filtered by candidate scope 2. Embeds candidate information for downstream enrichment
Source Module: Resume & Profile Management Module
Target Module: User Management & Authentication, Job Posting & Application, Administration & Moderation
URL: http://localhost/HireMe/public/api/resume-profile/resumes/{candidateId?}
Function Name: listResumes()

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
Protocol: RESTFUL
Function Description: 1. Aggregates candidate profile data with latest resume rendering 2. Forwards to User Management to attach the owning user account snapshot
Source Module: Resume & Profile Management Module
Target Module: User Management & Authentication, Job Posting & Application
URL: http://localhost/HireMe/public/api/resume-profile/profile/{candidateId}
Function Name: showProfile()

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


| Webservice Mechanism | HTTP | URL Pattern | Function Name | Primary Purpose |
|----------------------|------|-------------|---------------|-----------------|
| Job Listings | GET | `/public/api/job-application/jobs/{scope?}` | `listJobs()` | Filterable listing by status, employer, recruiter, or custom scope hint.【F:app/Services/Modules/JobApplicationService.php†L29-L71】 |
| Job Detail | GET | `/public/api/job-application/job/{id}` | `showJob()` | Retrieve a single job posting with full facade-provided context.【F:app/Services/Modules/JobApplicationService.php†L73-L86】 |
| Application Listings | GET | `/public/api/job-application/applications` | `listApplications()` | Filterable by `job_id` and/or `candidate_id` and includes guardian auditing.【F:app/Services/Modules/JobApplicationService.php†L88-L118】 |
| Application Detail | GET | `/public/api/job-application/application/{id}` | `showApplication()` | Returns an application plus candidate dossier via Resume/Profile service.【F:app/Services/Modules/JobApplicationService.php†L120-L138】 |
| Jobs Summary | GET | `/public/api/job-application/summary/all` | `summarise()` | Aggregated reporting (counts, highlights) for dashboards with candidate enrichment.【F:app/Services/Modules/JobApplicationService.php†L140-L154】 |


## 3. Job Posting & Application Module

### 3.1 Job Listings with Recruiter/Employer Filters

Webservice Mechanism Service Exposure: Job Listings with Recruiter/Employer Filters
Protocol: RESTFUL
Function Description: 1. Provides job listings filtered by status, employer, recruiter, or scope 2. Supports admin guardian auditing for job directory access
Source Module: Job Posting & Application Module
Target Module: Administration & Moderation, User Management & Authentication, Resume & Profile Management
URL: http://localhost/HireMe/public/api/job-application/jobs/{scope?}
Function Name: listJobs()

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

Webservice Mechanism Service Exposure: Application Detail with Candidate Dossier
Protocol: RESTFUL
Function Description: 1. Retrieves a specific job application record 2. Invokes Resume/Profile service to attach candidate dossier data
Source Module: Job Posting & Application Module
Target Module: Resume & Profile Management, Administration & Moderation
URL: http://localhost/HireMe/public/api/job-application/application/{applicationId}
Function Name: showApplication()

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

---

## 4. Payment & Billing Module

### 4.1 Payment Ledger & Status Breakdown

Webservice Mechanism Service Exposure: Payment Ledger & Status Breakdown
Protocol: RESTFUL
Function Description: 1. Returns payments filtered by status, user type, or scoped identifier 2. Provides counts and filter echoes for financial reconciliations
Source Module: Payment & Billing Module
Target Module: Administration & Moderation, User Management & Authentication
URL: http://localhost/HireMe/public/api/payment-billing/payments/{scope?}
Function Name: listPayments()

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
Protocol: RESTFUL
Function Description: 1. Validates and processes a payment charge request through the payment processor 2. Emits payment and billing data plus webhook event name for downstream systems
Source Module: Payment & Billing Module
Target Module: Administration & Moderation, User Management & Authentication, External Billing Dashboards
URL: http://localhost/HireMe/public/api/payment-billing/charge
Function Name: charge()

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

---

## 5. Administration & Moderation Module

### 5.1 Job Approval Command Dispatch

Webservice Mechanism Service Exposure: Job Approval Command Dispatch
Protocol: RESTFUL
Function Description: 1. Validates moderator authority and approves a pending job 2. Dispatches moderation events through the arbiter for audit trails
Source Module: Administration & Moderation Module
Target Module: Job Posting & Application, User Management & Authentication
URL: http://localhost/HireMe/public/api/admin-moderation/approve-job/{jobId}
Function Name: makeApproveJobCommand() via handle('approve-job')

Web Services Request Parameter (provide)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| jobId | path integer | mandatory | Identifier of the job being approved. | 88 |
| X-Admin-Id | header string | optional | Moderator identifier used for auditing. | 12 |
| moderator_id | integer | optional | Moderator identifier fallback when header absent. | 12 |

Web Services Response Parameter (consume)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| module | string | mandatory | Service emitter identifier. | admin-moderation |
| result.command | string | mandatory | Command key executed by the moderation bus. | approve-job |
| result.status | string | mandatory | Outcome status from the command. | approved |
| result.payload.job_id | integer | optional | Echo of the approved job identifier. | 88 |

### 5.2 User Suspension Lifecycle Management

Webservice Mechanism Service Exposure: User Suspension Lifecycle Management
Protocol: RESTFUL
Function Description: 1. Suspends a user with optional expiry and moderation reason 2. Logs guardian audits and emits moderation events for enforcement
Source Module: Administration & Moderation Module
Target Module: User Management & Authentication, Resume & Profile Management, Payment & Billing
URL: http://localhost/HireMe/public/api/admin-moderation/suspend-user
Function Name: makeSuspendUserCommand() via handle('suspend-user')

Web Services Request Parameter (provide)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| role | string | mandatory | Role slug for the target account. | employer |
| user_id | integer | mandatory | Identifier of the account being suspended. | 35 |
| until | string | optional | ISO-8601 timestamp specifying suspension expiry. | 2024-06-30T23:59:59Z |
| reason | string | optional | Moderator-provided suspension note. | Fraudulent activity |
| X-Admin-Id | header string | optional | Acting moderator identifier for audit trails. | 12 |

Web Services Response Parameter (consume)
| Field Name | Field Type | Mandatory/ Optional | Description | Format |
|------------|-----------|----------------------|-------------|--------|
| module | string | mandatory | Service emitter identifier. | admin-moderation |
| result.command | string | mandatory | Command key executed by the moderation bus. | suspend-user |
| result.status | string | mandatory | Outcome status from the command execution. | success |
| result.payload.role | string | optional | Role that was suspended. | employer |
| result.payload.user_id | integer | optional | Identifier of the suspended account. | 35 |
| result.payload.until | string | optional | Suspension expiry timestamp when provided. | 2024-06-30T23:59:59+00:00 |

---

## 6. Local Testing Notes

1. Install dependencies: `composer install`
2. Run the Laravel built-in server: `php -S 0.0.0.0:8000 -t public`
3. Exercise endpoints with cURL or Postman using the URLs above and JSON payloads.

All services return JSON responses and rely on guardian assertions defined in `AbstractModuleService` to enforce permissions.

