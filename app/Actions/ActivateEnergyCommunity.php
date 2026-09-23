<?php

namespace App\Actions;

use App\Enums\EnergyCommunityMeterPointState;
use App\Enums\EnergyCommunityState;
use App\Enums\EnergyDirection;
use App\Exceptions\ConflictException;
use App\Models\EnergyCommunity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * BR-12: new -> activated, only with at least one accepted registration of a
 * generation metering point. Decision: that registration must be valid *today*
 * (from_date <= today <= to_date, or open ended).
 */
class ActivateEnergyCommunity
{
    public function handle(EnergyCommunity $community): EnergyCommunity
    {
        return DB::transaction(function () use ($community) {
            // Lock order: community first.
            $community = EnergyCommunity::whereKey($community->id)->lockForUpdate()->firstOrFail();

            if ($community->state !== EnergyCommunityState::New) {
                throw new ConflictException("Only a new energy community can be activated (current state: {$community->state->value}).");
            }

            $today = CarbonImmutable::today();

            // Locking the matching registration too: a concurrent transition can't
            // end it between this check and the state change below.
            $generator = $community->registrations()
                ->where('state', EnergyCommunityMeterPointState::Accepted)
                ->overlapping($today, $today) // valid today
                ->whereHas('meterPoint', fn (Builder $q) => $q->where('energy_direction', EnergyDirection::Generation))
                ->lockForUpdate()
                ->first();

            if ($generator === null) {
                throw new ConflictException(
                    'An energy community can only be activated with an accepted registration of a generation metering point that is valid today.'
                );
            }

            $community->update(['state' => EnergyCommunityState::Activated]);

            return $community;
        });
    }
}
