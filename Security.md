# Security Guidelines

## 1. User Management & Authentication Module

### 1.1 Potential Threat/Attack
- **Threat 1: Brute Force Login Attack**  
  Automated scripts may attempt to guess user credentials by repeatedly submitting login requests.
- **Threat 2: Password Reset Token Abuse**  
  Attackers may brute-force or reuse leaked password reset tokens to take over accounts.

### 1.2 Secure Coding Practice

**Solution for Threat 1: Rate Limiting & Account Lockout**  
Implement login throttling, exponential backoff, and temporary account lockout after successive failed attempts.

```php
// app/Http/Controllers/Auth/LoginController.php
public function login(Request $request)
{
    $credentials = $request->only('email', 'password');

    if (RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
        throw ValidationException::withMessages([
            'email' => ['Too many login attempts. Please try again later.'],
        ]);
    }

    if (!Auth::attempt($credentials)) {
        RateLimiter::hit($this->throttleKey($request), now()->addMinutes(15));
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    RateLimiter::clear($this->throttleKey($request));
    return redirect()->intended('/dashboard');
}
```

**Solution for Threat 2: Signed, Single-Use Tokens**  
Use signed tokens with short expiration and mark tokens as consumed upon use.

```php
// app/Services/PasswordResetService.php
public function createResetToken(User $user): string
{
    $token = Str::random(64);
    DB::table('password_resets')->updateOrInsert(
        ['email' => $user->email],
        [
            'token' => Hash::make($token),
            'created_at' => now(),
            'used' => false,
        ]
    );

    return Crypt::encryptString($user->email.'|'.$token.'|'.now()->addMinutes(30));
}

public function resetPassword(string $encryptedToken, string $password): void
{
    [$email, $token, $expires] = explode('|', Crypt::decryptString($encryptedToken));
    abort_if(now()->greaterThan($expires), 410, 'Token expired.');

    $record = DB::table('password_resets')->where('email', $email)->first();
    abort_unless($record && !$record->used && Hash::check($token, $record->token), 403);

    DB::transaction(function () use ($email, $password) {
        User::where('email', $email)->update(['password' => Hash::make($password)]);
        DB::table('password_resets')->where('email', $email)->update([
            'used' => true,
            'used_at' => now(),
        ]);
    });
}
```

## 2. Resume & Profile Management Module

### 2.1 Potential Threat/Attack
- **Threat 1: File Upload Vulnerability**  
  Malicious users may upload executable files disguised as resumes or profile pictures.
- **Threat 2: Unauthorized Profile Access**  
  Attackers might attempt to view other users' profiles by tampering with identifiers.

### 2.2 Secure Coding Practice

**Solution for Threat 1: File Type Validation & Sanitized Storage**

```php
// app/Http/Controllers/ProfileController.php
public function uploadResume(Request $request)
{
    $request->validate([
        'resume' => 'required|file|mimes:pdf,doc,docx|max:2048',
    ]);

    $file = $request->file('resume');
    $storedName = (string) Str::uuid().'.'.$file->getClientOriginalExtension();

    Storage::disk('resumes')->putFileAs('', $file, $storedName);
}
```

- Check MIME type and extension.  
- Limit file size (e.g., 2MB).  
- Store files outside the public web root (use private disk in Laravel).  
- Generate random filenames.

**Solution for Threat 2: Authorization Policies**

```php
// app/Policies/ProfilePolicy.php
public function view(User $user, Profile $profile): bool
{
    return $user->id === $profile->user_id || $user->hasRole('admin');
}

// controller
public function show(Profile $profile)
{
    $this->authorize('view', $profile);
    return view('profiles.show', compact('profile'));
}
```

## 3. Job Posting & Application Module

### 3.1 Potential Threat/Attack
- **Threat 1: Cross-Site Scripting (XSS)**  
  Attackers may inject malicious scripts into job descriptions or application messages.
- **Threat 2: Access to Sensitive Data**  
  Unauthorized access to application data or job analytics may expose personal information.

### 3.2 Secure Coding Practice

**Solution for Threat 1: Output Encoding & HTML Sanitization**

