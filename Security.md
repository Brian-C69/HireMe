# Security Guidelines

## 1. User Management & Authentication Module

### 1.1 Potential Threat/Attack
- **Threat 1: Brute Force Login Attack** – Automated scripts repeatedly submit credentials to guess a valid email/password pair.
- **Threat 2: Password Reset Token Abuse** – Attackers try to reuse or brute-force password reset tokens to take over accounts.

### 1.2 Secure Coding Practices Implemented in HireMe

**Solution for Threat 1: Login throttling, lockout, and CSRF checks**

The production login flow relies on `App\Services\AttemptService` to track failures per email/IP pair and to block requests once the configured threshold is exceeded. The controller consults this service before authenticating credentials and always enforces CSRF protection.

```php
// app/Services/AttemptService.php
public function isLockedOut(PDO $pdo, string $email, string $ip): bool
{
    $st = $pdo->prepare("SELECT attempts,last_attempt_at FROM login_attempts WHERE email=:e AND ip_address=:i LIMIT 1");
    $st->execute([':e' => $email, ':i' => $ip]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;
    if ((int)$row['attempts'] < self::MAX_ATTEMPTS) return false;
    return (time() - strtotime((string)$row['last_attempt_at'])) < self::LOCK_MINUTES * 60;
}
```

```php
// app/Controllers/AuthController.php (excerpt from doLogin)
if (!$this->verifyCsrf($_POST['csrf'] ?? null)) {
    $this->flash('danger', 'Invalid session.');
    $this->redirect('/login');
}

if ($attemptSvc->isLockedOut($pdo, $email, $ip)) {
    $this->flash('danger', 'Too many failed attempts. Please reset your password or try again later.');
    $this->redirect('/login');
}

if (!$found || !password_verify($pass, (string)$found['user']['password_hash'])) {
    $attemptSvc->recordFailure($pdo, $email, $ip);
    ...
}
```

**Solution for Threat 2: Single-use, hashed reset tokens with expiry**

Password reset links are generated with 32-byte random tokens. Only SHA-256 hashes of the tokens are stored, previous tokens are invalidated, and tokens are marked as used inside a transaction when the password changes.

```php
// app/Controllers/AuthController.php (sendReset)
$token = bin2hex(random_bytes(32));
$hash = hash('sha256', $token);
$expires = date('Y-m-d H:i:s', time() + 60 * 60);
$pdo->prepare("DELETE FROM password_resets WHERE email=:e OR expires_at < NOW() OR used_at IS NOT NULL")
    ->execute([':e' => $email]);
$pdo->prepare("INSERT INTO password_resets (email,user_type,token_hash,expires_at) VALUES (:e,:t,:h,:x)")
    ->execute([':e' => $email, ':t' => $found['user']['role'], ':h' => $hash, ':x' => $expires]);
```

```php
// app/Controllers/AuthController.php (processReset)
$st = $pdo->prepare("SELECT email,user_type,expires_at,used_at FROM password_resets WHERE token_hash=:h LIMIT 1");
$st->execute([':h' => $hash]);
...
$provider->updatePassword($pdo, $email, $newHash);
$pdo->prepare("UPDATE password_resets SET used_at=NOW() WHERE token_hash=:h")->execute([':h' => $hash]);
$pdo->prepare("DELETE FROM password_resets WHERE email=:e AND used_at IS NULL")->execute([':e' => $email]);
```

## 2. Resume & Profile Management Module

### 2.1 Potential Threat/Attack
- **Threat 1: File Upload Vulnerability** – Uploading executable or overly large files as profile photos, resumes, or verification documents.
- **Threat 2: Unauthorized Profile Access** – Manipulating identifiers to read or modify another user’s profile data.

### 2.2 Secure Coding Practices Implemented in HireMe

**Solution for Threat 1: MIME/extension validation, size limits, and safe storage**

`CandidateController` performs MIME detection via `mime_content_type`, restricts extensions, enforces size caps, randomises filenames, and stores uploads under `/public/assets/uploads/...` with role-specific prefixes.

