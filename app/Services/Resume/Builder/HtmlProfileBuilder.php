<?php

declare(strict_types=1);

namespace App\Services\Resume\Builder;

use function htmlspecialchars;
use function implode;
use function nl2br;
use function trim;

class HtmlProfileBuilder implements ProfileBuilder
{
    private string $name = 'Candidate';
    private string $headline = '';
    private string $contact = '';
    private string $summary = '';
    private string $experience = '';
    private string $skills = '';

    /**
     * @param array<string, mixed> $context
     */
    public function start(array $context = []): void
    {
        $this->reset();
    }

    /**
     * @param array{name: string, headline?: string|null, contacts?: array<int, string>} $header
     */
    public function addHeader(array $header): void
    {
        $this->name = $this->escape($header['name'] ?? 'Candidate');

        $headline = isset($header['headline']) ? trim((string) $header['headline']) : '';
        $this->headline = $headline !== ''
            ? '<p class="headline">' . $this->escape($headline) . '</p>'
            : '';

        $contactParts = [];
        foreach ($header['contacts'] ?? [] as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $contactParts[] = '<span>' . $this->escape($value) . '</span>';
        }

        $this->contact = $contactParts !== []
            ? '<div class="contact">' . implode(' • ', $contactParts) . '</div>'
            : '';
    }

    public function addSummary(?string $summary): void
    {
        $summary = $summary !== null ? trim($summary) : '';
        $this->summary = $summary !== ''
            ? '<section><h2>Summary</h2><p>' . nl2br($this->escape($summary), false) . '</p></section>'
            : '';
    }

    /**
     * @param array<int, array{role: string, company?: string|null, period?: string|null, description?: string|null}> $items
     */
    public function addExperience(array $items): void
    {
        $experienceItems = '';

        foreach ($items as $item) {
            $role = trim((string) ($item['role'] ?? ''));
            if ($role === '') {
                continue;
            }

            $line = '<strong>' . $this->escape($role) . '</strong>';

            $company = isset($item['company']) ? trim((string) $item['company']) : '';
            if ($company !== '') {
                $line .= ' at ' . $this->escape($company);
            }

            $period = isset($item['period']) ? trim((string) $item['period']) : '';
            if ($period !== '') {
                $line .= ' <em>(' . $this->escape($period) . ')</em>';
            }

            $experienceItems .= '<li>' . $line;

            $description = isset($item['description']) ? trim((string) $item['description']) : '';
            if ($description !== '') {
                $experienceItems .= '<div>' . nl2br($this->escape($description), false) . '</div>';
            }

            $experienceItems .= '</li>';
        }

        $this->experience = $experienceItems !== ''
            ? '<section><h2>Experience</h2><ul>' . $experienceItems . '</ul></section>'
            : '';
    }

    /**
     * @param array<int, string> $skills
     */
    public function addSkills(array $skills): void
    {
        $skillItems = '';
        foreach ($skills as $skill) {
            $skill = trim((string) $skill);
            if ($skill === '') {
                continue;
            }

            $skillItems .= '<li>' . $this->escape($skill) . '</li>';
        }

        $this->skills = $skillItems !== ''
            ? '<section><h2>Skills</h2><ul class="skills">' . $skillItems . '</ul></section>'
            : '';
    }

    public function getFormat(): string
    {
        return 'html';
    }

    public function getProfile(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$this->name} — Resume</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1f2933; margin: 0; padding: 32px; background: #f9fafb; }
        h1 { margin-bottom: 0; font-size: 28px; }
        h2 { font-size: 18px; border-bottom: 1px solid #d9e2ec; padding-bottom: 4px; text-transform: uppercase; letter-spacing: 1px; color: #102a43; }
        .contact { margin-top: 4px; color: #486581; }
        .headline { color: #243b53; margin-top: 8px; font-weight: 600; }
        section { margin-top: 24px; }
        ul { padding-left: 18px; }
        ul.skills { display: flex; flex-wrap: wrap; list-style: none; padding: 0; margin: 0; }
        ul.skills li { background: #e1effe; color: #1d4ed8; padding: 4px 8px; border-radius: 12px; margin: 4px 8px 4px 0; }
        li { margin-bottom: 12px; }
        li div { margin-top: 6px; color: #334e68; }
    </style>
</head>
<body>
    <header>
        <h1>{$this->name}</h1>
        {$this->contact}
        {$this->headline}
    </header>
    <main>
        {$this->summary}
        {$this->experience}
        {$this->skills}
    </main>
</body>
</html>
HTML;
    }

    private function reset(): void
    {
        $this->name = 'Candidate';
        $this->headline = '';
        $this->contact = '';
        $this->summary = '';
        $this->experience = '';
        $this->skills = '';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
