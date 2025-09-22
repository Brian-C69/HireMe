<?php

declare(strict_types=1);

namespace App\Services\Resume\Builder;

/**
 * Defines the contract for assembling resume/profile data one section at a time.
 */
interface ProfileBuilder
{
    /**
     * Prepare the builder for a fresh profile.
     *
     * @param array<string, mixed> $context
     */
    public function start(array $context = []): void;

    /**
     * @param array{name: string, headline?: string|null, contacts?: array<int, string>} $header
     */
    public function addHeader(array $header): void;

    public function addSummary(?string $summary): void;

    /**
     * @param array<int, array{role: string, company?: string|null, period?: string|null, description?: string|null}> $items
     */
    public function addExperience(array $items): void;

    /**
     * @param array<int, string> $skills
     */
    public function addSkills(array $skills): void;

    public function getProfile(): string;
}