```php
// app/Controllers/CandidateController.php (photo upload excerpt)
$okTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
$mime = mime_content_type($up['tmp_name']) ?: '';
if (!isset($okTypes[$mime])) {
    $errors['profile_picture'] = 'Only JPG or PNG allowed.';
} elseif ($up['size'] > 2 * 1024 * 1024) {
    $errors['profile_picture'] = 'Max 2MB.';
} else {
    $ext = $okTypes[$mime];
    $name = 'cand_' . $uid . '_' . time() . '.' . $ext;
    $dest = $profilesDir . '/' . $name;
    if (!move_uploaded_file($up['tmp_name'], $dest)) {
        $errors['profile_picture'] = 'Upload failed.';
    } else {
        $profileUrl = '/assets/uploads/profiles/' . $name;
    }
}
```

The same controller applies similar checks to resume uploads (PDF/DOC/DOCX, 5 MB limit) and candidate verification documents, rejecting unexpected MIME types and handling failures gracefully.

**Solution for Threat 2: Role checks and owner verification before data access**

All profile update endpoints require the caller to be signed in as a `Candidate`. The controller fetches the profile by the session user ID and aborts if no match exists, preventing direct-object-reference attacks.

```php
// app/Controllers/CandidateController.php (account update)
Auth::requireRole('Candidate');
$pdo = \App\Core\DB::conn();
$uid = (int)($_SESSION['user']['id'] ?? 0);
$st  = $pdo->prepare("SELECT * FROM candidates WHERE candidate_id = :id LIMIT 1");
$st->execute([':id' => $uid]);
$candidate = $st->fetch();
if (!$candidate) {
    $this->flash('danger', 'Profile not found.');
    $this->redirect('/account');
}
```

## 3. Job Posting & Application Module

### 3.1 Potential Threat/Attack
- **Threat 1: Cross-Site Scripting (XSS)** – Malicious markup submitted via job descriptions, candidate summaries, or messaging.
- **Threat 2: Access to Sensitive Data** – Employers or recruiters attempting to modify or read jobs that do not belong to them.

### 3.2 Secure Coding Practices Implemented in HireMe

**Solution for Threat 1: Consistent output encoding in views**

Server-rendered templates escape untrusted fields with `htmlspecialchars`, preventing injected HTML/JavaScript from executing in browsers.

```php
// app/Views/jobs/mine.php (excerpt)
<?php foreach ($jobs as $j): ?>
    <?php $title = htmlspecialchars($j['job_title'] ?? ''); ?>
    <?php $company = htmlspecialchars($j['company_name'] ?? ''); ?>
    <td><?= htmlspecialchars($posted) ?></td>
    <td><span class="<?= $badge($status) ?>"><?= htmlspecialchars($status) ?></span></td>
<?php endforeach; ?>
```

Similar escaping patterns exist across candidate, resume, and admin views to neutralise user-supplied content before rendering.

**Solution for Threat 2: Ownership checks on every mutating action**

Before editing, updating status, or deleting a job, the controller ensures the authenticated employer or recruiter owns the record.

```php
// app/Controllers/JobController.php
private function ownJob(PDO $pdo, int $jobId): ?array
{
    $st = $pdo->prepare("SELECT * FROM job_postings WHERE job_posting_id=:id LIMIT 1");
    $st->execute([':id' => $jobId]);
    $job = $st->fetch();
    if (!$job) return null;

    $role = $_SESSION['user']['role'] ?? '';
    $uid  = (int)($_SESSION['user']['id'] ?? 0);
    if ($role === 'Employer'  && (int)$job['company_id'] === $uid) return $job;
    if ($role === 'Recruiter' && (int)($job['recruiter_id'] ?? 0) === $uid) return $job;
    return null;
}
```

All state-changing actions call `Auth::requireRole(['Employer','Recruiter'])` and halt when `ownJob` returns `null`.

## 4. Payment & Billing Module

### 4.1 Potential Threat/Attack
- **Threat 1: Fake Payment Confirmation** – Forged callbacks to unlock credits or premium features without real Stripe payments.
- **Threat 2: Cross-Site Request Forgery (CSRF)** – Victims tricked into submitting payment or credit-purchase forms.

### 4.2 Secure Coding Practices Implemented in HireMe

**Solution for Threat 1: Stripe signature verification and atomic fulfilment**

Incoming Stripe webhooks are validated with the configured signing secret. Only verified `checkout.session.completed` events update balances, and the raw payload is stored for auditing.

