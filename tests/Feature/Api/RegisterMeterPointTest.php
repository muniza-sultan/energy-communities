<?php

namespace Tests\Feature\Api;

use App\Enums\EnergyCommunityMeterPointState as S;
use App\Models\EnergyCommunity;
use App\Models\EnergyCommunityMeterPoint;
use App\Models\MeterPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * POST /api/energy-communities/{energyCommunity}/meter-points (BR-5 to BR-8)
 */
class RegisterMeterPointTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $owner;
    private EnergyCommunity $community;
    private MeterPoint $meterPoint;

    protected function setUp(): void
    {
        parent::setUp();

        // "Today" in the brief's worked example.
        Carbon::setTestNow('2026-03-15');

        $this->manager = User::factory()->create();
        $this->owner = User::factory()->create();
        $this->community = EnergyCommunity::factory()
            ->withManager($this->manager)
            ->withMember($this->owner)
            ->create();
        $this->meterPoint = MeterPoint::factory()->ownedBy($this->owner)->create();
    }

    private function register(array $overrides = [], ?EnergyCommunity $community = null)
    {
        $community ??= $this->community;

        return $this->postJson("/api/energy-communities/{$community->id}/meter-points", array_merge([
            'meter_point_id' => $this->meterPoint->id,
            'from_date' => '2026-04-01',
            'to_date' => null,
            'consent_date' => '2026-03-01',
        ], $overrides));
    }

    /** A community whose manager is also $this->manager and whose members include the owner. */
    private function otherCommunity(): EnergyCommunity
    {
        return EnergyCommunity::factory()
            ->withManager($this->manager)
            ->withMember($this->owner)
            ->create();
    }

    // ---- happy path & permissions -------------------------------------------

    #[Test]
    public function a_manager_registers_a_meter_point_in_state_new(): void
    {
        Sanctum::actingAs($this->manager);

        $this->register()
            ->assertCreated()
            ->assertJsonPath('data.state', 'new')
            ->assertJsonPath('data.energy_community_id', $this->community->id)
            ->assertJsonPath('data.meter_point_id', $this->meterPoint->id)
            ->assertJsonPath('data.from_date', '2026-04-01')
            ->assertJsonPath('data.to_date', null)
            ->assertJsonPath('data.consent_date', '2026-03-01')
            ->assertJsonPath('data.meter_point.name', $this->meterPoint->name);
    }

    #[Test]
    public function an_admin_may_register(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->register()->assertCreated();
    }

    #[Test]
    public function a_member_and_an_outsider_get_403(): void
    {
        Sanctum::actingAs($this->owner); // member, not manager
        $this->register()->assertForbidden();

        Sanctum::actingAs(User::factory()->create());
        $this->register([])->assertForbidden();

        $this->assertDatabaseCount('energy_community_meter_point', 0);
    }

    // ---- BR-5: community state ----------------------------------------------

    #[Test]
    public function a_rejected_community_is_a_409(): void
    {
        $rejected = EnergyCommunity::factory()->rejected()
            ->withManager($this->manager)->withMember($this->owner)->create();
        Sanctum::actingAs($this->manager);

        $this->register([], $rejected)->assertConflict();
    }

    #[Test]
    public function an_activated_community_accepts_registrations(): void
    {
        $activated = EnergyCommunity::factory()->activated()
            ->withManager($this->manager)->withMember($this->owner)->create();
        Sanctum::actingAs($this->manager);

        $this->register([], $activated)->assertCreated();
    }

    // ---- BR-6: owner membership and consent ---------------------------------

    #[Test]
    public function the_owner_must_be_a_member_of_the_community(): void
    {
        $strangersMeterPoint = MeterPoint::factory()->create();
        Sanctum::actingAs($this->manager);

        $this->register(['meter_point_id' => $strangersMeterPoint->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('meter_point_id');
    }

    #[Test]
    public function an_owner_who_is_a_manager_counts_as_member(): void
    {
        $managersMeterPoint = MeterPoint::factory()->ownedBy($this->manager)->create();
        Sanctum::actingAs($this->manager);

        $this->register(['meter_point_id' => $managersMeterPoint->id])->assertCreated();
    }

    #[Test]
    public function consent_today_is_allowed(): void
    {
        Sanctum::actingAs($this->manager);

        $this->register(['consent_date' => '2026-03-15'])->assertCreated();
    }

    public static function invalidPayloads(): array
    {
        return [
            'consent in the future' => [['consent_date' => '2026-03-16'], 'consent_date'],
            'consent missing' => [['consent_date' => null], 'consent_date'],
            'from_date missing' => [['from_date' => null], 'from_date'],
            'from_date not a date' => [['from_date' => '01.04.2026'], 'from_date'],
            'to_date before from_date' => [['to_date' => '2026-03-31'], 'to_date'],
            'unknown meter point' => [['meter_point_id' => 999999], 'meter_point_id'],
            'meter point missing' => [['meter_point_id' => null], 'meter_point_id'],
        ];
    }

    #[Test]
    #[DataProvider('invalidPayloads')]
    public function invalid_payloads_are_422(array $overrides, string $field): void
    {
        Sanctum::actingAs($this->manager);

        $this->register($overrides)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    #[Test]
    public function a_soft_deleted_meter_point_cannot_be_registered(): void
    {
        $this->meterPoint->delete();
        Sanctum::actingAs($this->manager);

        $this->register()->assertUnprocessable()->assertJsonValidationErrors('meter_point_id');
    }

    #[Test]
    public function a_to_date_equal_to_from_date_is_a_one_day_period(): void
    {
        Sanctum::actingAs($this->manager);

        $this->register(['to_date' => '2026-04-01'])->assertCreated();
    }

    // ---- BR-7: the worked example from the brief ----------------------------

    /**
     * MP-1: in A 2026-01-01 -> open, accepted; in B 2024-03-01 -> 2025-09-30, deactivated.
     *
     * @return array{0: EnergyCommunityMeterPoint, 1: EnergyCommunityMeterPoint}
     */
    private function workedExampleHistory(): array
    {
        $a = EnergyCommunityMeterPoint::factory()
            ->for($this->otherCommunity())
            ->for($this->meterPoint)
            ->inState(S::Accepted)
            ->period('2026-01-01')
            ->create();

        $b = EnergyCommunityMeterPoint::factory()
            ->for($this->otherCommunity())
            ->for($this->meterPoint)
            ->inState(S::Deactivated)
            ->period('2024-03-01', '2025-09-30')
            ->create();

        return [$a, $b];
    }

    public static function workedExample(): array
    {
        return [
            'row 1: from 2026-04-01, A accepted and open' => ['2026-04-01', null, false, 409],
            'row 2: A deactivated on 2026-03-15 first' => ['2026-04-01', null, true, 201],
            'row 3: from 2025-01-01 open ended' => ['2025-01-01', null, false, 409],
            'row 4: 2024-01-01 to 2024-02-28' => ['2024-01-01', '2024-02-28', false, 201],
        ];
    }

    #[Test]
    #[DataProvider('workedExample')]
    public function it_follows_the_worked_example(string $from, ?string $to, bool $deactivateAFirst, int $status): void
    {
        [$a] = $this->workedExampleHistory();

        if ($deactivateAFirst) {
            // What the transition endpoint (BR-9) will do: close the period at "today".
            $a->update(['state' => S::Deactivated, 'to_date' => '2026-03-15']);
        }

        Sanctum::actingAs($this->manager);

        $this->register(['from_date' => $from, 'to_date' => $to])->assertStatus($status);
    }

    #[Test]
    public function overlap_is_checked_across_communities_and_touching_a_day_counts(): void
    {
        EnergyCommunityMeterPoint::factory()
            ->for($this->otherCommunity())
            ->for($this->meterPoint)
            ->inState(S::New)
            ->period('2026-01-01', '2026-04-01')
            ->create();
        Sanctum::actingAs($this->manager);

        $this->register(['from_date' => '2026-04-01'])->assertConflict();
        $this->register(['from_date' => '2026-04-02'])->assertCreated();
    }

    #[Test]
    public function non_blocking_registrations_do_not_block(): void
    {
        foreach ([S::Error, S::Removed, S::Deactivated] as $state) {
            EnergyCommunityMeterPoint::factory()
                ->for($this->otherCommunity())
                ->for($this->meterPoint)
                ->inState($state)
                ->period('2026-01-01')
                ->create();
        }
        Sanctum::actingAs($this->manager);

        $this->register()->assertCreated();
    }

    #[Test]
    public function other_meter_points_do_not_block(): void
    {
        EnergyCommunityMeterPoint::factory()->inState(S::Accepted)->period('2026-01-01')->create();
        Sanctum::actingAs($this->manager);

        $this->register()->assertCreated();
    }
}
