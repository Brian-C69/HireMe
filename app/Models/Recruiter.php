<?php

declare(strict_types=1);

namespace App\Models;

class Recruiter extends BaseModel
{
    protected $table = 'recruiters';
    protected $primaryKey = 'recruiter_id';

    public function jobPostings()
    {
        return $this->hasMany(JobPosting::class, 'recruiter_id', 'recruiter_id');
    }
}
