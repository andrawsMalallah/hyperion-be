<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'target_muscle_group' => $this->target_muscle_group,
            'mechanics_type' => $this->mechanics_type,
            // Drives which inputs the workout screen shows for this exercise
            // (weight x reps, reps + optional added weight, or a duration).
            'measurement_type' => $this->measurement_type,
            'status' => $this->status,
            // Present on the contributor's own list and the admin dashboard so a
            // rejected submission can show why it wasn't accepted.
            'rejection_reason' => $this->rejection_reason,
            // Contributor id + name for the admin dashboard (only when
            // eager-loaded; null for seeded/de-identified rows). The id powers
            // the dashboard's "filter by this contributor" action.
            'contributor' => $this->whenLoaded('contributor', fn () => $this->contributor ? [
                'id' => $this->contributor->id,
                'name' => $this->contributor->name,
            ] : null),
            'pivot' => $this->whenPivotLoaded('day_exercise', function () {
                return [
                    'display_order' => $this->pivot->display_order,
                    'target_sets' => $this->pivot->target_sets,
                    'rep_range_min' => $this->pivot->rep_range_min,
                    'rep_range_max' => $this->pivot->rep_range_max,
                    'target_rpe' => $this->pivot->target_rpe,
                    'rest_seconds' => $this->pivot->rest_seconds,
                    'notes' => $this->pivot->notes,
                    // How the exercise is performed, and (for supersets / giant
                    // sets) which group of the day it belongs to.
                    'group_type' => $this->pivot->group_type,
                    'group_key' => $this->pivot->group_key,
                ];
            }),
        ];
    }
}
