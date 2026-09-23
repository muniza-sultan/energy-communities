<?php

namespace App\Http\Resources;

use App\Models\EnergyCommunity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EnergyCommunity
 */
class EnergyCommunityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ecid' => $this->ecid,
            'name' => $this->name,
            'state' => $this->state,
            'members' => EnergyCommunityMemberResource::collection($this->whenLoaded('memberships')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
