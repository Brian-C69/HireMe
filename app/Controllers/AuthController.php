<?php
// =====================================================================
// app/Controllers/AuthController.php  (FULL UPDATED CLASS)
// ---------------------------------------------------------------------
// Includes: DB-backed login, register (prev), + forgot/reset flow using
// PHPMailer wrapper. Field errors + old input preserved.
// =====================================================================
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Core\Mailer;
use PDO;
use Throwable;

final class AuthController
{
    private const MAX_ATTEMPTS = 3;
    private const LOCK_MINUTES = 15;
    private const ROLES        = ['Candidate', 'Employer', 'Recruiter'];

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

        if ($this->isLockedOut($email, $ip)) {
            $this->flash('danger', 'Too many failed attempts. Please reset your password or try again later.');
            $this->setOld(['email' => $email]);
            $this->redirect('/login');
        }

        $pdo  = DB::conn();
        $user = $this->findUserByEmail($pdo, $email); // must return: id, email, password_hash, role
        if (!$user || !password_verify($pass, (string)$user['password_hash'])) {
            $this->recordFailure($email, $ip);
            $left = max(0, self::MAX_ATTEMPTS - $this->attemptCount($email, $ip));
            $this->setErrors([
                'email'    => 'Invalid email or password.',
                'password' => $left > 0 ? "You have {$left} attempt(s) left." : 'Limit reached. Please reset your password.',
            ]);
            $this->setOld(['email' => $email]);
            $this->redirect('/login');
        }

        // ---- NEW: fetch role-specific meta for the session (name/premium/verified) ----
        $name = '';
        $premium = 0;
        $verified = 0;

        switch ((string)$user['role']) {
            case 'Admin':
                $st = $pdo->prepare("SELECT full_name FROM admins WHERE admin_id = :id LIMIT 1");
                $st->execute([':id' => (int)$user['id']]);
                if ($row = $st->fetch()) $name = (string)($row['full_name'] ?? '');
                break;

            case 'Candidate':
                $st = $pdo->prepare("SELECT full_name, premium_badge, verified_status FROM candidates WHERE candidate_id = :id LIMIT 1");
                $st->execute([':id' => (int)$user['id']]);
                if ($row = $st->fetch()) {
                    $name     = (string)($row['full_name'] ?? '');
                    $premium  = (int)($row['premium_badge'] ?? 0);
                    $verified = (int)($row['verified_status'] ?? 0);
                }
                break;

            case 'Employer':
                $st = $pdo->prepare("SELECT company_name FROM employers WHERE employer_id = :id LIMIT 1");
                $st->execute([':id' => (int)$user['id']]);
                if ($row = $st->fetch()) {
                    $name = (string)($row['company_name'] ?? '');
                }
                break;

            case 'Recruiter':
                $st = $pdo->prepare("SELECT full_name FROM recruiters WHERE recruiter_id = :id LIMIT 1");
                $st->execute([':id' => (int)$user['id']]);
                if ($row = $st->fetch()) {
                    $name = (string)($row['full_name'] ?? '');
                }
                break;
        }

