<?php

namespace App\Policies;

use App\Models\EnergyCommunityMeterPoint;
use App\Models\User;

/**
 * Registrations. Permissions follow the registration's community.
 */
class EnergyCommunityMeterPointPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_admin ? true : null;
    }

    /** BR-11: members of the community see its registrations. */
    public function view(User $user, EnergyCommunityMeterPoint $registration): bool
    {
        return $registration->energyCommunity->hasMember($user);
    }

    /** BR-9: a manager of the community triggers transitions by hand. */
    public function transition(User $user, EnergyCommunityMeterPoint $registration): bool
    {
        return $registration->energyCommunity->hasManager($user);
    }

    /** BR-10: "deleting" is the ending transition, so the same rule applies. */
    public function delete(User $user, EnergyCommunityMeterPoint $registration): bool
    {
        return $this->transition($user, $registration);
    }
}
