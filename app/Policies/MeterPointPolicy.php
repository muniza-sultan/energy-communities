<?php

namespace App\Policies;

use App\Models\MeterPoint;
use App\Models\User;

/**
 * BR-2: through the metering-point endpoints only the owner and admins
 * may read or modify a metering point.
 */
class MeterPointPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_admin ? true : null;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, MeterPoint $meterPoint): bool
    {
        return $meterPoint->user_id === $user->id;
    }

    public function update(User $user, MeterPoint $meterPoint): bool
    {
        return $meterPoint->user_id === $user->id;
    }
}
