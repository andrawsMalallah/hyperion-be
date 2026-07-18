<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BodyMetricResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Canonical kilograms; float so the client's chart math and unit
            // conversion don't have to parse a decimal string.
            'weight' => (float) $this->weight,
            // Format here, not via the model cast: handing a Carbon to a
            // Resource serializes it as a full ISO timestamp regardless of the
            // cast, and a body weight is a calendar day (no time/zone to shift
            // it back a step on the client).
            'measured_on' => $this->measured_on->toDateString(),
        ];
    }
}
