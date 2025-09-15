<?php

declare(strict_types=1);

namespace App\Models;

class Admin extends BaseModel
{
    protected $table = 'admins';
    protected $primaryKey = 'admin_id';

    protected $fillable = [
        'full_name',
        'email',
        'password_hash',
        'role',
        'permissions',
        'profile_photo',
        'last_login_at',
        'status',
    ];
}

