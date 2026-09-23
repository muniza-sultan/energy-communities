<?php

namespace Tests\Feature\Api;

use App\Enums\EnergyCommunityMeterPointState as S;
use App\Models\EnergyCommunity;
use App\Models\EnergyCommunityMeterPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * GET /api/energy-communities/{energyCommunity}/meter-points (BR-11)
 */
class ListRegistrationsTest extends TestCase
{
    use RefreshDatabase;

    private User $member;
    private EnergyCommunity $community;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = User::factory()->create();
        $this->community = EnergyCommunity::factory()->withMember($this->member)->create();
    }

    private function list(string $query = '')
    {
        return $this->getJson("/api/energy-communities/{$this->community->id}/meter-points{$query}");
    }

    #[Test]
    public function members_see_only_this_communitys_registrations(): void
    {
        $ours = EnergyCommunityMeterPoint::factory()->for($this->community)->count(2)->create();
        EnergyCommunityMeterPoint::factory()->count(2)->create(); // other communities
        Sanctum::actingAs($this->member);

        $response = $this->list()->assertOk();

        $this->assertEqualsCanonicalizing(
            $ours->pluck('id')->all(),
            collect($response->json('data'))->pluck('id')->all(),
        );
        $response->assertJsonStructure(['data' => [['meter_point' => ['name', 'energy_direction']]]]);
    }

    #[Test]
    public function the_list_is_filterable_by_state(): void
    {
        $accepted = EnergyCommunityMeterPoint::factory()->for($this->community)->inState(S::Accepted)->create();
        EnergyCommunityMeterPoint::factory()->for($this->community)->inState(S::New)->create();
        Sanctum::actingAs($this->member);

        $this->list('?state=accepted')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $accepted->id);
    }

    #[Test]
    public function an_invalid_state_filter_is_a_422(): void
    {
        Sanctum::actingAs($this->member);

        $this->list('?state=foo')->assertUnprocessable()->assertJsonValidationErrors('state');
    }

    #[Test]
    public function outsiders_get_403_and_admins_see_it(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->list()->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->list()->assertOk();
    }

    #[Test]
    public function registrations_of_a_soft_deleted_meter_point_stay_listed(): void
    {
        $registration = EnergyCommunityMeterPoint::factory()->for($this->community)->create();
        $registration->meterPoint->delete();
        Sanctum::actingAs($this->member);

        $this->list()
            ->assertOk()
            ->assertJsonPath('data.0.meter_point.id', $registration->meter_point_id);
    }
}
