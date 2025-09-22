<?php

declare(strict_types=1);

namespace App\Services\Modules;

use App\Core\Request;
use App\Models\Candidate;
use App\Models\Resume;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class ResumeProfileService extends AbstractModuleService
{
    public function name(): string
    {
        return 'resume-profile';
    }

    public function handle(string $type, ?string $id, Request $request): array
    {
        return match (strtolower($type)) {
            'resumes' => $this->listResumes($request, $id),
            'resume' => $this->showResume($id),
            'profiles' => $this->listProfiles($request),
            'profile' => $this->showProfile($id),
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
    private function showResume(?string $id): array
    {
        $resumeId = $this->requireIntId($id, 'A resume identifier is required.');
        $resume = Resume::query()->with('candidate')->find($resumeId);
        if ($resume === null) {
            throw new InvalidArgumentException('Resume record not found.');
        }

        $payload = $resume->toArray();
        $candidate = $resume->candidate;
        if ($candidate instanceof Model) {
            $payload['candidate'] = $candidate->toArray();
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

        $candidates = $query->orderBy('full_name')->get();

        return $this->respond([
            'profiles' => $candidates->map(static fn (Candidate $candidate) => $candidate->toArray())->all(),
            'count' => $candidates->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function showProfile(?string $id): array
    {
        $candidateId = $this->requireIntId($id, 'A candidate identifier is required.');
        $candidate = Candidate::find($candidateId);
        if ($candidate === null) {
            throw new InvalidArgumentException('Candidate profile not found.');
        }

        $resume = Resume::query()->where('candidate_id', $candidateId)->orderByDesc('updated_at')->first();

        $userDetails = $this->forward('user-management', 'user', (string) $candidateId, [
            'role' => 'candidates',
        ]);

        return $this->respond([
            'profile' => $candidate->toArray(),
            'resume' => $resume?->toArray(),
            'user' => $userDetails['user'] ?? null,
        ]);
    }
}
