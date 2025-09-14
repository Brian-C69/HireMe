<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Capsule\Manager as Capsule;

class JobPosting extends BaseModel
{
    protected $table = 'job_postings';
    protected $primaryKey = 'job_posting_id';

    protected $fillable = [
        'company_id',
        'recruiter_id',
        'job_title',
        'job_description',
        'job_requirements',
        'job_location',
        'employment_type',
        'salary_range_min',
        'salary_range_max',
        'application_deadline',
        'date_posted',
        'status',
        'number_of_positions',
        'required_experience',
        'education_level',
    ];

    public function employer()
    {
        return $this->belongsTo(Employer::class, 'company_id', 'employer_id');
    }

    public function recruiter()
    {
        return $this->belongsTo(Recruiter::class, 'recruiter_id', 'recruiter_id');
    }

    public static function attachQuestions(int $jobId, array $questionIds): void
    {
        if (!$questionIds) return;
        $rows = array_map(
            fn($id) => ['job_posting_id' => $jobId, 'question_id' => $id],
            $questionIds
        );
        Capsule::table('job_micro_questions')->insert($rows);
    }
}
