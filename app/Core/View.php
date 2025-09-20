<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    public static function render(string $view, array $data = []): string
    {
        $basePath = dirname(__DIR__) . '/Views/';
        $viewPath = $basePath . ltrim($view, '/') . '.php';

        if (!is_file($viewPath)) {
            throw new RuntimeException(sprintf('View "%s" not found.', $view));
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewPath;
        return (string) ob_get_clean();
    }
}
