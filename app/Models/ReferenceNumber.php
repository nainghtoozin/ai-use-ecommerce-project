<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferenceNumber extends Model
{
    protected $fillable = [
        'prefix',
        'date',
        'last_sequence',
    ];

    protected $casts = [
        'date' => 'date',
        'last_sequence' => 'integer',
    ];
}
