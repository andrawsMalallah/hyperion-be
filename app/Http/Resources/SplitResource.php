<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SplitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'split_name' => $this->split_name,
            'is_active' => $this->is_active,
            'user' => new UserResource($this->whenLoaded('user')),
            'days' => SplitDayResource::collection($this->whenLoaded('days')),
            'created_at' => $this->created_at,
        ];
    }
}
