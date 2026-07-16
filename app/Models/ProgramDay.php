<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramDay extends Model
{
    protected $fillable = [
        'program_id',
        'day_name',
        'display_order',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function exercises()
    {
        return $this->belongsToMany(Exercise::class, 'day_exercise')
            ->withPivot(['display_order', 'target_sets', 'rep_range_min', 'rep_range_max', 'target_rpe', 'rest_seconds', 'notes', 'group_type', 'group_key'])
            ->orderBy('pivot_display_order');
    }

    public function workoutLogs()
    {
        return $this->hasMany(WorkoutLog::class, 'program_day_id');
    }
}
