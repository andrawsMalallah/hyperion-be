<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'split_day_id' => $this->split_day_id,
            'date_timestamp' => $this->date_timestamp,
            'sets' => SetLogResource::collection($this->whenLoaded('sets')),
            'day' => new SplitDayResource($this->whenLoaded('day')),
            'created_at' => $this->created_at,
        ];
    }
}
