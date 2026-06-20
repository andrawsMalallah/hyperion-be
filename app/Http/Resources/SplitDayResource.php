<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SplitDayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'split_id' => $this->split_id,
            'day_name' => $this->day_name,
            'display_order' => $this->display_order,
            'exercises' => ExerciseResource::collection($this->whenLoaded('exercises')),
            'split' => new SplitResource($this->whenLoaded('split')),
        ];
    }
}
