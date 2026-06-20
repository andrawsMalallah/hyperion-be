<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'user' => new UserResource($this->whenLoaded('user')),
            'days' => ProgramDayResource::collection($this->whenLoaded('days')),
            'created_at' => $this->created_at,
        ];
    }
}