        // Success
        $this->resetAttempts($email, $ip);
        $_SESSION['user'] = [
            'id'              => (int)$user['id'],
            'email'           => (string)$user['email'],
            'role'            => (string)$user['role'],
            'name'            => $name,
            'premium_badge'   => $premium,   // <- used by navbar ⭐ chip
            'verified_status' => $verified,  // <- optional "verified" chip
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

    private function findUserByEmail(PDO $pdo, string $email): ?array
    {
        foreach (
            [
                ["SELECT admin_id     AS id, email, password_hash, 'Admin'     AS role FROM admins     WHERE email=:e LIMIT 1"],
                ["SELECT candidate_id AS id, email, password_hash, 'Candidate' AS role FROM candidates WHERE email=:e LIMIT 1"],
                ["SELECT employer_id AS id, email, password_hash, 'Employer'  AS role FROM employers  WHERE email=:e LIMIT 1"],
                ["SELECT recruiter_id AS id, email, password_hash, 'Recruiter' AS role FROM recruiters WHERE email=:e LIMIT 1"],
            ] as $sql
        ) {
            $st = $pdo->prepare($sql[0]);
            $st->execute([':e' => $email]);
            $row = $st->fetch();
            if ($row) return $row;
        }
        return null;
    }

    /* ------------ Lockout helpers ------------ */
    private function isLockedOut(string $email, string $ip): bool
    {
        $pdo = DB::conn();
        $st = $pdo->prepare("SELECT attempts,last_attempt_at FROM login_attempts WHERE email=:e AND ip_address=:i LIMIT 1");
        $st->execute([':e' => $email, ':i' => $ip]);
        $row = $st->fetch();
        if (!$row) return false;
        if ((int)$row['attempts'] < self::MAX_ATTEMPTS) return false;
        return (time() - strtotime((string)$row['last_attempt_at'])) < self::LOCK_MINUTES * 60;
    }
    private function attemptCount(string $email, string $ip): int
    {
        $pdo = DB::conn();
        $st = $pdo->prepare("SELECT attempts FROM login_attempts WHERE email=:e AND ip_address=:i LIMIT 1");
        $st->execute([':e' => $email, ':i' => $ip]);
        return (int)($st->fetchColumn() ?: 0);
    }
    private function recordFailure(string $email, string $ip): void
    {
        $pdo = DB::conn();
        $st = $pdo->prepare("INSERT INTO login_attempts (email, ip_address, attempts, last_attempt_at) VALUES (:e,:i,1,NOW()) ON DUPLICATE KEY UPDATE attempts=attempts+1,last_attempt_at=NOW()");
        $st->execute([':e' => $email, ':i' => $ip]);
    }
    private function resetAttempts(string $email, string $ip): void
    {
        $pdo = DB::conn();
        $st = $pdo->prepare("DELETE FROM login_attempts WHERE email=:e AND ip_address=:i");
        $st->execute([':e' => $email, ':i' => $ip]);
    }

    /* ------------ Register (kept from before; field errors + old) ------------ */
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
        if ($this->emailExistsAny($pdo, $email)) {
            $this->setErrors(['email' => 'Email already registered.']);
            $this->setOld($old);
            $this->redirect('/register');
        }
        $now = date('Y-m-d H:i:s');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            if ($role === 'Candidate') {
                $st = $pdo->prepare("INSERT INTO candidates (full_name,email,password_hash,phone_number,country,created_at,updated_at) VALUES (:n,:e,:p,:ph,:c,:ca,:ua)");
                $st->execute([':n' => $fullName, ':e' => $email, ':p' => $hash, ':ph' => $phone ?: null, ':c' => 'Malaysia', ':ca' => $now, ':ua' => $now]);
            } elseif ($role === 'Employer') {
                $st = $pdo->prepare("INSERT INTO employers (company_name,email,password_hash,industry,location,contact_person_name,contact_number,created_at,updated_at) VALUES (:cn,:e,:p,:i,:l,:cp,:cc,:ca,:ua)");
                $st->execute([':cn' => $companyName, ':e' => $email, ':p' => $hash, ':i' => null, ':l' => $location ?: null, ':cp' => $fullName ?: null, ':cc' => $phone ?: null, ':ca' => $now, ':ua' => $now]);
            } else {
                $st = $pdo->prepare("INSERT INTO recruiters (full_name,email,password_hash,agency_name,contact_number,location,created_at,updated_at) VALUES (:n,:e,:p,:a,:c,:l,:ca,:ua)");
                $st->execute([':n' => $fullName, ':e' => $email, ':p' => $hash, ':a' => $agencyName ?: null, ':c' => $phone ?: null, ':l' => $location ?: null, ':ca' => $now, ':ua' => $now]);
            }
        } catch (Throwable) {
            $this->flash('danger', 'Could not register. Please check your inputs.');
            $this->setOld($old);
            $this->redirect('/register');
        }
        $this->flash('success', 'Registration successful. Please log in.');
        $this->redirect('/login');
    }

    private function emailExistsAny(PDO $pdo, string $email): bool
    {
        foreach (['candidates', 'employers', 'recruiters'] as $t) {
            $q = $pdo->prepare("SELECT 1 FROM {$t} WHERE email=:e LIMIT 1");
            $q->execute([':e' => $email]);
            if ($q->fetchColumn()) return true;
        }
        return false;
    }

    /* ------------ Forgot / Reset ------------ */

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
        $user = $this->findUserByEmail($pdo, $email);
        // Always respond success to avoid email enumeration
        $this->flash('success', 'If that email exists, a reset link has been sent.');

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);
            $expires = date('Y-m-d H:i:s', time() + 60 * 60); // 60 minutes
            // invalidate previous tokens for this email
            $pdo->prepare("DELETE FROM password_resets WHERE email=:e OR expires_at < NOW() OR used_at IS NOT NULL")->execute([':e' => $email]);
            $st = $pdo->prepare("INSERT INTO password_resets (email,user_type,token_hash,expires_at) VALUES (:e,:t,:h,:x)");
            $st->execute([':e' => $email, ':t' => $user['role'], ':h' => $hash, ':x' => $expires]);

            // send email
            $url = $this->absoluteBase() . '/reset?token=' . urlencode($token);
            $html = '<p>Hello,</p><p>Click the button below to reset your HireMe password. This link expires in 60 minutes.</p>'
                . '<p><a href="' . $url . '" style="display:inline-block;background:#0d6efd;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;">Reset Password</a></p>'
                . '<p>If the button doesn’t work, copy and paste this link:<br><a href="' . $url . '">' . $url . '</a></p>';
            $text = "Reset your HireMe password: $url (valid 60 minutes)";

            try {
                (new Mailer())->send($email, 'Reset your HireMe password', $html, $text);
            } catch (Throwable) { /* silent: still show success */
            }
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
        // Pass token forward (plain token, never hash)
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

        // Update password in the correct table
        $email = $row['email'];
        $role = $row['user_type'];
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        if ($role === 'Admin') {
            $q = $pdo->prepare("UPDATE admins SET password_hash=:p, updated_at=NOW() WHERE email=:e LIMIT 1");
        } elseif ($role === 'Candidate') {
            $q = $pdo->prepare("UPDATE candidates SET password_hash=:p, updated_at=NOW() WHERE email=:e LIMIT 1");
        } elseif ($role === 'Employer') {
            $q = $pdo->prepare("UPDATE employers SET password_hash=:p, updated_at=NOW() WHERE email=:e LIMIT 1");
        } else {
            $q = $pdo->prepare("UPDATE recruiters SET password_hash=:p, updated_at=NOW() WHERE email=:e LIMIT 1");
        }
        $q->execute([':p' => $newHash, ':e' => $email]);

        // Mark token used and remove any other outstanding tokens for this email
        $pdo->prepare("UPDATE password_resets SET used_at=NOW() WHERE token_hash=:h")->execute([':h' => $hash]);
        $pdo->prepare("DELETE FROM password_resets WHERE email=:e AND used_at IS NULL")->execute([':e' => $email]);

        $this->flash('success', 'Password updated. Please log in.');
        $this->redirect('/login');
    }
}
