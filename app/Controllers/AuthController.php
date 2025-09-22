<?php
// app/Controllers/AuthController.php  (REFACTORED using Strategy + Factory)

// Uses: App\Auth\UserProviderFactory + App\Services\AttemptService
// Keeps original behaviour: login, register, forgot/reset, CSRF, flash, old/errors.
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Core\Mailer;
use App\Controllers\Auth\UserProviderFactory;
use App\Services\AttemptService;
use PDO;
use Throwable;

final class AuthController
{
    private const ROLES = ['Candidate', 'Employer', 'Recruiter'];

    /* ------------ Helpers ------------ */
    private function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
        return $_SESSION['csrf'];
    }
    private function verifyCsrf(?string $t): bool
    {
        return isset($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t);
    }
    private function flash(string $type, string $msg): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $msg];
    }
    private function setErrors(array $errs): void
    {
        $_SESSION['errors'] = $errs;
    }
    private function takeErrors(): array
    {
        $e = $_SESSION['errors'] ?? [];
        unset($_SESSION['errors']);
        return $e;
    }
    private function setOld(array $old): void
    {
        $_SESSION['old'] = $old;
    }
    private function takeOld(): array
    {
        $o = $_SESSION['old'] ?? [];
        unset($_SESSION['old']);
        return $o;
    }
    private function redirect(string $path): void
    {
        $base = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $base . $path, true, 302);
        exit;
    }

    private function absoluteBase(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = defined('BASE_URL') ? BASE_URL : '';
        return rtrim($scheme . '://' . $host . $base, '/');
    }

    /* ------------ LOGIN (DB-backed) ------------ */
    public function showLogin(array $params = []): void
    {
        $root = dirname(__DIR__, 2);
        $title = 'Login — HireMe';
        $viewFile = $root . '/app/Views/auth/login.php';
        $csrf = $this->csrfToken();
        $errors = $this->takeErrors();
        $old = $this->takeOld();
        require $root . '/app/Views/layout.php';
    }

    public function doLogin(array $params = []): void
    {
        if (!$this->verifyCsrf($_POST['csrf'] ?? null)) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/login');
        }

        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass  = (string)($_POST['password'] ?? '');
        $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $err = [];
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err['email'] = 'Enter a valid email.';
        if ($pass === '') $err['password'] = 'Password is required.';
        if ($err) {
            $this->setErrors($err);
            $this->setOld(['email' => $email]);
            $this->redirect('/login');
        }

        $pdo = DB::conn();
        $attemptSvc = new AttemptService();

        // check lockout using AttemptService
        if ($attemptSvc->isLockedOut($pdo, $email, $ip)) {
            $this->flash('danger', 'Too many failed attempts. Please reset your password or try again later.');
            $this->setOld(['email' => $email]);
            $this->redirect('/login');
        }

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
        $name = (string)($meta['name'] ?? '');
        $premium = (int)($meta['premium_badge'] ?? 0);
        $verified = (int)($meta['verified_status'] ?? 0);

        $attemptSvc->resetAttempts($pdo, $email, $ip);

        $_SESSION['user'] = [
            'id'              => (int)$user['id'],
            'email'           => (string)$user['email'],
            'role'            => (string)$user['role'],
            'name'            => $name,
            'premium_badge'   => $premium,
            'verified_status' => $verified,
            'time'            => time(),
        ];

        // rotate CSRF
        $_SESSION['csrf'] = bin2hex(random_bytes(16));

        $this->flash('success', 'Welcome back, ' . $user['role'] . '!');
        $this->redirect('/welcome');
    }


    public function logout(array $params = []): void
    {
        unset($_SESSION['user']);
        $this->flash('success', 'You have been logged out.');
        $this->redirect('/');
    }

    /* ------------ Register (delegated to providers) ------------ */
    public function showRegister(array $params = []): void
    {
        $root = dirname(__DIR__, 2);
        $title = 'Register — HireMe';
        $viewFile = $root . '/app/Views/auth/register.php';
        $csrf = $this->csrfToken();
        $errors = $this->takeErrors();
        $old = $this->takeOld();
        require $root . '/app/Views/layout.php';
    }

    public function doRegister(array $params = []): void
    {
        if (!$this->verifyCsrf($_POST['csrf'] ?? null)) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/register');
        }
        $role = trim((string)($_POST['role'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $companyName = trim((string)($_POST['company_name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        $agencyName = trim((string)($_POST['agency_name'] ?? ''));
        $old = compact('role', 'email', 'fullName', 'companyName', 'phone', 'location', 'agencyName');

        $err = [];
        if (!in_array($role, self::ROLES, true)) $err['role'] = 'Select a valid role.';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err['email'] = 'Provide a valid email.';
        if ($password === '' || strlen($password) < 6) $err['password'] = 'Min 6 characters.';
        if ($password !== $confirm) $err['password_confirm'] = 'Passwords do not match.';
        if ($role === 'Candidate' && $fullName === '') $err['full_name'] = 'Full name is required.';
        if ($role === 'Recruiter' && $fullName === '') $err['full_name'] = 'Full name is required.';
        if ($role === 'Employer' && $companyName === '') $err['company_name'] = 'Company name is required.';
        if ($err) {
            $this->setErrors($err);
            $this->setOld($old);
            $this->redirect('/register');
        }

        $pdo = DB::conn();

        // check existing email (factory helper)
        if (UserProviderFactory::emailExistsAny($pdo, $email)) {
            $this->setErrors(['email' => 'Email already registered.']);
            $this->setOld($old);
            $this->redirect('/register');
        }

        // delegate insert to provider
        $provider = UserProviderFactory::providerForRole($role);
        if (!$provider) {
            $this->flash('danger', 'Invalid role selected.');
            $this->setOld($old);
            $this->redirect('/register');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $payload = [
            'email' => $email,
            'password_hash' => $hash,
            'full_name' => $fullName,
            'company_name' => $companyName,
            'phone' => $phone,
            'location' => $location,
            'agency_name' => $agencyName,
        ];

        try {
            $ok = $provider->create($pdo, $payload);
            if (!$ok) throw new \RuntimeException('Could not create user.');
        } catch (Throwable) {
            $this->flash('danger', 'Could not register. Please check your inputs.');
            $this->setOld($old);
            $this->redirect('/register');
        }

        $this->flash('success', 'Registration successful. Please log in.');
        $this->redirect('/login');
    }

    /* ------------ Forgot / Reset (use factory) ------------ */

    public function showForgot(array $params = []): void
    {
        $root = dirname(__DIR__, 2);
        $title = 'Forgot Password — HireMe';
        $viewFile = $root . '/app/Views/auth/forgot.php';
        $csrf = $this->csrfToken();
        $errors = $this->takeErrors();
        $old = $this->takeOld();
        require $root . '/app/Views/layout.php';
    }

    public function sendReset(array $params = []): void
    {
        if (!$this->verifyCsrf($_POST['csrf'] ?? null)) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/forgot');
        }
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $err = [];
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err['email'] = 'Enter a valid email.';
        if ($err) {
            $this->setErrors($err);
            $this->setOld(['email' => $email]);
            $this->redirect('/forgot');
        }

        $pdo = DB::conn();
        // still avoid enumeration
        $this->flash('success', 'If that email exists, a reset link has been sent.');

        $found = UserProviderFactory::findByEmail($pdo, $email);
        if ($found) {
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);
            $expires = date('Y-m-d H:i:s', time() + 60 * 60); // 60 minutes

            // invalidate previous tokens
            $pdo->prepare("DELETE FROM password_resets WHERE email=:e OR expires_at < NOW() OR used_at IS NOT NULL")->execute([':e' => $email]);
            $st = $pdo->prepare("INSERT INTO password_resets (email,user_type,token_hash,expires_at) VALUES (:e,:t,:h,:x)");
            $st->execute([':e' => $email, ':t' => $found['user']['role'], ':h' => $hash, ':x' => $expires]);

            // send email
            $url = $this->absoluteBase() . '/reset?token=' . urlencode($token);
            $html = '<p>Hello,</p><p>Click the button below to reset your HireMe password. This link expires in 60 minutes.</p>'
                . '<p><a href="' . $url . '" style="display:inline-block;background:#0d6efd;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;">Reset Password</a></p>'
                . '<p>If the button doesn’t work, copy and paste this link:<br><a href="' . $url . '">' . $url . '</a></p>';
            $text = "Reset your HireMe password: $url (valid 60 minutes)";

            try {
                (new Mailer())->send($email, 'Reset your HireMe password', $html, $text);
            } catch (Throwable) { /* silent */ }
        }

        $this->redirect('/forgot');
    }

    public function showReset(array $params = []): void
    {
        $token = (string)($_GET['token'] ?? '');
        if ($token === '') {
            $this->flash('danger', 'Invalid or expired reset link.');
            $this->redirect('/forgot');
        }

        $hash  = hash('sha256', $token);
        $pdo   = DB::conn();
        $st = $pdo->prepare("SELECT email,user_type,expires_at,used_at FROM password_resets WHERE token_hash=:h LIMIT 1");
        $st->execute([':h' => $hash]);
        $row = $st->fetch();

        if (!$row || $row['used_at'] !== null || strtotime((string)$row['expires_at']) < time()) {
            $this->flash('danger', 'Invalid or expired reset link.');
            $this->redirect('/forgot');
        }

        $root = dirname(__DIR__, 2);
        $title = 'Reset Password — HireMe';
        $viewFile = $root . '/app/Views/auth/reset.php';
        $csrf = $this->csrfToken();
        $errors = $this->takeErrors();
        $old = $this->takeOld();
        $resetToken = $token;
        require $root . '/app/Views/layout.php';
    }

    public function processReset(array $params = []): void
    {
        if (!$this->verifyCsrf($_POST['csrf'] ?? null)) {
            $this->flash('danger', 'Invalid session.');
            $this->redirect('/forgot');
        }
        $token = (string)($_POST['token'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');
        $err = [];
        if ($token === '') $err['token'] = 'Invalid token.';
        if ($password === '' || strlen($password) < 6) $err['password'] = 'Min 6 characters.';
        if ($password !== $confirm) $err['password_confirm'] = 'Passwords do not match.';
        if ($err) {
            $this->setErrors($err);
            $this->setOld([]);
            $this->redirect('/reset?token=' . urlencode($token));
        }

        $hash = hash('sha256', $token);
        $pdo = DB::conn();
        $st = $pdo->prepare("SELECT email,user_type,expires_at,used_at FROM password_resets WHERE token_hash=:h LIMIT 1");
        $st->execute([':h' => $hash]);
        $row = $st->fetch();
        if (!$row || $row['used_at'] !== null || strtotime((string)$row['expires_at']) < time()) {
            $this->flash('danger', 'Invalid or expired reset link.');
            $this->redirect('/forgot');
        }

        $email = $row['email'];
        $role = $row['user_type'];
        $newHash = password_hash($password, PASSWORD_DEFAULT);

        $provider = UserProviderFactory::providerForRole($role);
        if ($provider === null) {
            $this->flash('danger', 'Invalid user type.');
            $this->redirect('/forgot');
        }

        $provider->updatePassword($pdo, $email, $newHash);

        // mark token used and remove other outstanding tokens
        $pdo->prepare("UPDATE password_resets SET used_at=NOW() WHERE token_hash=:h")->execute([':h' => $hash]);
        $pdo->prepare("DELETE FROM password_resets WHERE email=:e AND used_at IS NULL")->execute([':e' => $email]);

        $this->flash('success', 'Password updated. Please log in.');
        $this->redirect('/login');
    }
}
