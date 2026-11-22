<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $fillable = [
        'morphy',
        'firstname',
        'lastname',
        'company',
        'email',
        'subscription_date',
        'subscription_end',
    ];

    protected function casts(): array
    {
        return [
            'subscription_date' => 'date:Y-m-d',
            'subscription_end' => 'date:Y-m-d',
        ];
    }
}