```php
// app/Controllers/PaymentController.php (webhook)
$payload = file_get_contents('php://input') ?: '';
$sig     = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$secret  = (string)($this->cfgGet('stripe.webhook_secret', '') ?? '');

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig, $secret);
} catch (\UnexpectedValueException | \Stripe\Exception\SignatureVerificationException) {
    http_response_code(400);
    echo 'Invalid payload';
    return;
}

if ($event->type === 'checkout.session.completed') {
    $pdo = DB::conn();
    $pdo->beginTransaction();
    ... // update stripe_payments + user entitlements
    $pdo->commit();
}
```

**Solution for Threat 2: CSRF tokens on every form + role checks**

Credit and premium purchase forms include synchroniser tokens. Controllers reject requests when the submitted token does not match the session value.

```php
// app/Controllers/PaymentController.php (checkoutCredits)
Auth::requireRole(['Employer', 'Recruiter']);
if (!$this->csrfOk()) {
    $this->flash('danger', 'Invalid session.');
    $this->redirect('/credits');
}
$qty = (int)($_POST['credits_qty'] ?? 0);
if (!in_array($qty, [5, 10, 50, 100, 250, 500], true)) {
    $_SESSION['errors'] = ['credits_qty' => 'Please choose a valid credit amount.'];
    $this->redirect('/credits');
}
```

CSRF helpers are also used by premium, refund, and admin-facing payment endpoints to prevent unwanted state changes.

## 5. Administration & Moderation Module

### 5.1 Potential Threat/Attack
- **Threat 1: SQL Injection** – Malicious search/filter input injected into admin queries to extract or alter data.
- **Threat 2: API and Session Hijacking** – Stolen cookies or unauthorised roles accessing privileged administration pages.

### 5.2 Secure Coding Practices Implemented in HireMe

**Solution for Threat 1: Prepared statements and whitelisted filters**

Admin list pages use PDO prepared statements with bound parameters for every dynamic clause. Only expected filters are concatenated into the SQL string, protecting against injection.

```php
// app/Controllers/AdminController.php (employersIndex excerpt)
if ($q !== '') {
    $where[] = "(e.company_name LIKE :q OR e.email LIKE :q OR e.industry LIKE :q OR e.location LIKE :q OR e.contact_person_name LIKE :q OR e.contact_number LIKE :q)";
    $bind[':q'] = "%$q%";
}
...
$sql = "SELECT e.employer_id, e.company_name, e.email, ... FROM employers e $wsql ORDER BY e.created_at DESC LIMIT :limit OFFSET :offset";
$st = $pdo->prepare($sql);
foreach ($bind as $k => $v) $st->bindValue($k, $v);
$st->bindValue(':limit', $per, \PDO::PARAM_INT);
$st->bindValue(':offset', $offset, \PDO::PARAM_INT);
$st->execute();
```

**Solution for Threat 2: Strict role enforcement and hardened session cookies**

Admin endpoints wrap every action with `requireAdmin()` which delegates to `Auth::requireRole('Admin')`. Sessions are initialised with secure cookie attributes, inactivity timeouts, and strict mode to reduce hijacking risk.

```php
// app/Controllers/AdminController.php
private function requireAdmin(): void
{
    Auth::requireRole('Admin');
}
```

```php
// app/bootstrap.php (session bootstrap excerpt)
$sessionOptions = [
    'cookie_lifetime' => 0,
    'cookie_path' => $cookieParams['path'] ?? '/',
    'cookie_secure' => (($cookieParams['secure'] ?? false) || $isSecure),
    'cookie_httponly' => true,
    'cookie_samesite' => ucfirst($normalisedSameSite),
    'use_strict_mode' => 1,
    'gc_maxlifetime' => max($timeoutSeconds, (int) ini_get('session.gc_maxlifetime')),
];
$startSession();
...
if (isset($_SESSION['user'])) {
    $lastActivity = $_SESSION['last_activity'] ?? null;
    if (is_int($lastActivity) && ($now - $lastActivity) > $timeoutSeconds) {
        $_SESSION = [];
        session_destroy();
        $startSession();
        $_SESSION['flash'] = [
            'type' => 'warning',
            'message' => 'Your session expired due to inactivity. Please log in again.',
        ];
    }
}
```

Together, these measures ensure that only authenticated admin users retain access, sessions are short-lived, and cookies cannot be read or replayed via client-side scripts.

---

The snippets above are taken directly from the current HireMe codebase to document how each module mitigates its respective threats.
