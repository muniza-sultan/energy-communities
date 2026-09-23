<?php

namespace Tests\Feature\Api;

use App\Actions\RejectEnergyCommunity;
use App\Enums\EnergyCommunityMeterPointState as S;
use App\Models\EnergyCommunity;
use App\Models\EnergyCommunityMeterPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * POST /api/energy-communities/{energyCommunity}/reject (BR-13)
 */
class RejectEnergyCommunityTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private EnergyCommunity $community;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-15');

        $this->manager = User::factory()->create();
        $this->community = EnergyCommunity::factory()->activated()->withManager($this->manager)->create();
    }

    private function registration(S $state, ?EnergyCommunity $community = null, ?string $to = null): EnergyCommunityMeterPoint
    {
        return EnergyCommunityMeterPoint::factory()
            ->for($community ?? $this->community)
            ->inState($state)
            ->period('2026-01-01', $to)
            ->create();
    }

    private function reject(?EnergyCommunity $community = null)
    {
        $community ??= $this->community;

        return $this->postJson("/api/energy-communities/{$community->id}/reject");
    }

    #[Test]
    public function rejecting_ends_every_blocking_registration(): void
    {
        $accepted = $this->registration(S::Accepted);
        $new = $this->registration(S::New);
        $requested = $this->registration(S::Requested);
        $messageReceived = $this->registration(S::MessageReceived);
        Sanctum::actingAs($this->manager);

        $this->reject()->assertOk()->assertJsonPath('data.state', 'rejected');

        $this->assertSame(S::Deactivated, $accepted->fresh()->state);
        $this->assertSame(S::Removed, $new->fresh()->state);
        $this->assertSame(S::Removed, $requested->fresh()->state);
        $this->assertSame(S::Removed, $messageReceived->fresh()->state);

        // BR-9: the period is closed at "today".
        $this->assertSame('2026-03-15', $accepted->fresh()->to_date->toDateString());
    }

    #[Test]
    public function non_blocking_registrations_are_left_alone(): void
    {
        // BR-13 ends *blocking* registrations; error, removed and deactivated don't block.
        $error = $this->registration(S::Error);
        $removed = $this->registration(S::Removed, to: '2026-02-01');
        Sanctum::actingAs($this->manager);

        $this->reject()->assertOk();

        $this->assertSame(S::Error, $error->fresh()->state);
        $this->assertSame(S::Removed, $removed->fresh()->state);
        $this->assertSame('2026-02-01', $removed->fresh()->to_date->toDateString());
    }

    #[Test]
    public function other_communities_are_not_touched(): void
    {
        $elsewhere = $this->registration(S::Accepted, EnergyCommunity::factory()->create());
        Sanctum::actingAs($this->manager);

        $this->reject()->assertOk();

        $this->assertSame(S::Accepted, $elsewhere->fresh()->state);
    }

    #[Test]
    public function a_new_community_can_be_rejected_too(): void
    {
        $community = EnergyCommunity::factory()->withManager($this->manager)->create();
        Sanctum::actingAs($this->manager);

        $this->reject($community)->assertOk()->assertJsonPath('data.state', 'rejected');
    }

    #[Test]
    public function an_already_rejected_community_is_409(): void
    {
        $community = EnergyCommunity::factory()->rejected()->withManager($this->manager)->create();
        Sanctum::actingAs($this->manager);

        $this->reject($community)->assertConflict();
    }

    #[Test]
    public function after_rejecting_nothing_can_be_registered_or_added(): void
    {
        Sanctum::actingAs($this->manager);
        $this->reject()->assertOk();

        $this->postJson("/api/energy-communities/{$this->community->id}/users", [
            'user_id' => User::factory()->create()->id,
            'role' => 'member',
        ])->assertConflict();
    }

    #[Test]
    public function if_any_part_fails_nothing_is_written(): void
    {
        $first = $this->registration(S::Accepted);
        $second = $this->registration(S::New);

        // Make ending the second registration fail half way through.
        EnergyCommunityMeterPoint::updating(function (EnergyCommunityMeterPoint $registration) use ($second) {
            if ($registration->id === $second->id) {
                throw new RuntimeException('simulated failure');
            }
        });

        try {
            app(RejectEnergyCommunity::class)->handle($this->community);
            $this->fail('Expected the simulated failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated failure', $e->getMessage());
        }

        // All or nothing: the community and the first registration are unchanged.
        $this->assertSame('activated', $this->community->fresh()->state->value);
        $this->assertSame(S::Accepted, $first->fresh()->state);
        $this->assertNull($first->fresh()->to_date);
        $this->assertSame(S::New, $second->fresh()->state);
    }

    #[Test]
    public function members_and_outsiders_get_403_admins_may(): void
    {
        $member = User::factory()->create();
        $this->community->memberships()->create(['user_id' => $member->id, 'role' => 'member']);

        Sanctum::actingAs($member);
        $this->reject()->assertForbidden();

        Sanctum::actingAs(User::factory()->create());
        $this->reject()->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->reject()->assertOk();
    }
}
