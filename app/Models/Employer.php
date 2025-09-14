<?php

declare(strict_types=1);

namespace App\Models;

class Employer extends BaseModel
{
    protected $table = 'employers';
    protected $primaryKey = 'employer_id';

    protected $fillable = [
        'company_name',
        'email',
        'password_hash',
        'industry',
        'location',
        'contact_person_name',
        'contact_number',
        'company_logo',
        'company_description',
        'credits_balance',
    ];

    public function jobPostings()
    {
        return $this->hasMany(JobPosting::class, 'company_id', 'employer_id');
    }
}
