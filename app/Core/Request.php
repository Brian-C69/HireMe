<?php

declare(strict_types=1);

namespace App\Core;

use JsonException;

final class Request
{
    private array $query;
    private array $body;
    private array $cookies;
    private array $files;
    private array $server;
    private array $headers;
    private string $method;
    private string $path;
    private ?string $rawBody;
    private ?array $jsonCache = null;
    private ?string $basePath;

    public function __construct(
        array $query = [],
        array $body = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $rawBody = null,
        ?string $basePath = null
    ) {
        $this->query = $query;
        $this->body = $body;
        $this->cookies = $cookies;
        $this->files = $files;
        $this->server = $server;
        $this->rawBody = $rawBody;
        $this->basePath = $basePath !== null ? rtrim($basePath, '/') : null;

        $this->method = strtoupper($server['REQUEST_METHOD'] ?? 'GET');
        $this->headers = $this->parseHeaders($server);
        $this->path = $this->normalisePath($server['REQUEST_URI'] ?? '/', $this->basePath);
    }

    public static function fromGlobals(?string $basePath = null): self
    {
        $input = file_get_contents('php://input');
        return new self(
            $_GET,
            $_POST,
            $_COOKIE,
            $_FILES,
            $_SERVER,
            $input === false ? null : $input,
            $basePath
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->path;
    }

    public function basePath(): ?string
    {
        return $this->basePath;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        $data = $this->all();
        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? $default;
    }

    public function all(): array
    {
        return array_replace_recursive($this->query, $this->body, $this->json() ?? []);
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        $decoded = $this->decodeJson();
        if ($decoded === null) {
            return $key === null ? null : $default;
        }

        if ($key === null) {
            return $decoded;
        }

        return $decoded[$key] ?? $default;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function file(string $key, mixed $default = null): mixed
    {
        return $this->files[$key] ?? $default;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        $key = strtoupper($key);
        return $this->server[$key] ?? $default;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        $normalized = $this->normaliseHeaderName($name);
        return $this->headers[$normalized] ?? $default;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization');
        if (!is_string($header)) {
            return null;
        }

        if (preg_match('/Bearer\\s+(.*)$/i', $header, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    public function expectsJson(): bool
    {
        $accept = (string) $this->header('Accept', '');
        if ($accept !== '' && str_contains(strtolower($accept), 'json')) {
            return true;
        }

        $requestedWith = (string) $this->header('X-Requested-With', '');
        if (strcasecmp($requestedWith, 'XMLHttpRequest') === 0) {
            return true;
        }

        $contentType = (string) $this->header('Content-Type', '');
        return str_contains(strtolower($contentType), 'json');
    }

    public function isAjax(): bool
    {
        return strcasecmp((string) $this->header('X-Requested-With', ''), 'XMLHttpRequest') === 0;
    }

    public function ip(): ?string
    {
        foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $value = $this->server[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $parts = explode(',', $value);
                return trim($parts[0]);
            }
        }

        return null;
    }

    public function userAgent(): ?string
    {
        $agent = $this->server['HTTP_USER_AGENT'] ?? null;
        return is_string($agent) ? $agent : null;
    }

    public function rawBody(): ?string
    {
        return $this->rawBody;
    }

    private function decodeJson(): ?array
    {
        if ($this->jsonCache !== null) {
            return $this->jsonCache;
        }

        $contentType = strtolower((string) $this->header('Content-Type', ''));
        if ($this->rawBody === null || $this->rawBody === '' || !str_contains($contentType, 'json')) {
            $this->jsonCache = [];
            return $this->jsonCache;
        }

        try {
            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($this->rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->jsonCache = [];
            return $this->jsonCache;
        }

        $this->jsonCache = $decoded ?? [];
        return $this->jsonCache;
    }

    private function parseHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $headers[$this->normaliseHeaderName(substr($key, 5))] = $value;
                continue;
            }

            if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $headers[$this->normaliseHeaderName($key)] = $value;
            }
        }

        return $headers;
    }

    private function normaliseHeaderName(string $name): string
    {
        $name = str_replace('-', '_', $name);
        $name = preg_replace('/[^A-Za-z0-9_]/', '', $name) ?? $name;
        $segments = explode('_', strtolower($name));
        $segments = array_map(static fn (string $segment) => ucfirst($segment), $segments);
        return implode('-', $segments);
    }

    private function normalisePath(string $uri, ?string $basePath): string
    {
        $path = str_replace('\\', '/', parse_url($uri, PHP_URL_PATH) ?? '/');
        $path = $path !== '' ? $path : '/';

        if ($basePath) {
            $basePath = '/' . ltrim($basePath, '/');
            if (str_starts_with($path, $basePath)) {
                $path = substr($path, strlen($basePath)) ?: '/';
            }
        }

        $path = rtrim($path, '/') ?: '/';
        if ($path === '/index.php') {
            $path = '/';
        }

        return $path;
    }
}
