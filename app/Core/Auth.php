<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            self::flash('warning', 'Please log in to continue.');
            self::redirect('/login');
        }
    }

    /**
     * Guard by role(s). If not authorized: show 403 or redirect with flash.
     * @param string|array $roles Allowed role or roles, e.g. 'Employer' or ['Employer','Recruiter']
     */
    public static function requireRole(string|array $roles): void
    {
        self::requireLogin();
        $roles = (array)$roles;
        $userRole = self::role();

        if (!in_array($userRole, $roles, true)) {
            // Prefer 403 with a friendly page
            http_response_code(403);
            $root = dirname(__DIR__, 2);
            $view403 = $root . '/app/Views/errors/403.php';
            if (is_file($view403)) {
                $base = defined('BASE_URL') ? BASE_URL : '';
                require $view403;
                exit;
            }
            echo '403 Forbidden';
            exit;
        }
    }

    public static function can(string ...$roles): bool
    {
        return self::check() && in_array(self::role(), $roles, true);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    public static function redirect(string $path): void
    {
        $base = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $base . $path, true, 302);
        exit;
    }
}
