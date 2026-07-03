<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    protected $fillable = [
        'name',
        'target_muscle_group',
        'mechanics_type',
        'created_by',
        'status',
    ];
}
