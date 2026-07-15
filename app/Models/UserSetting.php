<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    protected $fillable = [
        'user_id',
        'timer_enabled',
        'rest_notifications',
        'default_rest_time',
        'weight_unit',
    ];

    protected $casts = [
        'timer_enabled' => 'boolean',
        'rest_notifications' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
