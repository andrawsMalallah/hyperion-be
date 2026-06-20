<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkoutLog extends Model
{
    protected $fillable = [
        'user_id',
        'split_day_id',
        'date_timestamp',
    ];

    protected $casts = [
        'date_timestamp' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function day()
    {
        return $this->belongsTo(SplitDay::class, 'split_day_id');
    }

    public function sets()
    {
        return $this->hasMany(SetLog::class)->orderBy('set_order');
    }
}
