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
            'status' => $this->status,
            'pivot' => $this->whenPivotLoaded('day_exercise', function () {
                return [
                    'display_order' => $this->pivot->display_order,
                    'target_sets' => $this->pivot->target_sets,
                    'rep_range_min' => $this->pivot->rep_range_min,
                    'rep_range_max' => $this->pivot->rep_range_max,
                    'target_rpe' => $this->pivot->target_rpe,
                    'rest_seconds' => $this->pivot->rest_seconds,
                    'notes' => $this->pivot->notes,
                ];
            }),
        ];
    }
}
