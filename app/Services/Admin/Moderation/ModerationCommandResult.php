<?php

declare(strict_types=1);

namespace App\Services\Admin\Moderation;

final class ModerationCommandResult
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly string $command,
        private readonly string $status,
        private readonly array $data = [],
        private readonly ?string $message = null
    ) {
    }

    public function command(): string
    {
        return $this->command;
    }

    public function status(): string
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    /**
     * @return array{command: string, status: string, data: array<string, mixed>, message?: string}
     */
    public function toArray(): array
    {
        $payload = [
            'command' => $this->command,
            'status' => $this->status,
            'data' => $this->data,
        ];

        if ($this->message !== null) {
            $payload['message'] = $this->message;
        }

        return $payload;
    }
}
