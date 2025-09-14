<?php

declare(strict_types=1);

namespace App\Models;

class Candidate extends BaseModel
{
    protected $table = 'candidates';
    protected $primaryKey = 'candidate_id';

    protected $fillable = [
        'full_name',
        'email',
        'password_hash',
        'phone_number',
        'date_of_birth',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'profile_picture_url',
        'resume_url',
        'verified_status',
        'verification_date',
        'verification_doc_type',
        'verification_doc_url',
        'premium_badge',
        'premium_badge_date',
        'skills',
        'experience_years',
        'education_level',
    ];

    public function applications()
    {
        return $this->hasMany(Application::class, 'candidate_id', 'candidate_id');
    }
}
