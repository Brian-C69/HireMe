<?php

declare(strict_types=1);

namespace App\Models;

class Billing extends BaseModel
{
    protected $table = 'billing';
    protected $primaryKey = 'billing_id';

    protected $fillable = [
        'user_id',
        'user_type',
        'transaction_type',
        'amount',
        'payment_method',
        'transaction_date',
        'status',
        'reference_number',
    ];
}

