<?php

declare(strict_types=1);

namespace App\Models;

class Application extends BaseModel
{
    protected $table = 'applications';
    protected $primaryKey = 'applicant_id';

    public function candidate()
    {
        return $this->belongsTo(Candidate::class, 'candidate_id', 'candidate_id');
    }

    public function jobPosting()
    {
        return $this->belongsTo(JobPosting::class, 'job_posting_id', 'job_posting_id');
    }
}
