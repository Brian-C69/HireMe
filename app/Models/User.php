<?php

declare(strict_types=1);

namespace App\Models;

class User extends BaseModel
{
    protected $table = 'users';
    protected $primaryKey = 'id';

    protected $fillable = [
        'email',
        'password_hash',
        'role',
    ];
}

