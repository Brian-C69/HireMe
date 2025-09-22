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
use App\Services\Resume\Builder\JsonProfileBuilder;
use App\Services\Resume\Builder\ProfileBuilder;
use App\Services\Resume\Builder\ProfileDirector;
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
            $buildResult = $this->buildGeneratedResume($candidateId, $data);
            $relativePath = $buildResult['path'];
            $format = $buildResult['format'];
            $variant = $buildResult['variant'];

            $dataForStorage = $data;
            $dataForStorage['format'] = $format;
            $dataForStorage['variant'] = $variant;

            $builderRecord = $this->builders->create([
                'candidate_id' => $candidateId,
                'template' => $data['template'] ?? 'modern',
                'data' => $dataForStorage,
                'generated_path' => $relativePath,
            ]);

            $resume = $this->resumes->create([
                'candidate_id' => $candidateId,
                'title' => $data['title'] ?? 'Generated Resume',
                'file_path' => $relativePath,
                'content' => $this->encodeResumeData($dataForStorage),
                'is_generated' => true,
                'visibility' => $data['visibility'] ?? 'private',
            ]);

            $this->notifications->notify($candidateId, 'Resume generated', [
                'resume_id' => $resume->getKey(),
                'builder_id' => $builderRecord->getKey(),
                'format' => $format,
                'variant' => $variant,
                'path' => $relativePath,
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

    /**
     * @param array<string, mixed> $data
     * @return array{path: string, format: string, variant: string}
     */
    private function buildGeneratedResume(int $candidateId, array $data): array
    {
        $builder = $this->resolveBuilder($data);
        $variant = $this->resolveVariant($data);

        $directory = storage_path('resumes');
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create resume storage directory at %s.', $directory));
        }

        $filename = sprintf("candidate_%d_%s.%s", $candidateId, bin2hex(random_bytes(8)), $this->determineExtension($builder));
        $relativePath = 'resumes/' . $filename;
        $fullPath = $directory . DIRECTORY_SEPARATOR . $filename;

        $profile = $variant === 'preview'
            ? $this->profileDirector->buildPreview($builder, $data)
            : $this->profileDirector->buildFullProfile($builder, $data);

        if (file_put_contents($fullPath, $profile) === false) {
            throw new RuntimeException(sprintf('Unable to write generated resume to %s.', $fullPath));
        }

        return [
            'path' => $relativePath,
            'format' => $builder->getFormat(),
            'variant' => $variant,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveBuilder(array $data): ProfileBuilder
    {
        $format = $data['format'] ?? $data['builder'] ?? $data['output'] ?? null;
        $normalised = is_string($format) ? strtolower($format) : null;

        return match ($normalised) {
            'json', 'application/json' => new JsonProfileBuilder(),
            default => new HtmlProfileBuilder(),
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveVariant(array $data): string
    {
        $variant = $data['variant'] ?? $data['view'] ?? null;
        $normalised = is_string($variant) ? strtolower($variant) : null;

        return $normalised === 'preview' ? 'preview' : 'full';
    }

    private function determineExtension(ProfileBuilder $builder): string
    {
        return $builder->getFormat() === 'json' ? 'json' : 'html';
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
