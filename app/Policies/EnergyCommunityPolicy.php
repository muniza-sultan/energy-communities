<?php

namespace App\Policies;

use App\Models\EnergyCommunity;
use App\Models\User;

/**
 * Who may do what with a community. Only answers "who" (403).
 * State rules (e.g. "not when rejected") live in the actions and return 409.
 */
class EnergyCommunityPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_admin ? true : null;
    }

    /** BR-3: any authenticated user may create a community. */
    public function create(User $user): bool
    {
        return true;
    }

    /** BR-11: members (either role) may view. */
    public function view(User $user, EnergyCommunity $energyCommunity): bool
    {
        return $energyCommunity->hasMember($user);
    }

    /** BR-4 */
    public function addUser(User $user, EnergyCommunity $energyCommunity): bool
    {
        return $energyCommunity->hasManager($user);
    }

    /** BR-5 */
    public function registerMeterPoint(User $user, EnergyCommunity $energyCommunity): bool
    {
        return $energyCommunity->hasManager($user);
    }

    /** BR-12 */
    public function activate(User $user, EnergyCommunity $energyCommunity): bool
    {
        return $energyCommunity->hasManager($user);
    }

    /** BR-13 */
    public function reject(User $user, EnergyCommunity $energyCommunity): bool
    {
        return $energyCommunity->hasManager($user);
    }
}