```php
// resources/views/jobs/show.blade.php
<h1>{{ $job->title }}</h1>
<p>{!! Purifier::clean($job->description, ['HTML.Allowed' => 'p,ul,ol,li,b,strong,i,em,a[href]']) !!}</p>
```

- Store raw input but sanitize/encode before rendering.  
- Use a library like HTMLPurifier to allow only safe tags/attributes.  
- Enforce Content Security Policy (CSP) headers.

**Solution for Threat 2: Scoped Queries & Data Masking**

```php
// app/Repositories/ApplicationRepository.php
public function getApplicationsForEmployer(User $employer)
{
    return Application::query()
        ->whereHas('job', fn ($q) => $q->where('employer_id', $employer->id))
        ->with(['applicant:id,name,email'])
        ->select(['id', 'job_id', 'applicant_id', 'status'])
        ->paginate();
}
```

- Restrict queries to the authenticated employer.  
- Avoid selecting sensitive columns unless necessary.  
- Apply data masking or redaction for PII when exporting.

## 4. Payment & Billing Module

### 4.1 Potential Threat/Attack
- **Threat 1: Fake Payment Confirmation**  
  Attackers may forge payment callbacks to unlock premium features without paying.
- **Threat 2: Cross-Site Request Forgery (CSRF)**  
  Victims might unknowingly trigger payment-related actions via crafted links or forms.

### 4.2 Secure Coding Practice

**Solution for Threat 1: Signed Webhook Verification**

```php
// app/Http/Controllers/PaymentWebhookController.php
public function handle(Request $request)
{
    $signature = $request->header('X-Payment-Signature');
    $payload = $request->getContent();

    if (!hash_equals(hash_hmac('sha256', $payload, config('services.payment.secret')), $signature)) {
        abort(403, 'Invalid signature');
    }

    $event = json_decode($payload, true);
    PaymentService::processEvent($event);
}
```

- Validate webhook origin using shared secrets or asymmetric signatures.  
- Ensure idempotency by tracking processed event IDs.  
- Confirm payment status with provider before granting access.

**Solution for Threat 2: CSRF Protection Tokens**

```php
// resources/views/payments/subscribe.blade.php
<form method="POST" action="{{ route('payments.subscribe') }}">
    @csrf
    <!-- subscription fields -->
</form>
```

- Use framework-provided CSRF middleware.  
- Require POST/PUT/DELETE for state-changing endpoints.  
- Validate SameSite cookies and check `Origin`/`Referer` headers for critical requests.

## 5. Administration & Moderation Module

### 5.1 Potential Threat/Attack
- **Threat 1: SQL Injection**  
  Attackers may attempt to inject SQL queries via admin search/filter inputs to exfiltrate data.
- **Threat 2: API and Session Hijacking**  
  Session tokens or API keys might be stolen or misused to gain admin privileges.

### 5.2 Secure Coding Practice

**Solution for Threat 1: Parameterized Queries & ORM Usage**

```php
// app/Http/Controllers/Admin/UserController.php
public function index(Request $request)
{
    $query = User::query();

    if ($request->filled('email')) {
        $query->where('email', 'like', '%'.$request->input('email').'%');
    }

    return view('admin.users.index', ['users' => $query->paginate(50)]);
}
```

- Avoid raw SQL; use query builders or prepared statements.  
- Validate and normalize input before use.  
- Apply least privilege to the database credentials used by the application.

**Solution for Threat 2: Secure Session & API Key Management**

```php
// config/session.php
'regenerate_on_every_request' => true,
'cookie' => [
    'secure' => true,
    'http_only' => true,
    'same_site' => 'lax',
],

// app/Http/Middleware/AdminApiAuth.php
public function handle($request, Closure $next)
{
    $token = $request->bearerToken();
    abort_unless($token && hash_equals(cache()->get('admin_api_tokens:'.sha1($token)), 'valid'), 401);

    return $next($request);
}
```

- Rotate session IDs upon login and use secure cookies.  
- Store API tokens hashed, enforce expiration, and bind them to device/IP if possible.  
- Enable multi-factor authentication for admin accounts.  
- Monitor for anomalous session activity and revoke compromised tokens immediately.
