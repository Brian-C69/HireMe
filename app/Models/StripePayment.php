<?php

declare(strict_types=1);

namespace App\Models;

class StripePayment extends BaseModel
{
    protected $table = 'stripe_payments';

    protected $fillable = [
        'user_id',
        'user_role',
        'purpose',
        'credits',
        'amount',
        'currency',
        'session_id',
        'status',
        'payment_intent',
        'payload',
    ];
}

