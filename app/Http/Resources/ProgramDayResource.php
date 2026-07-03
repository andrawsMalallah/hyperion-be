<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramDayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'program_id' => $this->program_id,
            'day_name' => $this->day_name,
            'display_order' => $this->display_order,
            'exercises' => ExerciseResource::collection($this->whenLoaded('exercises')),
            'program' => new ProgramResource($this->whenLoaded('program')),
        ];
    }
}
