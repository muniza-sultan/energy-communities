<?php

namespace Tests\Feature\Database;

use App\Enums\EnergyCommunityMeterPointState as S;
use App\Models\EnergyCommunityMeterPoint;
use App\Models\MeterPoint;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * BR-7 / BR-8 at the database level: these bypass all application checks on purpose.
 */
class RegistrationOverlapConstraintTest extends TestCase
{
    use RefreshDatabase;

    private MeterPoint $meterPoint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->meterPoint = MeterPoint::factory()->create();
    }

    private function register(S $state, string $from, ?string $to = null): EnergyCommunityMeterPoint
    {
        return EnergyCommunityMeterPoint::factory()
            ->for($this->meterPoint)
            ->inState($state)
            ->period($from, $to)
            ->create();
    }

    private function assertRejectedAsOverlap(callable $insert): void
    {
        try {
            $insert();
            $this->fail('Expected the exclusion constraint to reject the insert.');
        } catch (QueryException $e) {
            $this->assertSame('23P01', $e->getCode()); // exclusion_violation
        }
    }

    #[Test]
    public function two_open_ended_blocking_registrations_are_rejected(): void
    {
        $this->register(S::Accepted, '2026-01-01');

        $this->assertRejectedAsOverlap(fn () => $this->register(S::New, '2026-04-01'));
    }

    #[Test]
    public function inclusive_bounds_touching_on_one_day_overlap(): void
    {
        $this->register(S::Accepted, '2026-01-01', '2026-03-31');

        $this->assertRejectedAsOverlap(fn () => $this->register(S::New, '2026-03-31'));
    }

    #[Test]
    public function adjacent_periods_do_not_overlap(): void
    {
        $this->register(S::Accepted, '2026-01-01', '2026-03-31');
        $this->register(S::New, '2026-04-01');

        $this->assertSame(2, $this->meterPoint->registrations()->count());
    }

    #[Test]
    public function non_blocking_registrations_are_ignored(): void
    {
        $this->register(S::Deactivated, '2026-01-01');
        $this->register(S::Removed, '2026-01-01');
        $this->register(S::Error, '2026-01-01');
        $this->register(S::New, '2026-01-01');

        $this->assertSame(4, $this->meterPoint->registrations()->count());
    }

    #[Test]
    public function moving_a_non_blocking_row_back_into_a_blocking_state_is_rejected(): void
    {
        // error -> requested (retry) must not sneak past BR-7.
        $errored = $this->register(S::Error, '2026-01-01');
        $this->register(S::New, '2026-02-01');

        $this->assertRejectedAsOverlap(fn () => $errored->update(['state' => S::Requested]));
    }

    #[Test]
    public function other_metering_points_are_independent(): void
    {
        $this->register(S::Accepted, '2026-01-01');

        EnergyCommunityMeterPoint::factory()->inState(S::Accepted)->period('2026-01-01')->create();

        $this->assertSame(2, EnergyCommunityMeterPoint::count());
    }

    #[Test]
    public function to_date_before_from_date_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        $this->register(S::New, '2026-05-01', '2026-04-01');
    }

    #[Test]
    public function overlapping_scope_matches_the_worked_example(): void
    {
        $this->register(S::Accepted, '2026-01-01');                 // A
        $this->register(S::Deactivated, '2024-03-01', '2025-09-30'); // B

        $blockingOverlaps = fn (string $from, ?string $to) => $this->meterPoint->registrations()
            ->blocking()->overlapping($from, $to)->count();

        $this->assertSame(1, $blockingOverlaps('2026-04-01', null));   // 409
        $this->assertSame(1, $blockingOverlaps('2025-01-01', null));   // 409
        $this->assertSame(0, $blockingOverlaps('2024-01-01', '2024-02-28')); // 201
    }

    #[Test]
    public function meter_point_resolves_its_grid_operator_by_identifier(): void
    {
        $operator = $this->meterPoint->gridOperator;

        $this->assertNotNull($operator);
        $this->assertSame(substr($this->meterPoint->name, 0, 8), $operator->identifier);
        $this->assertTrue($operator->meterPoints->contains($this->meterPoint));
    }
}
