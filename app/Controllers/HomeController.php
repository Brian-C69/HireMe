<?php

declare(strict_types=1);

namespace App\Controllers;

final class HomeController
{
    public function index(array $params = []): void
    {
        $root    = dirname(__DIR__, 2);
        $title   = 'HireMe — Hiring in Malaysia, simplified.';
        $viewFile = $root . '/app/Views/home.php';
        require $root . '/app/Views/layout.php';
    }
}
