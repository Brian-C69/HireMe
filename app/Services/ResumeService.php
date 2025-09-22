<?php

namespace App\Services;

use App\Core\ORM\EntityManager;
use App\Models\Resume;
use App\Repositories\BillingRepository;
use App\Repositories\ResumeBuilderRepository;
use App\Repositories\ResumeRepository;
use App\Repositories\ResumeUnlockRepository;
use App\Services\Notifications\NotificationService;
use App\Services\Resume\Builder\HtmlProfileBuilder;
use App\Services\Resume\ProfileDirector;
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
        private NotificationService $notifications,
        private ProfileDirector $profileDirector
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

        $html = $this->profileDirector->buildFullProfile(new HtmlProfileBuilder(), $data);
        if (file_put_contents($fullPath, $html) === false) {
            throw new RuntimeException(sprintf('Unable to write generated resume to %s.', $fullPath));
        }

        return $relativePath;
    }

    private function encodeResumeData(array $data): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode resume data to JSON.', 0, $exception);
        }
    }
}
