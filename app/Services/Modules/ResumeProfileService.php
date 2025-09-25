<?php

declare(strict_types=1);

namespace App\Services\Modules;

use App\Core\Request;
use App\Models\Candidate;
use App\Models\Resume;
use App\Services\Admin\AdminRoleAwareInterface;
use App\Services\Admin\AdminRoleAwareTrait;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use App\Services\Resume\Builder\HtmlProfileBuilder;
use App\Services\Resume\Builder\JsonProfileBuilder;
use App\Services\Resume\Builder\ProfileBuilder;
use App\Services\Resume\Builder\ProfileDirector;
use JsonException;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function strtolower;
use function str_ends_with;
use function trim;


final class ResumeProfileService extends AbstractModuleService implements AdminRoleAwareInterface
{
    use AdminRoleAwareTrait;

    private ProfileDirector $profileDirector;

    public function __construct(?ProfileDirector $profileDirector = null)
    {
        $this->profileDirector = $profileDirector ?? new ProfileDirector();
    }

    public function name(): string
    {
        return 'resume-profile';
    }

    public function handle(string $type, ?string $id, Request $request): array
    {
        return match (strtolower($type)) {
            'resumes' => $this->listResumes($request, $id),
            'resume' => $this->showResume($request, $id),
            'profiles' => $this->listProfiles($request),
            'profile' => $this->showProfile($request, $id),
            default => throw new InvalidArgumentException(sprintf('Unknown resume/profile operation "%s".', $type)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function listResumes(Request $request, ?string $scope): array
    {
        $query = Resume::query()->with('candidate');

        $candidateId = null;
        if ($scope !== null && $scope !== '' && $scope !== 'all' && ctype_digit($scope)) {
            $candidateId = (int) $scope;
        }

        $candidateQuery = $this->query($request, 'candidate_id');
        if ($candidateId === null && $candidateQuery !== null && ctype_digit($candidateQuery)) {
            $candidateId = (int) $candidateQuery;
        }

        if ($candidateId !== null) {
            $query->where('candidate_id', $candidateId);
        }

        $this->adminGuardian()->assertRead('resumes', $this->adminContext($request, [
            'action' => 'resumes.list',
            'candidate_id' => $candidateId,
        ]));

        $resumes = $query->orderByDesc('updated_at')->get();

        $items = $resumes->map(static function (Resume $resume): array {
            $data = $resume->toArray();
            $candidate = $resume->candidate;
            if ($candidate instanceof Model) {
                $data['candidate'] = $candidate->toArray();
            }
            return $data;
        })->all();

        return $this->respond([
            'resumes' => $items,
            'count' => count($items),
            'filters' => [
                'candidate_id' => $candidateId,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function showResume(Request $request, ?string $id): array
    {
        $resumeId = $this->requireIntId($id, 'A resume identifier is required.');
        $resume = Resume::query()->with('candidate')->find($resumeId);
        if ($resume === null) {
            throw new InvalidArgumentException('Resume record not found.');
        }

        $this->adminGuardian()->assertRead('resumes', $this->adminContext($request, [
            'action' => 'resumes.show',
            'resume_id' => $resumeId,
        ]));

        $payload = $resume->toArray();
        $candidate = $resume->candidate;
        if ($candidate instanceof Model) {
            $payload['candidate'] = $candidate->toArray();
        }

        $rendered = $this->renderResumeOutput($resume);
        if ($rendered !== null) {
            $payload['rendered_resume'] = $rendered['output'];
            $payload['rendered_format'] = $rendered['format'];
        }

        return $this->respond([
            'resume' => $payload,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listProfiles(Request $request): array
    {
        $query = Candidate::query();

        if ($status = $this->query($request, 'verified_status')) {
            $query->where('verified_status', $status);
        }

        if ($city = $this->query($request, 'city')) {
            $query->where('city', $city);
        }

        $this->adminGuardian()->assertRead('profiles', $this->adminContext($request, [
            'action' => 'profiles.list',
        ]));

        $candidates = $query->orderBy('full_name')->get();

        return $this->respond([
            'profiles' => $candidates->map(static fn (Candidate $candidate) => $candidate->toArray())->all(),
            'count' => $candidates->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function showProfile(Request $request, ?string $id): array
    {
        $candidateId = $this->requireIntId($id, 'A candidate identifier is required.');
        $candidate = Candidate::find($candidateId);
        if ($candidate === null) {
            throw new InvalidArgumentException('Candidate profile not found.');
        }

        $this->adminGuardian()->assertRead('profiles', $this->adminContext($request, [
            'action' => 'profiles.show',
            'candidate_id' => $candidateId,
        ]));

        $resume = Resume::query()->where('candidate_id', $candidateId)->orderByDesc('updated_at')->first();

        $userDetails = $this->forward('user-management', 'user', (string) $candidateId, [
            'role' => 'candidates',
        ]);

        $resumeData = null;
        if ($resume !== null) {
            $resumeData = $resume->toArray();
            $rendered = $this->renderResumeOutput($resume);
            if ($rendered !== null) {
                $resumeData['rendered_resume'] = $rendered['output'];
                $resumeData['rendered_format'] = $rendered['format'];
            }
        }

        return $this->respond([
            'profile' => $candidate->toArray(),
            'resume' => $resumeData,
            'user' => $userDetails['user'] ?? null,
        ]);
    }
    /**
     * Render the resume using the configured builder.
     *
     * @return array{output: string, format: string}|null
     */
    private function renderResumeOutput(Resume $resume, string $variant = 'full'): ?array
    {
        $content = $resume->getAttribute('content');
        if (!is_string($content) || trim($content) === '') {
            return null;
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        $builder = $this->selectBuilderForResume($resume, $data);
        $format = $builder->getFormat();

        $output = $variant === 'preview'
            ? $this->profileDirector->buildPreview($builder, $data)
            : $this->profileDirector->buildFullProfile($builder, $data);

        return [
            'output' => $output,
            'format' => $format,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function selectBuilderForResume(Resume $resume, array $data): ProfileBuilder
    {
        $format = $data['format'] ?? $data['builder'] ?? $data['output'] ?? null;
        $normalised = is_string($format) ? strtolower($format) : null;

        if (in_array($normalised, ['json', 'application/json'], true)) {
            return new JsonProfileBuilder();
        }

        $path = $resume->getAttribute('file_path');
        if (is_string($path) && $path !== '') {
            $lower = strtolower($path);
            if (str_ends_with($lower, '.json')) {
                return new JsonProfileBuilder();
            }
        }

        return new HtmlProfileBuilder();
    }

}
