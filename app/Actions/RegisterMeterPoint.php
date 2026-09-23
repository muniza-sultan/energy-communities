<?php

namespace App\Actions;

use App\Enums\EnergyCommunityMeterPointState;
use App\Exceptions\ConflictException;
use App\Models\EnergyCommunity;
use App\Models\EnergyCommunityMeterPoint;
use App\Models\MeterPoint;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registers a metering point into an energy community (BR-5 to BR-8).
 *
 */
class RegisterMeterPoint
{
    /** SQLSTATE for exclusion_violation (the BR-7 constraint). */
    private const EXCLUSION_VIOLATION = '23P01';

    public function handle(
        EnergyCommunity $community,
        MeterPoint $meterPoint,
        CarbonImmutable $fromDate,
        ?CarbonImmutable $toDate,
        CarbonImmutable $consentDate,
    ): EnergyCommunityMeterPoint {
        try {
            return DB::transaction(function () use ($community, $meterPoint, $fromDate, $toDate, $consentDate) {
                // BR-13
                $community = EnergyCommunity::whereKey($community->id)->lockForUpdate()->firstOrFail();

                // BR-5: only while new or activated.
                if (! $community->state->acceptsChanges()) {
                    throw new ConflictException('Metering points cannot be registered into a rejected energy community.');
                }

                // BR-8: lock the metering point, so concurrent registrations of the
                // same metering point run one after the other through the check below.
                $meterPoint = MeterPoint::whereKey($meterPoint->id)->lockForUpdate()->firstOrFail();

                // BR-6: the owner must be a member (either role) of the community.
                if (! $community->hasMember($meterPoint->owner)) {
                    throw ValidationException::withMessages([
                        'meter_point_id' => ['The owner of this metering point is not a member of the energy community.'],
                    ]);
                }

                // BR-7: no overlapping blocking registration, accross different communities.
                $overlaps = EnergyCommunityMeterPoint::query()
                    ->where('meter_point_id', $meterPoint->id)
                    ->blocking()
                    ->overlapping($fromDate, $toDate)
                    ->exists();

                if ($overlaps) {
                    throw $this->overlapConflict();
                }

                return EnergyCommunityMeterPoint::create([
                    'energy_community_id' => $community->id,
                    'meter_point_id' => $meterPoint->id,
                    'state' => EnergyCommunityMeterPointState::New, // BR-6
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'consent_date' => $consentDate,
                ]);
            });
        } catch (QueryException $e) {
            
            if ($e->getCode() === self::EXCLUSION_VIOLATION) {
                throw $this->overlapConflict();
            }

            throw $e;
        }
    }

    private function overlapConflict(): ConflictException
    {
        return new ConflictException(
            'This metering point already has a blocking registration whose period overlaps the requested one.'
        );
    }
}
