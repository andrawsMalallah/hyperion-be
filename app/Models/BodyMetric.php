<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BodyMetric extends Model
{
    protected $fillable = [
        'user_id',
        'weight',
        'measured_on',
    ];

    protected $casts = [
        'weight' => 'float',
        // Y-m-d only: a body weight is a calendar day, not an instant. Casting
        // to date:Y-m-d keeps the JSON free of a time/zone that could shift the
        // day back a step on the client.
        'measured_on' => 'date:Y-m-d',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
