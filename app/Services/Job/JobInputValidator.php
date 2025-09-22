<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\Services\Job\Contracts\JobValidatorInterface;

final class JobInputValidator implements JobValidatorInterface
{
    public function validateForPublish(string $role, int $userId, array $input): array
    {
        $title   = trim((string)($input['job_title'] ?? ''));
        $desc    = trim((string)($input['job_description'] ?? ''));
        $loc     = trim((string)($input['job_location'] ?? ''));
        $langs   = trim((string)($input['job_languages'] ?? ''));
        $salary  = (string)($input['salary'] ?? '');
        $empType = trim((string)($input['employment_type'] ?? 'Full-time'));

        $companyId = ($role === 'Employer')
            ? $userId
            : (int)($input['company_id'] ?? 0);

        $chosen = array_values(array_filter((array)($input['mi_questions'] ?? []), static fn($v) => ctype_digit((string) $v)));
        $chosen = array_unique(array_map('intval', $chosen));

        $errors = [];
        if ($title === '') {
            $errors['job_title'] = 'Job title is required.';
        }
        if ($desc === '') {
            $errors['job_description'] = 'Description is required.';
        }
        if ($salary !== '' && !is_numeric($salary)) {
            $errors['salary'] = 'Salary must be numeric (e.g., 3500).';
        }
        if ($role === 'Recruiter' && $companyId <= 0) {
            $errors['company_id'] = 'Please select a company.';
        }
        if (count($chosen) !== 3) {
            $errors['mi_questions'] = 'Please select exactly 3 questions.';
        }

        $now = date('Y-m-d H:i:s');
        $data = [
            'company_id'       => $companyId,
            'recruiter_id'     => ($role === 'Recruiter' ? $userId : null),
            'job_title'        => $title,
            'job_description'  => $desc,
            'job_requirements' => null,
            'job_location'     => $loc ?: null,
            'job_languages'    => $langs ?: null,
            'employment_type'  => $empType ?: 'Full-time',
            'salary_range_min' => ($salary === '' ? null : number_format((float) $salary, 2, '.', '')),
            'date_posted'      => $now,
            'created_at'       => $now,
            'updated_at'       => $now,
            'question_ids'     => $chosen,
        ];

        return ['data' => $data, 'errors' => $errors];
    }

    public function validateForUpdate(string $role, int $userId, array $input, array $existing = []): array
    {
        $result = $this->validateForPublish($role, $userId, $input);

        // Preserve existing timestamps if provided to avoid overwriting unintentionally.
        if (isset($existing['created_at'])) {
            $result['data']['created_at'] = $existing['created_at'];
        }

        return $result;
    }
}
