<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\Models\Application;
use App\Models\JobPosting;
use App\Services\Job\Contracts\JobAnalyticsInterface;

final class JobAnalyticsService implements JobAnalyticsInterface
{
    public function recordJobPublished(int $jobId, array $jobData): void
    {
        // Analytics tracking could be added here.
    }

    public function recordJobUpdated(int $jobId, array $jobData): void
    {
        // Analytics tracking could be added here.
    }

    public function summarise(?callable $candidateProfileResolver = null): array
    {
        $totalJobs = JobPosting::query()->count();
        $activeJobs = JobPosting::query()->whereIn('status', ['active', 'open'])->count();
        $closedJobs = JobPosting::query()->whereIn('status', ['closed', 'archived'])->count();

        $totalApplications = Application::query()->count();
        $recentApplications = Application::query()->orderByDesc('application_date')->take(5)->get();
        $recent = $recentApplications->map(static function (Application $application): array {
            return $application->toArray();
        })->all();

        $topCandidatesQuery = Application::query()
            ->selectRaw('candidate_id, COUNT(*) as total_applications')
            ->whereNotNull('candidate_id')
            ->groupBy('candidate_id')
            ->orderByDesc('total_applications')
            ->take(5)
            ->get();

        $topCandidates = $topCandidatesQuery->map(function ($row) use ($candidateProfileResolver): array {
            $candidateId = (int) $row->candidate_id;
            $resolved = null;
            if ($candidateProfileResolver !== null && $candidateId > 0) {
                $resolved = $candidateProfileResolver($candidateId);
            }

            return [
                'candidate_id' => $candidateId,
                'applications' => (int) $row->total_applications,
                'profile' => is_array($resolved) ? ($resolved['profile'] ?? null) : null,
                'resume' => is_array($resolved) ? ($resolved['resume'] ?? null) : null,
            ];
        })->all();

        return [
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
        ];
    }
}
