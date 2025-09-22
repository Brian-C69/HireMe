<?php

declare(strict_types=1);

namespace App\Services\Resume\Builder;

use JsonException;
use RuntimeException;

use function array_values;
use function json_encode;

class JsonProfileBuilder implements ProfileBuilder
{
    /** @var array<string, mixed> */
    private array $profile = [];

    /**
     * @param array<string, mixed> $context
     */
    public function start(array $context = []): void
    {
        $this->profile = [
            'name' => 'Candidate',
            'headline' => null,
            'contacts' => [],
            'summary' => null,
            'experience' => [],
            'skills' => [],
        ];
    }

    /**
     * @param array{name: string, headline?: string|null, contacts?: array<int, string>} $header
     */
    public function addHeader(array $header): void
    {
        $this->profile['name'] = $header['name'];
        $this->profile['headline'] = $header['headline'] ?? null;
        $this->profile['contacts'] = array_values($header['contacts'] ?? []);
    }

    public function addSummary(?string $summary): void
    {
        $this->profile['summary'] = $summary !== null && $summary !== '' ? $summary : null;
    }

    /**
     * @param array<int, array{role: string, company?: string|null, period?: string|null, description?: string|null}> $items
     */
    public function addExperience(array $items): void
    {
        $this->profile['experience'] = array_map(
            static fn (array $item): array => [
                'role' => $item['role'],
                'company' => $item['company'] ?? null,
                'period' => $item['period'] ?? null,
                'description' => $item['description'] ?? null,
            ],
            $items
        );
    }

    /**
     * @param array<int, string> $skills
     */
    public function addSkills(array $skills): void
    {
        $this->profile['skills'] = array_values($skills);
    }

    public function getProfile(): string
    {
        try {
            return json_encode($this->profile, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode profile data to JSON.', 0, $exception);
        }
    }
}
