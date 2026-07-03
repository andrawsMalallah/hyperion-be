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
            'program_day_id' => $this->program_day_id,
            'date_timestamp' => $this->date_timestamp,
            'ended_at' => $this->ended_at,
            'notes' => $this->notes,
            'sets' => SetLogResource::collection($this->whenLoaded('sets')),
            'day' => new ProgramDayResource($this->whenLoaded('day')),
            'created_at' => $this->created_at,
        ];
    }
}
