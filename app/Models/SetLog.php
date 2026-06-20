<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SetLog extends Model
{
    protected $fillable = [
        'workout_log_id',
        'exercise_id',
        'weight',
        'reps',
        'rpe',
        'set_order',
    ];

    public function workoutLog()
    {
        return $this->belongsTo(WorkoutLog::class);
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }
}
