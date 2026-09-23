<?php

namespace App\Actions;

use App\Enums\EnergyCommunityMeterPointState as State;
use App\Exceptions\ConflictException;
use App\Exceptions\RegistrationAlreadyEnded;
use App\Models\EnergyCommunityMeterPoint;
use App\Models\MeterPoint;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Applies one BR-9 transition to a registration. Used by the transition endpoint,
 * by DELETE (BR-10) and by rejecting a community (BR-13).
 *
 * BR-8: the registration row is locked and its state re-read before the check,
 * so two concurrent transitions out of the same state run one after the other;
 * the second sees the new state and gets a 409.
 */
class TransitionRegistration
{
    /** SQLSTATE for exclusion_violation (the BR-7 constraint). */
    private const EXCLUSION_VIOLATION = '23P01';

    /**
     * Move the registration to $target (transition endpoint).
     */
    public function handle(EnergyCommunityMeterPoint $registration, State $target, ?int $statusCode = null): EnergyCommunityMeterPoint
    {
        return $this->apply($registration, fn (State $current) => $target, $statusCode);
    }

    /**
     * End the registration (BR-10 DELETE, BR-13 reject): accepted -> deactivated,
     * earlier states -> removed. The end state is decided from the state read
     * *after* the lock, so a concurrent change can't make us pick a stale one.
     *
     * @return EnergyCommunityMeterPoint|null null if it had already ended
     */
    public function end(EnergyCommunityMeterPoint $registration): ?EnergyCommunityMeterPoint
    {
        try {
            return $this->apply(
                $registration,
                fn (State $current) => $current->endState() ?? throw new RegistrationAlreadyEnded,
            );
        } catch (RegistrationAlreadyEnded) {
            return null;
        }
    }

    /**
     * @param  callable(State): State  $resolveTarget  gets the current (locked) state, returns the target
     */
    private function apply(EnergyCommunityMeterPoint $registration, callable $resolveTarget, ?int $statusCode = null): EnergyCommunityMeterPoint
    {
        try {
            return DB::transaction(function () use ($registration, $resolveTarget, $statusCode) {
                // Lock order: metering point -> registration (see NOTES.md).
                // withTrashed: a deleted metering point's registration can still be ended.
                MeterPoint::withTrashed()->whereKey($registration->meter_point_id)->lockForUpdate()->first();

                $registration = EnergyCommunityMeterPoint::whereKey($registration->id)->lockForUpdate()->firstOrFail();
                $current = $registration->state;
                $target = $resolveTarget($current);

                if (! $current->canTransitionTo($target)) {
                    throw new ConflictException("A registration cannot move from {$current->value} to {$target->value}.");
                }

                // error -> requested makes a non-blocking registration blocking again,
                // so BR-7 has to be checked again.
                if ($target->isBlocking() && ! $current->isBlocking()) {
                    $this->assertNoOverlap($registration);
                }

                $registration->state = $target;

                // Decision: status_code describes the current error only; cleared when leaving `error`.
                $registration->status_code = $target === State::Error ? $statusCode : null;

                if ($target->isTerminal()) {
                    $registration->to_date = $this->closingDate($registration);
                }

                $registration->save();

                return $registration;
            });
        } catch (QueryException $e) {
            if ($e->getCode() === self::EXCLUSION_VIOLATION) {
                throw $this->overlapConflict();
            }

            throw $e;
        }
    }

    /**
     * BR-9: to_date becomes today, unless it already holds an earlier date,
     * and never a date before from_date.
     */
    private function closingDate(EnergyCommunityMeterPoint $registration): CarbonImmutable
    {
        $today = CarbonImmutable::today();
        $from = CarbonImmutable::parse($registration->from_date);
        $to = $registration->to_date ? CarbonImmutable::parse($registration->to_date) : null;

        $end = ($to !== null && $to->lessThan($today)) ? $to : $today;

        return $end->lessThan($from) ? $from : $end;
    }

    private function assertNoOverlap(EnergyCommunityMeterPoint $registration): void
    {
        $overlaps = EnergyCommunityMeterPoint::query()
            ->where('meter_point_id', $registration->meter_point_id)
            ->whereKeyNot($registration->id)
            ->blocking()
            ->overlapping($registration->from_date, $registration->to_date)
            ->exists();

        if ($overlaps) {
            throw $this->overlapConflict();
        }
    }

    private function overlapConflict(): ConflictException
    {
        return new ConflictException(
            'This metering point already has a blocking registration whose period overlaps this one.'
        );
    }
}
