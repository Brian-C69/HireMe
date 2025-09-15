<?php

declare(strict_types=1);

namespace App\Models;

class Resume extends BaseModel
{
    protected $table = 'resumes';
    protected $primaryKey = 'resume_id';

    protected $fillable = [
        'candidate_id',
        'resume_url',
        'generated_by_system',
        'summary',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidate::class, 'candidate_id', 'candidate_id');
    }
}

