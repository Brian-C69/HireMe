<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\Models\JobPosting;
use App\Services\Job\Contracts\JobRepositoryInterface;
use Illuminate\Database\Capsule\Manager as Capsule;

final class JobRepository implements JobRepositoryInterface
{
    public function createJob(array $data, array $questionIds = []): int
    {
        return Capsule::connection()->transaction(static function () use ($data, $questionIds) {
            $job = JobPosting::create($data);
            $jobId = (int) $job->getKey();
            JobPosting::attachQuestions($jobId, $questionIds);

            return $jobId;
        });
    }

    public function updateJob(int $jobId, array $data, array $questionIds = []): bool
    {
        return Capsule::connection()->transaction(static function () use ($jobId, $data, $questionIds) {
            $job = JobPosting::query()->find($jobId);
            if ($job === null) {
                return false;
            }

            $job->fill($data);
            $job->save();

            Capsule::table('job_micro_questions')
                ->where('job_posting_id', $jobId)
                ->delete();
            JobPosting::attachQuestions($jobId, $questionIds);

            return true;
        });
    }
}
