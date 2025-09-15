<?php

declare(strict_types=1);

namespace App\Models;

class Payment extends BaseModel
{
    protected $table = 'payments';
    protected $primaryKey = 'payment_id';

    protected $fillable = [
        'user_type',
        'user_id',
        'amount',
        'purpose',
        'payment_method',
        'transaction_status',
        'transaction_id',
    ];
}

