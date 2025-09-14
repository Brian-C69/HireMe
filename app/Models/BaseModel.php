<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    /**
     * Allow all attributes to be mass assignable and enable timestamps.
     */
    public $timestamps = true;

    protected $guarded = [];
}
