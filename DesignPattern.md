# Design Patterns for HireMe Modules

HireMe organises core capabilities—users, resumes, jobs, payments, and administration—into dedicated modules that expose a consistent request/response contract via `AbstractModuleService`. Each module combines Laravel models with bespoke service layers and applies a targeted design pattern to tame complexity, align collaborators, and make cross‑module integration through `ModuleRegistry` predictable. The sections below document the pattern, implementation structure, and rationale for every module.

---

## 1. User Management & Authentication Module — Strategy Pattern (ZX)

### Design Pattern

The authentication surface supports multiple user roles (candidate, employer, recruiter) that all share login, registration, and password reset flows but persist their data in role‑specific tables. The module therefore uses the **Strategy** pattern: the `AuthController` chooses a `UserProviderInterface` implementation at runtime, delegating user discovery, metadata loading, registration, and password maintenance to role‑specific strategies while keeping the high‑level workflow unchanged. Each concrete provider encapsulates the SQL tailored to its table while honouring the shared interface. 【F\:app/Controllers/AuthController.php†L82-L379】【F\:app/Controllers/Auth/UserProviderInterface.php†L8-L24】【F\:app/Controllers/Auth/Providers/CandidateProvider.php†L9-L55】

### Implementation & Coding

```
+----------------------+                            +------------------------------+
|  AuthController      |   --------------------->   |      UserProviderInterface   |
+----------------------+        depends on          +------------------------------+
|  doLogin()           |                             |             |             |
|  doRegister()        |                             v             v             v
|  processReset()      |               +-----------------+ +----------------+ +-----------------+
+----------------------+               |CandidateProvider| |EmployerProvider| |RecruiterProvider|
            |                          +-----------------+ +----------------+ +-----------------+
            | uses
            v
+---------------------------+
|  UserProviderFactory      |
+---------------------------+
|  providers()              |
|  findByEmail()            |
|  providerForRole()        |
+---------------------------+


```php
// Use factory to find user + provider (strategy)
$found = UserProviderFactory::findByEmail($pdo, $email); // returns ['provider'=>..., 'user'=>...]
if (!$found || !password_verify($pass, (string)$found['user']['password_hash'])) {
    // record failure and show remaining attempts
    $attemptSvc->recordFailure($pdo, $email, $ip);
    $left = max(0, AttemptService::MAX_ATTEMPTS - $attemptSvc->attemptCount($pdo, $email, $ip));
    $this->setErrors([
        'email' => 'Invalid email or password.',
        'password' => $left > 0 ? "You have {$left} attempt(s) left." : 'Limit reached. Please reset your password.',
    ]);
    $this->setOld(['email' => $email]);
    $this->redirect('/login');
}

// success -> fetch meta using provider (strategy)
$provider = $found['provider'];
$user = $found['user'];
$meta = $provider->fetchMeta($pdo, (int)$user['id']);
```

* `AuthController::doLogin()` and `doRegister()` invoke `UserProviderFactory::findByEmail()`/`providerForRole()` to obtain the correct strategy before fetching metadata or creating a record. 【F\:app/Controllers/AuthController.php†L112-L244】【F\:app/Controllers/Auth/UserProviderFactory.php†L11-L50】
* The `UserProviderInterface` defines the algorithm family (lookup, metadata, create, password update), and each provider supplies its SQL, keeping role peculiarities isolated. 【F\:app/Controllers/Auth/UserProviderInterface.php†L8-L24】【F\:app/Controllers/Auth/Providers/CandidateProvider.php†L9-L55】
* Module‑facing APIs such as `UserManagementService::handle('authenticate')` still expose a unified contract, returning role metadata that can be enriched through `ModuleRegistry` for dashboards and profile lookups. 【F\:app/Services/Modules/UserManagementService.php†L15-L298】

### Justification

Strategy cleanly separates per‑role persistence rules from shared authentication workflows. Adding a new persona now requires only a new provider class registered with the factory, leaving controller logic untouched and avoiding cascades of conditionals across login, registration, and reset flows. 【F\:app/Controllers/Auth/UserProviderFactory.php†L11-L50】【F\:app/Controllers/AuthController.php†L112-L372】 Security checks—rate limiting, password hashing, metadata projection—stay centralised in the controller, while providers focus on storage concerns for easier testing and maintenance. 【F\:app/Controllers/AuthController.php†L102-L379】【F\:app/Controllers/Auth/Providers/CandidateProvider.php†L13-L55】

---

## 2. Resume & Profile Management Module — Builder Pattern (YX)

### Design Pattern

Generating resumes requires assembling headers, summaries, experience, skills, and format‑specific markup in different combinations (full profile versus preview, HTML versus JSON). The module applies the **Builder** pattern: `ProfileDirector` orchestrates the construction steps against the `ProfileBuilder` interface, and concrete builders (`HtmlProfileBuilder`, `JsonProfileBuilder`) render the sections in their native representation without duplicating orchestration logic. 【F\:app/Services/Resume/Builder/ProfileDirector.php†L12-L158】【F\:app/Services/Resume/Builder/ProfileBuilder.php†L10-L39】

### Implementation & Coding

```
+------------------------+
| ResumeProfileService   |
| renderResumeOutput()   |
+-----------+------------+
            | uses
            v
