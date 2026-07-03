<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SetLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exercise_id' => $this->exercise_id,
            'weight' => $this->weight,
            'reps' => $this->reps,
            'rpe' => $this->rpe,
            'set_type' => $this->set_type,
            'set_order' => $this->set_order,
            'exercise' => new ExerciseResource($this->whenLoaded('exercise')),
        ];
    }
}
