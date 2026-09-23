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
 * POST /api/energy-communities/{energyCommunity}/activate (BR-12)
 */
class ActivateEnergyCommunityTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private EnergyCommunity $community;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-15');

        $this->manager = User::factory()->create();
        $this->community = EnergyCommunity::factory()->withManager($this->manager)->create();
    }

    private function registration(S $state, bool $generation, string $from = '2026-01-01', ?string $to = null): EnergyCommunityMeterPoint
    {
        $meterPoint = $generation
            ? MeterPoint::factory()->generation()->create()
            : MeterPoint::factory()->consumption()->create();

        return EnergyCommunityMeterPoint::factory()
            ->for($this->community)
            ->for($meterPoint)
            ->inState($state)
            ->period($from, $to)
            ->create();
    }

    private function activate(?EnergyCommunity $community = null)
    {
        $community ??= $this->community;

        return $this->postJson("/api/energy-communities/{$community->id}/activate");
    }

    #[Test]
    public function a_manager_activates_with_an_accepted_generation_registration_valid_today(): void
    {
        $this->registration(S::Accepted, generation: true);
        Sanctum::actingAs($this->manager);

        $this->activate()->assertOk()->assertJsonPath('data.state', 'activated');
        $this->assertSame('activated', $this->community->fresh()->state->value);
    }

    #[Test]
    public function a_period_touching_today_on_either_end_counts(): void
    {
        $this->registration(S::Accepted, generation: true, from: '2026-03-15', to: '2026-03-15');
        Sanctum::actingAs($this->manager);

        $this->activate()->assertOk();
    }

    public static function notEnough(): array
    {
        return [
            'no registrations at all' => [null, true, '2026-01-01', null],
            'accepted, but consumption only' => [S::Accepted, false, '2026-01-01', null],
            'generation, but only requested' => [S::Requested, true, '2026-01-01', null],
            'generation, but message_received' => [S::MessageReceived, true, '2026-01-01', null],
            'accepted generation starting in the future' => [S::Accepted, true, '2026-04-01', null],
            'accepted generation that already ended' => [S::Accepted, true, '2025-01-01', '2026-03-14'],
            'generation, but deactivated' => [S::Deactivated, true, '2026-01-01', '2026-03-15'],
        ];
    }

    #[Test]
    #[DataProvider('notEnough')]
    public function without_an_accepted_generation_registration_valid_today_it_is_409(?S $state, bool $generation, string $from, ?string $to): void
    {
        if ($state !== null) {
            $this->registration($state, $generation, $from, $to);
        }
        Sanctum::actingAs($this->manager);

        $this->activate()->assertConflict();
        $this->assertSame('new', $this->community->fresh()->state->value);
    }

    #[Test]
    public function an_accepted_generation_registration_in_another_community_does_not_count(): void
    {
        EnergyCommunityMeterPoint::factory()
            ->for(MeterPoint::factory()->generation()->create())
            ->inState(S::Accepted)
            ->period('2026-01-01')
            ->create();
        Sanctum::actingAs($this->manager);

        $this->activate()->assertConflict();
    }

    #[Test]
    public function only_a_new_community_can_be_activated(): void
    {
        Sanctum::actingAs($this->manager);

        foreach (['activated', 'rejected'] as $state) {
            $community = EnergyCommunity::factory()->withManager($this->manager)->create(['state' => $state]);
            $this->registration(S::Accepted, generation: true)->update(['energy_community_id' => $community->id]);

            $this->activate($community)->assertConflict();
        }
    }

    #[Test]
    public function members_and_outsiders_get_403_admins_may(): void
    {
        $this->registration(S::Accepted, generation: true);

        $member = User::factory()->create();
        $this->community->memberships()->create(['user_id' => $member->id, 'role' => 'member']);

        Sanctum::actingAs($member);
        $this->activate()->assertForbidden();

        Sanctum::actingAs(User::factory()->create());
        $this->activate()->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->activate()->assertOk();
    }
}
