<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;

final class HomeController
{
    private function renderLayout(string $title, string $viewFile): string
    {
        $root = dirname(__DIR__, 2);
        ob_start();
        require $root . '/app/Views/layout.php';
        return (string) ob_get_clean();
    }

    public function index(Request $request): string
    {
        $root = dirname(__DIR__, 2);
        $title = 'HireMe — Hiring in Malaysia, simplified.';
        $viewFile = $root . '/app/Views/home.php';
        return $this->renderLayout($title, $viewFile);
    }

    public function privacy(Request $request): string
    {
        $root = dirname(__DIR__, 2);
        $title = 'Privacy Policy — HireMe';
        $viewFile = $root . '/app/Views/privacy.php';
        return $this->renderLayout($title, $viewFile);
    }

    public function terms(Request $request): string
    {
        $root = dirname(__DIR__, 2);
        $title = 'Terms of Service — HireMe';
        $viewFile = $root . '/app/Views/terms.php';
        return $this->renderLayout($title, $viewFile);
    }
}
