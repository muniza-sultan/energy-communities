<?php

namespace App\Actions;

use App\Enums\EnergyCommunityUserRole;
use App\Exceptions\ConflictException;
use App\Models\EnergyCommunity;
use App\Models\EnergyCommunityUser;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;


class AddUserToEnergyCommunity
{
    public function handle(EnergyCommunity $community, User $user, EnergyCommunityUserRole $role): EnergyCommunityUser
    {
        try {
            return DB::transaction(function () use ($community, $user, $role) {
                
                $community = EnergyCommunity::whereKey($community->id)->lockForUpdate()->firstOrFail();

                // cannot add user to a reject community
                if (! $community->state->acceptsChanges()) {
                    throw new ConflictException('Users cannot be added to a rejected energy community.');
                }

                return $community->memberships()->create([
                    'user_id' => $user->id,
                    'role' => $role,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // Two concurrent requests for the same user: the unique index
            // (energy_community_id, user_id) stopped the second one.
            throw ValidationException::withMessages([
                'user_id' => ['The user already exists in this energy community.'],
            ]);
        }
    }
}
