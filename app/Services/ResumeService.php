<?php

namespace App\Services;

use App\Core\ORM\EntityManager;
use App\Models\Resume;
use App\Repositories\BillingRepository;
use App\Repositories\ResumeBuilderRepository;
use App\Repositories\ResumeRepository;
use App\Repositories\ResumeUnlockRepository;
use App\Services\Notifications\NotificationService;
use JsonException;
use RuntimeException;

class ResumeService
{
    public function __construct(
        private EntityManager $entityManager,
        private ResumeRepository $resumes,
        private ResumeBuilderRepository $builders,
        private ResumeUnlockRepository $unlocks,
        private BillingRepository $billing,
        private NotificationService $notifications
    ) {
    }

    public function upload(array $payload): Resume
    {
        /** @var Resume $resume */
        $resume = $this->resumes->create($payload);
        $this->notifications->notify($payload['candidate_id'], 'Resume uploaded', ['resume_id' => $resume->getKey()]);
        return $resume;
    }

    public function generate(int $candidateId, array $data): Resume
    {
        return $this->entityManager->transaction(function () use ($candidateId, $data) {
            $relativePath = $this->buildGeneratedResume($candidateId, $data);

            $builder = $this->builders->create([
                'candidate_id' => $candidateId,
                'template' => $data['template'] ?? 'modern',
                'data' => $data,
                'generated_path' => $relativePath,
            ]);

            $resume = $this->resumes->create([
                'candidate_id' => $candidateId,
                'title' => $data['title'] ?? 'Generated Resume',
                'file_path' => $relativePath,
                'content' => $this->encodeResumeData($data),
                'is_generated' => true,
                'visibility' => $data['visibility'] ?? 'private',
            ]);

            $this->notifications->notify($candidateId, 'Resume generated', [
                'resume_id' => $resume->getKey(),
                'builder_id' => $builder->getKey(),
            ]);

            return $resume;
        });
    }

    public function unlock(int $resumeId, int $userId, int $credits = 1): bool
    {
        $billing = $this->billing->forUser($userId);
        if (!$billing || $billing->getAttribute('credits_balance') < $credits) {
            return false;
        }

        $billing->fill(['credits_balance' => $billing->getAttribute('credits_balance') - $credits]);
        $billing->save();

        $this->unlocks->create([
            'resume_id' => $resumeId,
            'unlocked_by' => $userId,
            'credits_used' => $credits,
            'unlocked_at' => now(),
        ]);

        $this->notifications->notify($userId, 'Resume unlocked', ['resume_id' => $resumeId]);
        return true;
    }

    private function buildGeneratedResume(int $candidateId, array $data): string
    {
        $directory = storage_path('resumes');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create resume storage directory at %s.', $directory));
        }

        $filename = sprintf('candidate_%d_%s.html', $candidateId, bin2hex(random_bytes(8)));
        $relativePath = 'resumes/' . $filename;
        $fullPath = $directory . DIRECTORY_SEPARATOR . $filename;

        $html = $this->renderResumeTemplate($data);
        if (file_put_contents($fullPath, $html) === false) {
            throw new RuntimeException(sprintf('Unable to write generated resume to %s.', $fullPath));
        }

        return $relativePath;
    }

    private function renderResumeTemplate(array $data): string
    {
        $name = $this->escape((string) ($data['name'] ?? 'Candidate'));
        $headlineValue = trim((string) ($data['headline'] ?? ($data['title'] ?? '')));
        $headline = $headlineValue !== '' ? '<p class="headline">' . $this->escape($headlineValue) . '</p>' : '';

        $contactParts = [];
        foreach (['email', 'phone', 'location'] as $field) {
            if (!empty($data[$field])) {
                $contactParts[] = '<span>' . $this->escape((string) $data[$field]) . '</span>';
            }
        }
        $contact = $contactParts ? '<div class="contact">' . implode(' • ', $contactParts) . '</div>' : '';

        $summaryValue = trim((string) ($data['summary'] ?? ''));
        $summary = $summaryValue !== ''
            ? '<section><h2>Summary</h2><p>' . nl2br($this->escape($summaryValue), false) . '</p></section>'
            : '';

        $experienceItems = '';
        foreach ((array) ($data['experience'] ?? []) as $item) {
            $role = trim((string) ($item['role'] ?? ($item['title'] ?? '')));
            if ($role === '') {
                continue;
            }

            $company = trim((string) ($item['company'] ?? ''));
            $period = trim((string) ($item['period'] ?? ''));
            $description = trim((string) ($item['description'] ?? ''));

            $line = '<strong>' . $this->escape($role) . '</strong>';
            if ($company !== '') {
                $line .= ' at ' . $this->escape($company);
            }
            if ($period !== '') {
                $line .= ' <em>(' . $this->escape($period) . ')</em>';
            }

            $experienceItems .= '<li>' . $line;
            if ($description !== '') {
                $experienceItems .= '<div>' . nl2br($this->escape($description), false) . '</div>';
            }
            $experienceItems .= '</li>';
        }
        $experience = $experienceItems !== ''
            ? '<section><h2>Experience</h2><ul>' . $experienceItems . '</ul></section>'
            : '';

        $skillsItems = '';
        foreach ((array) ($data['skills'] ?? []) as $skill) {
            $skill = trim((string) $skill);
            if ($skill === '') {
                continue;
            }
            $skillsItems .= '<li>' . $this->escape($skill) . '</li>';
        }
        $skills = $skillsItems !== ''
            ? '<section><h2>Skills</h2><ul class="skills">' . $skillsItems . '</ul></section>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$name} — Resume</title>
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
        <h1>{$name}</h1>
        {$contact}
        {$headline}
    </header>
    <main>
        {$summary}
        {$experience}
        {$skills}
    </main>
</body>
</html>
HTML;
    }

    private function encodeResumeData(array $data): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode resume data to JSON.', 0, $exception);
        }
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