+------------------------+        +---------------------------+
|    ProfileDirector     |<-------|     ResumeService         |
| buildFullProfile()     |        | buildGeneratedResume()    |
| buildPreview()         |        +---------------------------+
+-----------+------------+
            | directs calls via ProfileBuilder
            v
    +------------------------+
    |   ProfileBuilder       |
    +-----------+------------+
                |
    -------------------------------
    |                             |
+--------------------+   +--------------------+
| HtmlProfileBuilder |   | JsonProfileBuilder |
+--------------------+   +--------------------+
```

```php
$builder = $this->selectBuilderForResume($resume, $data);
$format = $builder->getFormat();

$output = $variant === 'preview'
    ? $this->profileDirector->buildPreview($builder, $data)
    : $this->profileDirector->buildFullProfile($builder, $data);
```

* `ResumeProfileService::renderResumeOutput()` parses stored resume JSON, selects the proper builder, and asks the director to produce either a preview or full render. 【F\:app/Services/Modules/ResumeProfileService.php†L146-L232】
* `ResumeService::generate()` resolves a builder (HTML or JSON) and delegates orchestration to `ProfileDirector`, capturing the returned file path and metadata for persistence and notification. 【F\:app/Services/ResumeService.php†L40-L146】
* `ProfileDirector` sequences section construction (header, summary, experience, skills) while the builders encapsulate formatting specifics—HTML markup or JSON serialisation—behind the shared `ProfileBuilder` contract. 【F\:app/Services/Resume/Builder/ProfileDirector.php†L12-L158】【F\:app/Services/Resume/Builder/HtmlProfileBuilder.php†L12-L181】【F\:app/Services/Resume/Builder/JsonProfileBuilder.php†L13-L85】

### Justification

The Builder pattern localises the combinatorial logic for assembling resumes. Directors ensure every output includes the same ordering and fallbacks, while builders specialise in presentation, making it straightforward to add new formats (e.g., PDF) or tweak section rendering without touching orchestration code. The separation also keeps `ResumeService` transaction‑safe and focused on persistence, further simplifying maintenance when new resume templates or preview variants are introduced. 【F\:app/Services/Resume/Builder/ProfileDirector.php†L12-L158】【F\:app/Services/Resume/Builder/ProfileBuilder.php†L10-L39】【F\:app/Services/ResumeService.php†L40-L146】

---

## 3. Job Posting & Application Module — Facade Pattern (FW)

### Design Pattern

Job management spans validation, authorisation, persistence, analytics, search, notification, and application workflows. Rather than exposing these subsystems individually, the module offers a `JobModuleFacade` that fronts them with a cohesive API—classic **Facade** pattern. Clients such as `JobApplicationService` call the facade to list jobs, publish updates, summarise analytics, or fetch applications without being aware of the underlying collaborators. 【F\:app/Services/Job/JobModuleFacade.php†L13-L238】

### Implementation & Coding

```
+---------------------------+
|   JobApplicationService   |
|   handle()/list/show...   |
+-------------+-------------+
              | delegates
              v
+--------------------------------------+
|           JobModuleFacade            |
| publishJob() / listJobs() / ...      |
+--+------+------+------+------+-------+
   |      |      |      |      |
   v      v      v      v      v
Validator Authorizer Repository Notifier Analytics
   |                                   |
   v                                   v
