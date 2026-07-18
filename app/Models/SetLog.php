<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class SetLog extends Model
{
    protected $fillable = [
        'workout_log_id',
        'exercise_id',
        'weight',
        'reps',
        'duration_seconds',
        'rpe',
        'set_type',
        'set_order',
    ];

    /**
     * Bodyweight and timed sets may omit weight entirely — for those, "no added
     * weight" is 0 rather than unknown. Normalising here (instead of in the
     * controller) keeps both the store and update paths, which each hand raw
     * validated payloads to createMany(), from writing a null into a NOT NULL
     * column.
     */
    protected function weight(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value ?? 0,
        );
    }

    public function workoutLog()
    {
        return $this->belongsTo(WorkoutLog::class);
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }
}
