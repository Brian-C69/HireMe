<?php

declare(strict_types=1);

namespace App\Core;

use JsonException;

final class Response
{
    private string $body;
    private int $status;
    /** @var array<string, array<int, string>> */
    private array $headers = [];

    public function __construct(string $body = '', int $status = 200, array $headers = [])
    {
        $this->body = $body;
        $this->status = $status;
        foreach ($headers as $name => $value) {
            $values = is_array($value) ? array_values(array_map('strval', $value)) : [(string) $value];
            $this->headers[$this->normalizeHeaderName($name)] = $values;
        }
    }

    public function body(): string
    {
        return $this->body;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function header(string $name, string $value, bool $replace = true): self
    {
        $normalized = $this->normalizeHeaderName($name);
        if ($replace || !isset($this->headers[$normalized])) {
            $this->headers[$normalized] = [$value];
        } else {
            $this->headers[$normalized][] = $value;
        }

        return $this;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $values) {
            foreach ($values as $value) {
                header($name . ': ' . $value, false);
            }
        }

        echo $this->body;
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        try {
            $body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            $body = json_encode(['error' => 'Unable to encode response'], JSON_UNESCAPED_UNICODE);
            $status = 500;
        }

        $headers = ['Content-Type' => 'application/json'] + $headers;

        return new self($body ?: '', $status, $headers);
    }

    private function normalizeHeaderName(string $name): string
    {
        $name = strtolower($name);
        $segments = explode('-', $name);
        $segments = array_map(static fn (string $segment) => ucfirst($segment), $segments);
        return implode('-', $segments);
    }
}
