<?php

namespace App\Core;

use App\Core\Contracts\Middleware as MiddlewareContract;
use Closure;
use RuntimeException;

class Router
{
    /** @var array<int, array<string, mixed>> */
    private array $routes = [];

    /** @var array<int, array<string, mixed>> */
    private array $groupStack = [];

    /** @var array<string, array<string, mixed>> */
    private array $namedRoutes = [];

    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $this->mergeGroupAttributes($attributes);
        $callback($this);
        array_pop($this->groupStack);
    }

    public function get(string $uri, callable|array|string $action, array $options = []): void
    {
        $this->addRoute(['GET'], $uri, $action, $options);
    }

    public function post(string $uri, callable|array|string $action, array $options = []): void
    {
        $this->addRoute(['POST'], $uri, $action, $options);
    }

    public function put(string $uri, callable|array|string $action, array $options = []): void
    {
        $this->addRoute(['PUT'], $uri, $action, $options);
    }

    public function patch(string $uri, callable|array|string $action, array $options = []): void
    {
        $this->addRoute(['PATCH'], $uri, $action, $options);
    }

    public function delete(string $uri, callable|array|string $action, array $options = []): void
    {
        $this->addRoute(['DELETE'], $uri, $action, $options);
    }

    public function any(string $uri, callable|array|string $action, array $options = []): void
    {
        $this->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $uri, $action, $options);
    }

    public function addRoute(array $methods, string $uri, callable|array|string $action, array $options = []): void
    {
        $uri = '/' . ltrim($uri, '/');
        $attributes = $this->mergeGroupAttributes($options);

        $route = [
            'methods' => array_unique(array_map('strtoupper', $methods)),
            'uri' => $this->prependGroupPrefix($uri),
            'action' => $action,
            'middleware' => $this->normalizeMiddleware($attributes['middleware'] ?? []),
            'name' => $this->prependNamePrefix($attributes['as'] ?? null),
            'json' => $attributes['json'] ?? false,
        ];

        $this->routes[] = $route;

        if ($route['name']) {
            $this->namedRoutes[$route['name']] = $route;
        }
    }

    public function dispatch(Request $request, Container $container): Response
    {
        $method = $request->method();
        $path = rtrim($request->uri(), '/') ?: '/';

        foreach ($this->routes as $route) {
            if (!in_array($method, $route['methods'], true)) {
                continue;
            }

            $pattern = $this->compileRoute($route['uri']);
            if (preg_match($pattern, $path, $matches)) {
                $parameters = $this->extractParameters($matches);
                return $this->runRoute($route, $parameters, $request, $container);
            }
        }

        if ($request->expectsJson()) {
            return Response::json(['status' => 'error', 'message' => 'Route not found.'], 404);
        }

        return new Response(View::render('errors/404', ['uri' => $path]), 404);
    }

    public function url(string $name, array $parameters = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new RuntimeException(sprintf('Route "%s" is not defined.', $name));
        }

        $uri = $this->namedRoutes[$name]['uri'];
        foreach ($parameters as $key => $value) {
            $uri = str_replace('{' . $key . '}', $value, $uri);
        }

        return $uri;
    }

    private function runRoute(array $route, array $parameters, Request $request, Container $container): Response
    {
        $response = $this->runMiddlewarePipeline($route['middleware'], $request, function (Request $request) use ($route, $parameters, $container) {
            $callable = $this->resolveAction($route['action'], $container);
            $result = $callable($request, ...array_values($parameters));
            return $this->prepareResponse($result, $request, $route);
        }, $container);

        return $response;
    }

    private function runMiddlewarePipeline(array $middlewares, Request $request, callable $destination, Container $container): Response
    {
        $pipeline = array_reduce(
            array_reverse($middlewares),
            function ($next, $middleware) use ($container) {
                return function (Request $request) use ($next, $middleware, $container) {
                    $parameters = [];
                    if (is_string($middleware)) {
                        if (str_contains($middleware, ':')) {
                            [$middlewareClass, $parameterString] = explode(':', $middleware, 2);
                            $middleware = $middlewareClass;
                            $parameters = $parameterString !== '' ? explode(',', $parameterString) : [];
                        }
                        $instance = $container->make($middleware);
                        if (method_exists($instance, 'setParameters')) {
                            $instance->setParameters($parameters);
                        }
                    } else {
                        $instance = $middleware;
                    }

                    if ($instance instanceof MiddlewareContract) {
                        return $instance->handle($request, $next);
                    }

                    if (is_callable($instance)) {
                        return $instance($request, $next);
                    }

                    throw new RuntimeException('Invalid middleware provided.');
                };
            },
            $destination
        );

        return $pipeline($request);
    }

    private function prepareResponse(mixed $result, Request $request, array $route): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        if (is_string($result)) {
            return new Response($result);
        }

        if ($result === null) {
            return new Response('', 204);
        }

        if ($route['json'] || $request->expectsJson()) {
            return Response::json(['data' => $result]);
        }

        return new Response((string) $result);
    }

    private function resolveAction(callable|array|string $action, Container $container): callable
    {
        if (is_callable($action)) {
            return $action;
        }

        if (is_array($action) && count($action) === 2) {
            [$class, $method] = $action;
            $controller = is_string($class) ? $container->make($class) : $class;
            if ($controller instanceof Controller) {
                $controller->setContainer($container);
            }
            return [$controller, $method];
        }

        if (is_string($action) && str_contains($action, '@')) {
            [$class, $method] = explode('@', $action);
            $controller = $container->make($class);
            if ($controller instanceof Controller) {
                $controller->setContainer($container);
            }

            return [$controller, $method];
        }

        throw new RuntimeException('Invalid route action.');
    }

    private function compileRoute(string $uri): string
    {
        $pattern = preg_replace('#\{([^}/]+)\}#', '(?P<$1>[^/]+)', rtrim($uri, '/') ?: '/');
        return '#^' . $pattern . '$#';
    }

    private function extractParameters(array $matches): array
    {
        $parameters = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $parameters[$key] = $value;
            }
        }

        return $parameters;
    }

    private function mergeGroupAttributes(array $attributes): array
    {
        $stacked = $this->groupStack;
        $merged = ['middleware' => [], 'prefix' => '', 'as' => null, 'json' => false];

        foreach ($stacked as $group) {
            $merged['middleware'] = array_merge(
                $merged['middleware'],
                $this->normalizeMiddleware($group['middleware'] ?? [])
            );
            $merged['prefix'] .= $group['prefix'] ?? '';
            $merged['as'] = $this->concatenateName($merged['as'], $group['as'] ?? null);
            $merged['json'] = $merged['json'] || ($group['json'] ?? false);
        }

        $merged['middleware'] = array_merge(
            $merged['middleware'],
            $this->normalizeMiddleware($attributes['middleware'] ?? [])
        );
        if (isset($attributes['prefix'])) {
            $merged['prefix'] .= $attributes['prefix'];
        }
        $merged['as'] = $this->concatenateName($merged['as'], $attributes['as'] ?? null);
        $merged['json'] = $merged['json'] || ($attributes['json'] ?? false);
        $merged['middleware'] = $this->uniqueMiddleware($merged['middleware']);

        return $merged;
    }

    private function prependGroupPrefix(string $uri): string
    {
        $prefix = '';
        foreach ($this->groupStack as $group) {
            if (!empty($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
        }

        return rtrim('/' . trim($prefix . '/' . ltrim($uri, '/'), '/'), '/') ?: '/';
    }

    private function prependNamePrefix(?string $name): ?string
    {
        $prefix = '';
        foreach ($this->groupStack as $group) {
            if (!empty($group['as'])) {
                $prefix .= $group['as'];
            }
        }

        if ($name === null) {
            return $prefix ?: null;
        }

        return $prefix . $name;
    }

    private function concatenateName(?string $base, ?string $append): ?string
    {
        if (!$base) {
            return $append;
        }

        if (!$append) {
            return $base;
        }

        return $base . $append;
    }

    private function normalizeMiddleware(mixed $middleware): array
    {
        if ($middleware === null || $middleware === []) {
            return [];
        }

        if (is_string($middleware)) {
            return [$middleware];
        }

        if (!is_array($middleware)) {
            return [$middleware];
        }

        $normalized = [];
        foreach ($middleware as $item) {
            $normalized = array_merge($normalized, $this->normalizeMiddleware($item));
        }

        return $normalized;
    }

    private function uniqueMiddleware(array $middleware): array
    {
        $unique = [];
        $seenStrings = [];

        foreach ($middleware as $item) {
            if (is_string($item)) {
                if (isset($seenStrings[$item])) {
                    continue;
                }
                $seenStrings[$item] = true;
            }

            $unique[] = $item;
        }

        return $unique;
    }
}
