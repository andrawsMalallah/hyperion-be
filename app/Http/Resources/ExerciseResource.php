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
            'pivot' => $this->whenPivotLoaded('day_exercise', function () {
                return [
                    'display_order' => $this->pivot->display_order,
                ];
            }),
        ];
    }
}