Search Service                   Application Workflow
```

```php
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
```

* `JobApplicationService` routes module requests directly through the facade, inheriting its filtering, enrichment, and cross‑module callbacks (e.g., resolving candidate profiles). 【F\:app/Services/Modules/JobApplicationService.php†L25-L123】
* `JobModuleFacade` wires together `JobInputValidator`, `JobAuthorizationService`, `JobRepository`, `JobNotificationService`, `JobSearchService`, `JobAnalyticsService`, and the `JobApplicationWorkflow`, coordinating them when publishing or updating jobs, listing entities, or processing applications. 【F\:app/Services/Job/JobModuleFacade.php†L13-L238】
* Each subsystem remains independently testable: validation normalises inputs and errors, 【F\:app/Services/Job/JobInputValidator.php†L11-L74】 authorisation enforces role rules, 【F\:app/Services/Job/JobAuthorizationService.php†L12-L51】 the repository manages transactions, 【F\:app/Services/Job/JobRepository.php†L13-L41】 search hydrates related models, 【F\:app/Services/Job/JobSearchService.php†L18-L69】 analytics aggregates KPIs, 【F\:app/Services/Job/JobAnalyticsService.php†L23-L71】 and the workflow handles application life cycles. 【F\:app/Services/Job/JobApplicationWorkflow\.php†L12-L184】

### Justification

Providing a Facade keeps controllers and module clients simple while allowing the job domain to evolve internally. New services (e.g., improved search or analytics) can be swapped in behind the facade without touching callers, and cross‑cutting flows—like refreshing search indices and logging analytics after a publish—stay in one place for consistency. 【F\:app/Services/Job/JobModuleFacade.php†L27-L124】 This arrangement also supports module‑to‑module coordination (forwarding to resume profiles or user data) through a single entry point, reducing duplicate integration code. 【F\:app/Services/Modules/JobApplicationService.php†L101-L123】

---

## 4. Payment & Billing Module — Observer Pattern (TC)

### Design Pattern

Payment processing emits events (paid, failed, refunded, pending) that must trigger disparate reactions: update invoices, adjust subscription credits, notify accounting, and surface aggregates. The module therefore applies the **Observer** pattern: `PaymentProcessor` acts as the subject, broadcasting `PaymentEvent` instances to registered observers that encapsulate each side effect.

### Implementation & Coding

```
+---------------------------+
| PaymentBillingService     |
| charge()/summary()        |
+-------------+-------------+
              | uses
              v
+---------------------------+
|    PaymentProcessor       |
| process() / notify()      |
+------+------+------+------+
       |      |      |
       v      v      v
+-----------+ +-----------------------+ +-----------------------+
| Invoice   | | SubscriptionState     | | AccountingNotifier    |
| Status    | | Manager               | | (log file observer)   |
| Updater   | +-----------------------+ +-----------------------+
+-----------+
```

```php
public static function withDefaultObservers(?string $logFile = null): self
{
    $logFile ??= dirname(__DIR__, 3) . '/storage/logs/accounting.log';

    $invoiceUpdater = new InvoiceStatusUpdater();
    $subscriptionManager = new SubscriptionStateManager();
    $accountingNotifier = new AccountingNotifier($logFile);

    $processor = new self();
    $processor->attach(self::EVENT_INVOICE_PAID, $invoiceUpdater);
    $processor->attach(self::EVENT_PAYMENT_FAILED, $invoiceUpdater);
    $processor->attach(self::EVENT_PAYMENT_REFUNDED, $invoiceUpdater);
    $processor->attach(self::EVENT_INVOICE_PAID, $subscriptionManager);
    $processor->attach(self::EVENT_PAYMENT_REFUNDED, $subscriptionManager);
    $processor->attach(self::EVENT_INVOICE_PAID, $accountingNotifier);
    $processor->attach(self::EVENT_PAYMENT_FAILED, $accountingNotifier);
    $processor->attach(self::EVENT_PAYMENT_REFUNDED, $accountingNotifier);

    return $processor;
}

public function notify(PaymentEvent $event): void
{
    $names = [$this->normaliseEvent($event->name())];
    if (isset($this->observers['*'])) {
        $names[] = '*';
    }

    foreach ($names as $name) {
        if (!isset($this->observers[$name])) {
            continue;
        }

        /** @var PaymentObserver $observer */
        foreach ($this->observers[$name] as $observer) {
            $observer->handle($event);
        }
    }
}
```

* `PaymentBillingService` composes a processor via `PaymentProcessor::withDefaultObservers()`, forwards charge requests to `process()`, and enriches responses with user/billing lookups to keep module results cohesive. 【F\:app/Services/Modules/PaymentBillingService.php†L13-L284】
* `PaymentProcessor` normalises payloads, persists `Payment` models, determines the event name, and notifies observers registered per event channel or wildcard. 【F\:app/Services/Payment/PaymentProcessor.php†L37-L149】
* Observers `InvoiceStatusUpdater`, `SubscriptionStateManager`, and `AccountingNotifier` react independently: updating billing rows, applying or reverting credits/premium badges, and logging to disk, respectively. 【F\:app/Services/Payment/Observers/InvoiceStatusUpdater.php†L12-L99】【F\:app/Services/Payment/Observers/SubscriptionStateManager.php†L15-L154】【F\:app/Services/Payment/Observers/AccountingNotifier.php†L11-L50】

### Justification

Observer decouples payment side effects so that new reactions—such as webhook calls or fraud scoring—can be added without editing the core processor. Existing observers remain focused on their responsibility, and failures in one listener do not block others, improving resilience for billing workflows while keeping `PaymentBillingService` thin and testable.

---

## 5. Administration & Moderation Module — Command Pattern (ZC)

### Design Pattern

Administrative operations (overview dashboards, metrics, audits, approving jobs, suspending or reinstating users) are modelled as discrete command objects. The **Command** pattern encapsulates each request—including authorisation and logging hooks—allowing the `AdminModerationService` to dispatch them via a `ModerationCommandBus` that standardises execution and reporting.

### Implementation & Coding

```
+--------------------------------+
|   AdminModerationService       |
|   handle()                     |
+-------------+------------------+
              | dispatches
              v
