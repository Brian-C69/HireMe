<?php

declare(strict_types=1);

namespace App\Controllers;

final class HomeController
{
    public function index(array $params = []): void
    {
        $root     = dirname(__DIR__, 2);
        $title    = 'HireMe — Hiring in Malaysia, simplified.';
        $viewFile = $root . '/app/Views/home.php';
        require $root . '/app/Views/layout.php';
    }

    public function privacy(array $params = []): void
    {
        $root     = dirname(__DIR__, 2);
        $title    = 'Privacy Policy — HireMe';
        $viewFile = $root . '/app/Views/privacy.php';
        require $root . '/app/Views/layout.php';
    }

    public function terms(array $params = []): void
    {
        $root     = dirname(__DIR__, 2);
        $title    = 'Terms of Service — HireMe';
        $viewFile = $root . '/app/Views/terms.php';
        require $root . '/app/Views/layout.php';
    }
}
