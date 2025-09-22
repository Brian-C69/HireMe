<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\Models\JobPosting;
use App\Services\Job\Contracts\JobSearchInterface;
use Illuminate\Database\Eloquent\Model;

final class JobSearchService implements JobSearchInterface
{
    public function refreshJob(int $jobId): void
    {
        // Search index is not implemented; method included for interface completeness.
    }

    public function search(array $filters = []): array
    {
        $query = JobPosting::query()->with(['employer', 'recruiter']);

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['company_id']) && (int) $filters['company_id'] > 0) {
            $query->where('company_id', (int) $filters['company_id']);
        }

        if (isset($filters['recruiter_id']) && (int) $filters['recruiter_id'] > 0) {
            $query->where('recruiter_id', (int) $filters['recruiter_id']);
        }

        $jobs = $query->orderByDesc('date_posted')->get();

        return $jobs->map(static function (JobPosting $posting): array {
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
    }

    public function getJob(int $jobId): ?array
    {
        $job = JobPosting::query()->with(['employer', 'recruiter'])->find($jobId);
        if ($job === null) {
            return null;
        }

        $data = $job->toArray();
        $employer = $job->employer;
        if ($employer instanceof Model) {
            $data['employer'] = $employer->toArray();
        }
        $recruiter = $job->recruiter;
        if ($recruiter instanceof Model) {
            $data['recruiter'] = $recruiter->toArray();
        }

        return $data;
    }
}