+-------------------------------+
|     ModerationCommandBus      |
|  authorize -> log -> execute  |
+-------------+-----------------+
              |
              v
      +---------------------+
      | ModerationCommand   |
      +---------------------+
              |
      ------------------------------
      |            |               |
+-------------+ +-------------+ +------------------+
|OverviewCommand|MetricsCommand|AuditLogCommand    |
+-------------+ +-------------+ +------------------+
      |            |               |
      ------------------------------
      |            |
+---------------------+   +-------------------------+
|SuspendUserCommand   |   |ReinstateUserCommand    |
+---------------------+   +-------------------------+
          |
+---------------------+
|ApproveJobCommand    |
+---------------------+
```

```php
public function dispatch(ModerationCommand $command): ModerationCommandResult
{
    $this->authorizer->authorize($command);

    $this->logger->info(sprintf('Dispatching moderation command "%s".', $command->name()));

    try {
        $result = $command->execute();
        $this->logger->info(
            sprintf('Command "%s" completed with status "%s".', $command->name(), $result->status()),
            ['command' => $command->name(), 'status' => $result->status()]
        );

        return $result;
    } catch (Throwable $exception) {
        $this->logger->error(
            sprintf('Command "%s" failed: %s', $command->name(), $exception->getMessage()),
            ['command' => $command->name(), 'exception' => $exception::class]
        );

        throw $exception;
    }
}
```

* `AdminModerationService` converts incoming requests into command instances (overview, metrics, audit, approve, suspend, reinstate) and hands them to the bus, receiving either structured data or a `ModerationCommandResult` for action responses. 【F\:app/Services/Modules/AdminModerationService.php†L33-L209】
* `ModerationCommandBus` enforces authorisation via `AdminRequestAuthorizer`, logs lifecycle events with `ErrorLogModerationLogger`, and supports queueing/retrying before executing the command’s `execute()` method. 【F\:app/Services/Admin/Moderation/ModerationCommandBus.php†L9-L102】【F\:app/Services/Admin/Moderation/AdminRequestAuthorizer.php†L8-L33】【F\:app/Services/Admin/Moderation/ErrorLogModerationLogger.php†L5-L18】
* Concrete commands encapsulate their logic: dashboards aggregate module data, metrics compute counts, audit lists flagged entities, while suspend/reinstate commands persist suspension state via `ModerationSuspensionStore` and `UserLookup` helpers. 【F\:app/Services/Admin/Moderation/Commands/OverviewCommand.php†L13-L46】【F\:app/Services/Admin/Moderation/Commands/MetricsCommand.php†L9-L40】【F\:app/Services/Admin/Moderation/Commands/AuditLogCommand.php†L13-L83】【F\:app/Services/Admin/Moderation/Commands/SuspendUserCommand.php†L13-L56】【F\:app/Services/Admin/Moderation/Commands/ReinstateUserCommand.php†L13-L48】【F\:app/Services/Admin/Moderation/ModerationSuspensionStore.php†L12-L88】【F\:app/Services/Admin/Moderation/UserLookup.php†L9-L24】

### Justification

Command isolates moderation actions so policy‑heavy logic (authorisation, auditing, suspension rules) lives alongside the action that needs it. New administrative capabilities become new command classes, and the bus guarantees consistent logging and permission checks without duplicating code across handlers. The pattern also streamlines testing—each command can be exercised independently—and supports future enhancements like background execution by reusing the queueing facilities already present in the bus. 【F\:app/Services/Admin/Moderation/ModerationCommandBus.php†L9-L102】【F\:app/Services/Admin/Moderation/Commands/SuspendUserCommand.php†L13-L56】

---

### Notes on Conventions

* **Citations** use the format `【F:<path>†Lstart-Lend】` to point to implementation lines.
* **UML‑style diagrams** are simplified ASCII for quick comprehension.
* All module services obey a shared `AbstractModuleService` contract and are registered in `ModuleRegistry` (not shown) to standardise integration boundaries.
