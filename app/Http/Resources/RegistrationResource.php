<?php

namespace App\Http\Resources;

use App\Models\EnergyCommunityMeterPoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A registration (energy_community_meter_point row).
 *
 * @mixin EnergyCommunityMeterPoint
 */
class RegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'energy_community_id' => $this->energy_community_id,
            'meter_point_id' => $this->meter_point_id,
            'meter_point' => MeterPointResource::make($this->whenLoaded('meterPoint')),
            'state' => $this->state,
            'from_date' => $this->from_date?->toDateString(),
            'to_date' => $this->to_date?->toDateString(),
            'consent_date' => $this->consent_date?->toDateString(),
            'status_code' => $this->status_code,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
