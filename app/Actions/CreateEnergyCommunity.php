<?php

namespace App\Actions;

use App\Enums\EnergyCommunityState;
use App\Enums\EnergyCommunityUserRole;
use App\Models\EnergyCommunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * BR-3: the creator becomes a manager, the community starts in state `new`,
 * and both rows are written in one transaction (all or nothing).
 */
class CreateEnergyCommunity
{
    /**
     * @param  array{ecid: string, name?: string|null}  $attributes
     */
    public function handle(User $creator, array $attributes): EnergyCommunity
    {
        return DB::transaction(function () use ($creator, $attributes) {
            $community = EnergyCommunity::create([
                'ecid' => $attributes['ecid'],
                'name' => $attributes['name'] ?? null,
                'state' => EnergyCommunityState::New,
            ]);

            $community->memberships()->create([
                'user_id' => $creator->id,
                'role' => EnergyCommunityUserRole::Manager,
            ]);

            return $community;
        });
    }
}
