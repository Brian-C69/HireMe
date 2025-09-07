<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array<int, array{pattern:string, handler: callable|array}>> */
    private array $routes = ['GET' => [], 'POST' => []];

    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }
    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, callable|array $handler): void
    {
        $pattern = $this->compile($path);
        $this->routes[$method][] = ['pattern' => $pattern, 'handler' => $handler];
    }

    private function compile(string $path): string
    {
        if ($path === '/' || $path === '') return '#^/$#';
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $path);
        $regex = rtrim($regex, '/');
        return '#^' . $regex . '/?$#';
    }

    public function dispatch(string $method, string $path): void
    {
        $path = rtrim($path, '/') ?: '/';
        foreach ($this->routes[$method] ?? [] as $r) {
            if (preg_match($r['pattern'], $path, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->invoke($r['handler'], $params);
                return;
            }
        }

        http_response_code(404);
        $root    = dirname(__DIR__, 2);
        $view404 = $root . '/app/Views/errors/404.php';
        if (is_file($view404)) {
            // Make BASE_URL available if view wants it
            $baseUrl = defined('BASE_URL') ? BASE_URL : '';
            require $view404;
            return;
        }
        echo '404 Not Found';
    }

    private function invoke(callable|array $handler, array $params): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $obj = new $class();
            $obj->$method($params);
            return;
        }
        $handler($params);
    }
}
