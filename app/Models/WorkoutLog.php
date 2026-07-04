<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkoutLog extends Model
{
    protected $fillable = [
        'client_uuid',
        'program_day_id',
        'date_timestamp',
        'ended_at',
        'notes',
    ];

    protected $casts = [
        'date_timestamp' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function day()
    {
        return $this->belongsTo(ProgramDay::class, 'program_day_id');
    }

    public function sets()
    {
        return $this->hasMany(SetLog::class)->orderBy('set_order');
    }
}
