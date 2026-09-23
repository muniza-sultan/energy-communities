<?php

namespace Tests\Feature\Api;

use App\Models\EnergyCommunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnergyCommunityTest extends TestCase
{
    use RefreshDatabase;

    private const ECID = 'AT00700009020GC999001000000000001';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    // ---- POST /api/energy-communities ---------------------------------------

    #[Test]
    public function the_creator_becomes_manager_and_the_state_is_new(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/energy-communities', [
            'ecid' => self::ECID,
            'name' => 'PV Sonnenweide',
        ])
            ->assertCreated()
            ->assertJsonPath('data.ecid', self::ECID)
            ->assertJsonPath('data.name', 'PV Sonnenweide')
            ->assertJsonPath('data.state', 'new')
            ->assertJsonPath('data.members.0.user_id', $this->user->id)
            ->assertJsonPath('data.members.0.role', 'manager');

        $this->assertDatabaseHas('energy_community_user', [
            'energy_community_id' => $response->json('data.id'),
            'user_id' => $this->user->id,
            'role' => 'manager',
        ]);
    }

    #[Test]
    public function a_state_in_the_body_is_ignored(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/energy-communities', [
            'ecid' => self::ECID,
            'state' => 'activated',
        ])->assertCreated()->assertJsonPath('data.state', 'new');
    }

    #[Test]
    public function the_name_is_optional(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/energy-communities', ['ecid' => self::ECID])
            ->assertCreated()
            ->assertJsonPath('data.name', null);
    }

    #[Test]
    public function the_ecid_is_required_and_unique(): void
    {
        EnergyCommunity::factory()->create(['ecid' => self::ECID]);
        Sanctum::actingAs($this->user);

        $this->postJson('/api/energy-communities', [])
            ->assertUnprocessable()->assertJsonValidationErrors('ecid');

        $this->postJson('/api/energy-communities', ['ecid' => self::ECID])
            ->assertUnprocessable()->assertJsonValidationErrors('ecid');
    }

    // ---- GET /api/energy-communities ----------------------------------------

    #[Test]
    public function a_user_sees_only_communities_they_belong_to(): void
    {
        $managed = EnergyCommunity::factory()->withManager($this->user)->create();
        $joined = EnergyCommunity::factory()->withMember($this->user)->create();
        EnergyCommunity::factory()->count(2)->create();
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/energy-communities')->assertOk();

        $this->assertEqualsCanonicalizing(
            [$managed->id, $joined->id],
            collect($response->json('data'))->pluck('id')->all(),
        );
    }

    #[Test]
    public function an_admin_sees_all_communities(): void
    {
        EnergyCommunity::factory()->count(3)->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/energy-communities')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function the_list_is_filterable_by_state(): void
    {
        $activated = EnergyCommunity::factory()->activated()->withMember($this->user)->create();
        EnergyCommunity::factory()->withMember($this->user)->create();
        EnergyCommunity::factory()->rejected()->withMember($this->user)->create();
        Sanctum::actingAs($this->user);

        $this->getJson('/api/energy-communities?state=activated')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activated->id);
    }

    #[Test]
    public function an_invalid_state_filter_is_a_422(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/energy-communities?state=foo')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('state');
    }

    #[Test]
    public function the_list_is_paginated_at_15(): void
    {
        EnergyCommunity::factory()->count(16)->withMember($this->user)->create();
        Sanctum::actingAs($this->user);

        $this->getJson('/api/energy-communities')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 16)
            ->assertJsonMissingPath('data.0.members');
    }

    // ---- GET /api/energy-communities/{id} -----------------------------------

    #[Test]
    public function members_and_admins_can_view_a_community(): void
    {
        $manager = User::factory()->create();
        $community = EnergyCommunity::factory()
            ->withManager($manager)
            ->withMember($this->user)
            ->create();

        Sanctum::actingAs($this->user);
        $this->getJson("/api/energy-communities/{$community->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $community->id)
            ->assertJsonCount(2, 'data.members');

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson("/api/energy-communities/{$community->id}")->assertOk();
    }

    #[Test]
    public function outsiders_get_403(): void
    {
        $community = EnergyCommunity::factory()->create();
        Sanctum::actingAs($this->user);

        $this->getJson("/api/energy-communities/{$community->id}")->assertForbidden();
    }

    #[Test]
    public function an_unknown_or_soft_deleted_community_is_404(): void
    {
        $community = EnergyCommunity::factory()->withMember($this->user)->create();
        $community->delete();
        Sanctum::actingAs($this->user);

        $this->getJson("/api/energy-communities/{$community->id}")->assertNotFound();
        $this->getJson('/api/energy-communities/999999')->assertNotFound();
    }

    #[Test]
    public function guests_get_401(): void
    {
        $this->getJson('/api/energy-communities')->assertUnauthorized();
        $this->postJson('/api/energy-communities', [])->assertUnauthorized();
    }
}
