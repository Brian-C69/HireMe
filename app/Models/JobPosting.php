<?php

declare(strict_types=1);

namespace App\Models;

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
}
