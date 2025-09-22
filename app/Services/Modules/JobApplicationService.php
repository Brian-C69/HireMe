<?php

declare(strict_types=1);

namespace App\Services\Modules;

use App\Core\Request;
use App\Models\Application;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class JobApplicationService extends AbstractModuleService
{
    public function name(): string
    {
        return 'job-application';
    }

    public function handle(string $type, ?string $id, Request $request): array
    {
        return match (strtolower($type)) {
            'jobs' => $this->listJobs($request, $id),
            'job' => $this->showJob($id),
            'applications' => $this->listApplications($request),
            'application' => $this->showApplication($id),
            'summary' => $this->summarise(),
            default => throw new InvalidArgumentException(sprintf('Unknown job/application operation "%s".', $type)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function listJobs(Request $request, ?string $scope): array
    {
        $query = JobPosting::query()->with(['employer', 'recruiter']);

        $status = $this->query($request, 'status');
        if ($scope !== null && $scope !== '' && !ctype_digit($scope)) {
            $scopeLower = strtolower($scope);
            if (in_array($scopeLower, ['active', 'open'], true)) {
                $status = $status ?? 'active';
            } elseif (in_array($scopeLower, ['closed', 'archived'], true)) {
                $status = $status ?? 'closed';
            }
        }

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        if ($scope !== null && str_starts_with($scope, 'employer-')) {
            $companyId = substr($scope, strlen('employer-'));
            if (ctype_digit($companyId)) {
                $query->where('company_id', (int) $companyId);
            }
        } elseif ($scope !== null && str_starts_with($scope, 'recruiter-')) {
            $recruiterId = substr($scope, strlen('recruiter-'));
            if (ctype_digit($recruiterId)) {
                $query->where('recruiter_id', (int) $recruiterId);
            }
        }

        if ($employer = $this->query($request, 'employer_id')) {
            if (ctype_digit($employer)) {
                $query->where('company_id', (int) $employer);
            }
        }

        if ($recruiter = $this->query($request, 'recruiter_id')) {
            if (ctype_digit($recruiter)) {
                $query->where('recruiter_id', (int) $recruiter);
            }
        }

        $jobs = $query->orderByDesc('date_posted')->get();

        $items = $jobs->map(static function (JobPosting $posting): array {
            $data = $posting->toArray();
            $employer = $posting->employer;
            if ($employer instanceof Model) {
                $data['employer'] = $employer->toArray();
            }
            $recruiter = $posting->recruiter;
            if ($recruiter instanceof Model) {
                $data['recruiter'] = $recruiter->toArray();
            }
            return $data;
        })->all();

        return $this->respond([
            'jobs' => $items,
            'count' => count($items),
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function showJob(?string $id): array
    {
        $jobId = $this->requireIntId($id, 'A job identifier is required.');
        $job = JobPosting::query()->with(['employer', 'recruiter'])->find($jobId);
        if ($job === null) {
            throw new InvalidArgumentException('Job posting not found.');
        }

        $payload = $job->toArray();
        $employer = $job->employer;
        if ($employer instanceof Model) {
            $payload['employer'] = $employer->toArray();
        }
        $recruiter = $job->recruiter;
        if ($recruiter instanceof Model) {
            $payload['recruiter'] = $recruiter->toArray();
        }

        $applications = Application::query()
            ->where('job_posting_id', $jobId)
            ->with('candidate')
            ->get();

        $payload['applications'] = $applications->map(static function (Application $application): array {
            $data = $application->toArray();
            $candidate = $application->candidate;
            if ($candidate instanceof Model) {
                $data['candidate'] = $candidate->toArray();
            }
            return $data;
        })->all();
        $payload['application_count'] = $applications->count();

        return $this->respond([
            'job' => $payload,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listApplications(Request $request): array
    {
        $query = Application::query()->with(['candidate', 'jobPosting']);

        if ($job = $this->query($request, 'job_id')) {
            if (ctype_digit($job)) {
                $query->where('job_posting_id', (int) $job);
            }
        }

        if ($candidate = $this->query($request, 'candidate_id')) {
            if (ctype_digit($candidate)) {
                $query->where('candidate_id', (int) $candidate);
            }
        }

        $applications = $query->orderByDesc('created_at')->get();

        $items = $applications->map(static function (Application $application): array {
            $data = $application->toArray();
            $candidate = $application->candidate;
            if ($candidate instanceof Model) {
                $data['candidate'] = $candidate->toArray();
            }
            $jobPosting = $application->jobPosting;
            if ($jobPosting instanceof Model) {
                $data['job'] = $jobPosting->toArray();
            }
            return $data;
        })->all();

        return $this->respond([
            'applications' => $items,
            'count' => count($items),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function showApplication(?string $id): array
    {
        $applicationId = $this->requireIntId($id, 'An application identifier is required.');
        $application = Application::query()->with(['candidate', 'jobPosting'])->find($applicationId);
        if ($application === null) {
            throw new InvalidArgumentException('Application record not found.');
        }

        $payload = $application->toArray();
        $candidate = $application->candidate;
        if ($candidate instanceof Model) {
            $payload['candidate'] = $candidate->toArray();
        }
        $jobPosting = $application->jobPosting;
        if ($jobPosting instanceof Model) {
            $payload['job'] = $jobPosting->toArray();
        }

        if (isset($payload['candidate']['candidate_id'])) {
            $candidateId = (string) $payload['candidate']['candidate_id'];
            $payload['profile'] = $this->forward('resume-profile', 'profile', $candidateId);
        }

        return $this->respond([
            'application' => $payload,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summarise(): array
    {
        $totalJobs = JobPosting::query()->count();
        $activeJobs = JobPosting::query()->whereIn('status', ['active', 'open'])->count();
        $closedJobs = JobPosting::query()->whereIn('status', ['closed', 'archived'])->count();

        $totalApplications = Application::query()->count();
        $recentApplications = Application::query()->orderByDesc('created_at')->take(5)->get();
        $recent = $recentApplications->map(static function (Application $application): array {
            return $application->toArray();
        })->all();

        $topCandidates = Application::query()
            ->selectRaw('candidate_id, COUNT(*) as total_applications')
            ->whereNotNull('candidate_id')
            ->groupBy('candidate_id')
            ->orderByDesc('total_applications')
            ->take(5)
            ->get()
            ->map(function ($row) {
                $candidateId = (string) $row->candidate_id;
                $profile = $this->forward('resume-profile', 'profile', $candidateId);
                return [
                    'candidate_id' => (int) $row->candidate_id,
                    'applications' => (int) $row->total_applications,
                    'profile' => $profile['profile'] ?? null,
                    'resume' => $profile['resume'] ?? null,
                ];
            })
            ->all();

        return $this->respond([
            'summary' => [
                'jobs' => [
                    'total' => $totalJobs,
                    'active' => $activeJobs,
                    'closed' => $closedJobs,
                ],
                'applications' => [
                    'total' => $totalApplications,
                    'recent' => $recent,
                ],
                'top_candidates' => $topCandidates,
            ],
        ]);
    }
}
