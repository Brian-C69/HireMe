<?php

declare(strict_types=1);

namespace App\Services\Resume\Builder;


use function array_slice;
use function is_array;
use function trim;

class ProfileDirector
{
    /**
     * Build the full resume including all sections.
     *
     * @param array<string, mixed> $data
     */
    public function buildFullProfile(ProfileBuilder $builder, array $data): string
    {
        $builder->start($data);
        $builder->addHeader($this->prepareHeader($data));
        $builder->addSummary($this->prepareSummary($data));
        $builder->addExperience($this->prepareExperience($data));
        $builder->addSkills($this->prepareSkills($data));

        return $builder->getProfile();
    }

    /**
     * Build a condensed preview with limited experience and skills.
     *
     * @param array<string, mixed> $data
     */
    public function buildPreview(ProfileBuilder $builder, array $data): string
    {
        $builder->start($data);
        $builder->addHeader($this->prepareHeader($data));
        $builder->addSummary($this->prepareSummary($data));
        $builder->addExperience(array_slice($this->prepareExperience($data), 0, 1));
        $builder->addSkills(array_slice($this->prepareSkills($data), 0, 5));

        return $builder->getProfile();
    }

    /**
     * @param array<string, mixed> $data
     * @return array{name: string, headline?: string|null, contacts?: array<int, string>}
     */
    private function prepareHeader(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $name = 'Candidate';
        }

        $headlineSource = $data['headline'] ?? ($data['title'] ?? null);
        $headline = $headlineSource !== null ? trim((string) $headlineSource) : '';

        $contacts = [];
        foreach (['email', 'phone', 'location'] as $field) {
            if (!isset($data[$field])) {
                continue;
            }

            $value = trim((string) $data[$field]);
            if ($value !== '') {
                $contacts[] = $value;
            }
        }

        $header = ['name' => $name];
        if ($headline !== '') {
            $header['headline'] = $headline;
        }
        if ($contacts !== []) {
            $header['contacts'] = $contacts;
        }

        return $header;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function prepareSummary(array $data): ?string
    {
        $summary = isset($data['summary']) ? trim((string) $data['summary']) : '';

        return $summary !== '' ? $summary : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array{role: string, company?: string|null, period?: string|null, description?: string|null}>
     */
    private function prepareExperience(array $data): array
    {
        $items = [];
        $experience = $data['experience'] ?? [];
        if (!is_array($experience)) {
            return $items;
        }

        foreach ($experience as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $role = trim((string) ($entry['role'] ?? ($entry['title'] ?? '')));
            if ($role === '') {
                continue;
            }

            $item = ['role' => $role];

            $company = isset($entry['company']) ? trim((string) $entry['company']) : '';
            if ($company !== '') {
                $item['company'] = $company;
            }

            $period = isset($entry['period']) ? trim((string) $entry['period']) : '';
            if ($period !== '') {
                $item['period'] = $period;
            }

            $description = isset($entry['description']) ? trim((string) $entry['description']) : '';
            if ($description !== '') {
                $item['description'] = $description;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    private function prepareSkills(array $data): array
    {
        $skills = [];
        $raw = $data['skills'] ?? [];
        if (!is_array($raw)) {
            return $skills;
        }

        foreach ($raw as $skill) {
            $value = trim((string) $skill);
            if ($value !== '') {
                $skills[] = $value;
            }
        }

        return $skills;
    }
}
