<?php
// File: app/Core/Auth.php (safe full version)
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int)($u['id'] ?? 0) : null;
    }

    public static function role(): ?string
    {
        $u = self::user();
        return $u['role'] ?? null;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            if (!isset($_SESSION['flash'])) {
                self::flash('warning', 'Please log in first.');
            }
            self::redirect('/login');
        }
    }

    /**
     * Allow only specific roles. Accepts string or array of roles.
     * Example: Auth::requireRole('Employer') or Auth::requireRole(['Employer','Recruiter'])
     */
    public static function requireRole(string|array $roles): void
    {
        if (!self::check()) {
            if (!isset($_SESSION['flash'])) {
                self::flash('warning', 'Please log in first.');
            }
            self::redirect('/login');
        }

        $roles = (array)$roles;
        $current = self::role();

        if (!in_array($current, $roles, true)) {
            http_response_code(403);
            self::flash('danger', 'You are not authorized to access this page.');
            self::redirect('/'); // or to /welcome
        }
    }

    /**
     * Back-compat helper used in your controller: Auth::requireAny(['Employer','Recruiter'])
     */
    public static function requireAny(array $roles): void
    {
        self::requireRole($roles);
    }

    /* ------------ helpers ------------ */

    private static function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    private static function redirect(string $path): void
    {
        $base = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $base . $path, true, 302);
        exit;
    }
}
