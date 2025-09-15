<?php

declare(strict_types=1);

namespace App\Models;

class ResumeUnlock extends BaseModel
{
    protected $table = 'resume_unlocks';
    protected $primaryKey = 'id';

    protected $fillable = [
        'candidate_id',
        'user_type',
        'user_id',
        'unlocked_at',
    ];
}

