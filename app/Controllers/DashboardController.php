<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use PDO;

final class DashboardController
{
    private function redirect(string $path): void
    {
        $base = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $base . $path, true, 302);
        exit;
    }

    private function requireAuth(): array
    {
        if (empty($_SESSION['user'])) {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Please log in to continue.'];
            $this->redirect('/login');
        }
        return $_SESSION['user'];
    }

    private function displayName(PDO $pdo, array $user): string
    {
        $email = $user['email'] ?? '';
        $role  = $user['role']  ?? '';

        if ($role === 'Candidate') {
            $q = $pdo->prepare("SELECT full_name FROM candidates WHERE email=:e LIMIT 1");
            $q->execute([':e' => $email]);
            return (string)($q->fetchColumn() ?: $email);
        }
        if ($role === 'Employer') {
            // Prefer contact person, fallback to company name
            $q = $pdo->prepare("SELECT COALESCE(NULLIF(contact_person_name,''), company_name) FROM employers WHERE email=:e LIMIT 1");
            $q->execute([':e' => $email]);
            return (string)($q->fetchColumn() ?: $email);
        }
        if ($role === 'Recruiter') {
            $q = $pdo->prepare("SELECT full_name FROM recruiters WHERE email=:e LIMIT 1");
            $q->execute([':e' => $email]);
            return (string)($q->fetchColumn() ?: $email);
        }
        return $email;
    }

    public function welcome(array $params = []): void
    {
        $auth = $this->requireAuth();
        $pdo  = DB::conn();

        $root     = dirname(__DIR__, 2);
        $title    = 'Welcome — HireMe';
        $viewFile = $root . '/app/Views/dashboard.php';

        // data for the view
        $role        = $auth['role'] ?? '';
        $email       = $auth['email'] ?? '';
        $displayName = $this->displayName($pdo, $auth);

        require $root . '/app/Views/layout.php';
    }
}
