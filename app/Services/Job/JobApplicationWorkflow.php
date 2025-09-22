<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\Models\Application;
use App\Services\Job\Contracts\JobApplicationManagerInterface;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;

final class JobApplicationWorkflow implements JobApplicationManagerInterface
{
    public function applyToJob(int $jobId, int $candidateId, array $input): array
    {
        if ($candidateId <= 0) {
            return ['application_id' => 0, 'errors' => ['general' => 'Authentication required.']];
        }

        $answers = $input['answers'] ?? [];
        $now = date('Y-m-d H:i:s');

        return Capsule::connection()->transaction(static function () use ($jobId, $candidateId, $answers, $now, $input) {
            $existing = Application::query()
                ->where('candidate_id', $candidateId)
                ->where('job_posting_id', $jobId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && strtolower((string) $existing->application_status) !== 'withdrawn') {
                return [
                    'application_id' => (int) $existing->getKey(),
                    'errors' => ['general' => 'You have already applied to this job.'],
                ];
            }

            $reapplied = false;
            if ($existing !== null) {
                $existing->application_status = 'Applied';
                $existing->application_date = $now;
                $existing->updated_at = $now;
                $existing->resume_url = $input['resume_url'] ?? $existing->resume_url;
                $existing->cover_letter = $input['cover_letter'] ?? $existing->cover_letter;
                $existing->notes = $input['notes'] ?? $existing->notes;
                $existing->save();

                $applicationId = (int) $existing->getKey();
                Capsule::table('application_answers')
                    ->where('application_id', $applicationId)
                    ->delete();
                $reapplied = true;
            } else {
                $application = Application::create([
                    'candidate_id' => $candidateId,
                    'job_posting_id' => $jobId,
                    'application_status' => 'Applied',
                    'application_date' => $now,
                    'resume_url' => $input['resume_url'] ?? null,
                    'cover_letter' => $input['cover_letter'] ?? null,
                    'notes' => $input['notes'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $applicationId = (int) $application->getKey();
            }

            self::storeAnswers($applicationId, $answers, $now);

            return [
                'application_id' => $applicationId,
                'errors' => [],
                'reapplied' => $reapplied,
            ];
        });
    }

    public function listApplications(array $filters = []): array
    {
        $query = Application::query()->with(['candidate', 'jobPosting']);

        if (isset($filters['job_id']) && (int) $filters['job_id'] > 0) {
            $query->where('job_posting_id', (int) $filters['job_id']);
        }

        if (isset($filters['candidate_id']) && (int) $filters['candidate_id'] > 0) {
            $query->where('candidate_id', (int) $filters['candidate_id']);
        }

        $applications = $query->orderByDesc('application_date')->get();

        return $applications->map(static function (Application $application): array {
            return self::applicationToArray($application, true, true);
        })->all();
    }

    public function getApplication(int $applicationId): ?array
    {
        $application = Application::query()->with(['candidate', 'jobPosting'])->find($applicationId);
        if ($application === null) {
            return null;
        }

        return self::applicationToArray($application, true, true);
    }

    public function listForJob(int $jobId): array
    {
        if ($jobId <= 0) {
            return [];
        }

        $applications = Application::query()
            ->where('job_posting_id', $jobId)
            ->with('candidate')
            ->orderByDesc('application_date')
            ->get();

        return $applications->map(static function (Application $application): array {
            return self::applicationToArray($application, true, false);
        })->all();
    }

    /**
     * @param array<int, array<string, mixed>> $answers
     */
    private static function storeAnswers(int $applicationId, array $answers, string $timestamp): void
    {
        if ($answers === []) {
            return;
        }

        $rows = [];
        foreach ($answers as $answer) {
            if (!is_array($answer)) {
                continue;
            }

            $questionId = 0;
            if (isset($answer['question_id'])) {
                $questionId = (int) $answer['question_id'];
            } elseif (isset($answer['qid'])) {
                $questionId = (int) $answer['qid'];
            }

            $text = trim((string)($answer['answer'] ?? $answer['text'] ?? ''));
            if ($questionId <= 0 || $text === '') {
                continue;
            }

            $rows[] = [
                'application_id' => $applicationId,
                'question_id' => $questionId,
                'answer_text' => $text,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        if ($rows !== []) {
            Capsule::table('application_answers')->insert($rows);
        }
    }

    private static function applicationToArray(Application $application, bool $includeCandidate, bool $includeJob): array
    {
        $data = $application->toArray();

        if ($includeCandidate) {
            $candidate = $application->candidate;
            if ($candidate instanceof Model) {
                $data['candidate'] = $candidate->toArray();
            }
        }

        if ($includeJob) {
            $job = $application->jobPosting;
            if ($job instanceof Model) {
                $data['job'] = $job->toArray();
            }
        }

        return $data;
    }
}
