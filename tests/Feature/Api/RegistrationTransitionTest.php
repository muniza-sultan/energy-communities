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
 * POST /api/registrations/{registration}/transition (BR-9)
 * DELETE /api/registrations/{registration} (BR-10)
 */
class RegistrationTransitionTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $member;
    private EnergyCommunity $community;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-15');

        $this->manager = User::factory()->create();
        $this->member = User::factory()->create();
        $this->community = EnergyCommunity::factory()
            ->withManager($this->manager)
            ->withMember($this->member)
            ->create();
    }

    private function registration(S $state, string $from = '2026-01-01', ?string $to = null, array $extra = []): EnergyCommunityMeterPoint
    {
        return EnergyCommunityMeterPoint::factory()
            ->for($this->community)
            ->inState($state)
            ->period($from, $to)
            ->create($extra);
    }

    private function transition(EnergyCommunityMeterPoint $registration, array $body)
    {
        return $this->postJson("/api/registrations/{$registration->id}/transition", $body);
    }

    // ---- the happy path -----------------------------------------------------

    #[Test]
    public function a_registration_walks_to_accepted(): void
    {
        $registration = $this->registration(S::New);
        Sanctum::actingAs($this->manager);

        foreach (['requested', 'message_received', 'accepted'] as $state) {
            $this->transition($registration, ['state' => $state])
                ->assertOk()
                ->assertJsonPath('data.state', $state)
                ->assertJsonPath('data.to_date', null);
        }
    }

    // ---- BR-9: everything not allowed is a conflict -------------------------

    public static function forbiddenTransitions(): array
    {
        return [
            'new -> accepted' => [S::New, 'accepted'],
            'new -> new' => [S::New, 'new'],
            'requested -> accepted' => [S::Requested, 'accepted'],
            'accepted -> removed' => [S::Accepted, 'removed'],
            'accepted -> requested' => [S::Accepted, 'requested'],
            'removed -> requested' => [S::Removed, 'requested'],
            'deactivated -> accepted' => [S::Deactivated, 'accepted'],
        ];
    }

    #[Test]
    #[DataProvider('forbiddenTransitions')]
    public function forbidden_transitions_are_409(S $from, string $to): void
    {
        $registration = $this->registration($from);
        Sanctum::actingAs($this->manager);

        $this->transition($registration, ['state' => $to])->assertConflict();

        $this->assertSame($from, $registration->fresh()->state);
    }

    // ---- status_code --------------------------------------------------------

    #[Test]
    public function error_carries_a_status_code_and_retry_clears_it(): void
    {
        $registration = $this->registration(S::Requested);
        Sanctum::actingAs($this->manager);

        $this->transition($registration, ['state' => 'error', 'status_code' => 4711])
            ->assertOk()
            ->assertJsonPath('data.state', 'error')
            ->assertJsonPath('data.status_code', 4711);

        $this->transition($registration, ['state' => 'requested'])
            ->assertOk()
            ->assertJsonPath('data.status_code', null);
    }

    #[Test]
    public function error_without_status_code_is_422(): void
    {
        $registration = $this->registration(S::Requested);
        Sanctum::actingAs($this->manager);

        $this->transition($registration, ['state' => 'error'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status_code');
    }

    #[Test]
    public function status_code_on_another_transition_is_422(): void
    {
        $registration = $this->registration(S::New);
        Sanctum::actingAs($this->manager);

        $this->transition($registration, ['state' => 'requested', 'status_code' => 4711])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status_code');
    }

    #[Test]
    public function an_unknown_state_is_422(): void
    {
        $registration = $this->registration(S::New);
        Sanctum::actingAs($this->manager);

        $this->transition($registration, ['state' => 'approved'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('state');
    }

    // ---- BR-9: closing the period -------------------------------------------

    #[Test]
    public function a_terminal_state_sets_to_date_to_today(): void
    {
        $registration = $this->registration(S::Accepted, '2026-01-01');
        Sanctum::actingAs($this->manager);

        $this->transition($registration, ['state' => 'deactivated'])
            ->assertOk()
            ->assertJsonPath('data.to_date', '2026-03-15');
    }

    #[Test]
    public function an_earlier_to_date_is_kept(): void
    {
        $registration = $this->registration(S::Accepted, '2025-01-01', '2025-12-31');
        Sanctum::actingAs($this->manager);

        $this->transition($registration, ['state' => 'deactivated'])
            ->assertJsonPath('data.to_date', '2025-12-31');
    }

    #[Test]
    public function a_later_to_date_is_cut_to_today(): void
    {
        $registration = $this->registration(S::Accepted, '2026-01-01', '2026-12-31');
        Sanctum::actingAs($this->manager);

        $this->transition($registration, ['state' => 'deactivated'])
            ->assertJsonPath('data.to_date', '2026-03-15');
    }

    #[Test]
    public function to_date_is_never_before_from_date(): void
    {
        // Starts in the future; removed today -> to_date = from_date.
        $registration = $this->registration(S::New, '2026-06-01');
        Sanctum::actingAs($this->manager);

        $this->transition($registration, ['state' => 'removed'])
            ->assertJsonPath('data.to_date', '2026-06-01');
    }

    // ---- BR-7 on retry ------------------------------------------------------

    #[Test]
    public function retry_into_an_overlap_is_409(): void
    {
        $errored = $this->registration(S::Error, '2026-01-01', null, ['status_code' => 4711]);

        // While it was in error (non-blocking), the metering point got registered elsewhere.
        EnergyCommunityMeterPoint::factory()
            ->for($errored->meterPoint)
            ->inState(S::New)
            ->period('2026-02-01')
            ->create();

        Sanctum::actingAs($this->manager);

        $this->transition($errored, ['state' => 'requested'])->assertConflict();
        $this->assertSame(S::Error, $errored->fresh()->state);
    }

    #[Test]
    public function retry_without_overlap_works(): void
    {
        $errored = $this->registration(S::Error, '2026-01-01', null, ['status_code' => 4711]);
        Sanctum::actingAs($this->manager);

        $this->transition($errored, ['state' => 'requested'])
            ->assertOk()
            ->assertJsonPath('data.state', 'requested');
    }

    // ---- permissions --------------------------------------------------------

    #[Test]
    public function members_and_outsiders_get_403_admins_may(): void
    {
        $registration = $this->registration(S::New);

        Sanctum::actingAs($this->member);
        $this->transition($registration, ['state' => 'requested'])->assertForbidden();
        $this->deleteJson("/api/registrations/{$registration->id}")->assertForbidden();

        Sanctum::actingAs(User::factory()->create());
        $this->transition($registration, [])->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->transition($registration, ['state' => 'requested'])->assertOk();
    }

    #[Test]
    public function unknown_registration_is_404(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/registrations/999999/transition', ['state' => 'requested'])->assertNotFound();
        $this->deleteJson('/api/registrations/999999')->assertNotFound();
    }

    // ---- DELETE (BR-10) -----------------------------------------------------

    #[Test]
    public function deleting_an_accepted_registration_deactivates_it(): void
    {
        $registration = $this->registration(S::Accepted);
        Sanctum::actingAs($this->manager);

        $this->deleteJson("/api/registrations/{$registration->id}")->assertNoContent();

        $fresh = $registration->fresh();
        $this->assertNotNull($fresh, 'Never hard-deleted.');
        $this->assertSame(S::Deactivated, $fresh->state);
        $this->assertSame('2026-03-15', $fresh->to_date->toDateString());
    }

    public static function removableStates(): array
    {
        return [
            'new' => [S::New],
            'requested' => [S::Requested],
            'message_received' => [S::MessageReceived],
            'error' => [S::Error],
        ];
    }

    #[Test]
    #[DataProvider('removableStates')]
    public function deleting_an_unaccepted_registration_removes_it(S $state): void
    {
        $registration = $this->registration($state);
        Sanctum::actingAs($this->manager);

        $this->deleteJson("/api/registrations/{$registration->id}")->assertNoContent();

        $this->assertSame(S::Removed, $registration->fresh()->state);
    }

    #[Test]
    public function deleting_an_ended_registration_is_409(): void
    {
        $registration = $this->registration(S::Removed, '2026-01-01', '2026-02-01');
        Sanctum::actingAs($this->manager);

        $this->deleteJson("/api/registrations/{$registration->id}")->assertConflict();
    }

    // ---- worked example, row 2, end to end ----------------------------------

    #[Test]
    public function after_deactivating_a_the_meter_point_can_join_c(): void
    {
        $owner = User::factory()->create();
        $meterPoint = MeterPoint::factory()->ownedBy($owner)->create();
        $a = EnergyCommunityMeterPoint::factory()
            ->for($this->community)
            ->for($meterPoint)
            ->inState(S::Accepted)
            ->period('2026-01-01')
            ->create();
        $c = EnergyCommunity::factory()->withManager($this->manager)->withMember($owner)->create();

        Sanctum::actingAs($this->manager);

        $body = [
            'meter_point_id' => $meterPoint->id,
            'from_date' => '2026-04-01',
            'to_date' => null,
            'consent_date' => '2026-03-01',
        ];

        $this->postJson("/api/energy-communities/{$c->id}/meter-points", $body)->assertConflict();
        $this->deleteJson("/api/registrations/{$a->id}")->assertNoContent();
        $this->postJson("/api/energy-communities/{$c->id}/meter-points", $body)->assertCreated();
    }
}
