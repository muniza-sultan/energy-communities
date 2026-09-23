<?php

namespace App\Http\Resources;

use App\Models\EnergyCommunityUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of energy_community_user: who is in the community, with which role.
 *
 * @mixin EnergyCommunityUser
 */
class EnergyCommunityMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'name' => $this->whenLoaded('user', fn () => $this->user->name),
            'role' => $this->role,
        ];
    }
}
