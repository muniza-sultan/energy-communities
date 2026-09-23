<?php

namespace App\Actions;

use App\Enums\EnergyCommunityState;
use App\Exceptions\ConflictException;
use App\Models\EnergyCommunity;
use Illuminate\Support\Facades\DB;

/**
 * BR-13: new|activated -> rejected, and every blocking registration ended per BR-9
 * (accepted -> deactivated, new/requested/message_received -> removed).
 * One transaction: if any part fails, nothing is written.
 */
class RejectEnergyCommunity
{
    public function __construct(private TransitionRegistration $transition) {}

    public function handle(EnergyCommunity $community): EnergyCommunity
    {
        return DB::transaction(function () use ($community) {
            // Lock order: community first. A concurrent registration into this community
            // (RegisterMeterPoint also locks the community first) waits, then sees `rejected`.
            $community = EnergyCommunity::whereKey($community->id)->lockForUpdate()->firstOrFail();

            if (! in_array($community->state, [EnergyCommunityState::New, EnergyCommunityState::Activated], true)) {
                throw new ConflictException("Only a new or activated energy community can be rejected (current state: {$community->state->value}).");
            }

            $community->update(['state' => EnergyCommunityState::Rejected]);

            // Each one goes through the same transition code as DELETE (BR-10):
            // same locks, same to_date rule. Nested transactions become savepoints,
            // so a failure anywhere rolls the whole reject back.
            $community->registrations()
                ->blocking()
                ->orderBy('id')
                ->get()
                ->each(fn ($registration) => $this->transition->end($registration));

            return $community;
        });
    }
}
