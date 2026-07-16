<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            // Drives the SPA's verification gate — whether to route the user to
            // the app or to the "verify your email" screen.
            'email_verified' => $this->hasVerifiedEmail(),
            // Drives the SPA's admin-only nav link + route guard.
            'is_admin' => (bool) $this->is_admin,
            'settings' => $this->whenLoaded('settings'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
