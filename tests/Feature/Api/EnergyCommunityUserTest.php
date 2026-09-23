<?php

namespace Tests\Feature\Api;

use App\Models\EnergyCommunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnergyCommunityUserTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $newUser;
    private EnergyCommunity $community;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create();
        $this->newUser = User::factory()->create();
        $this->community = EnergyCommunity::factory()->withManager($this->manager)->create();
    }

    private function add(array $payload, ?EnergyCommunity $community = null)
    {
        $community ??= $this->community;

        return $this->postJson("/api/energy-communities/{$community->id}/users", $payload);
    }

    #[Test]
    public function a_manager_adds_a_member(): void
    {
        Sanctum::actingAs($this->manager);

        $this->add(['user_id' => $this->newUser->id, 'role' => 'member'])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $this->newUser->id)
            ->assertJsonPath('data.role', 'member');

        $this->assertDatabaseHas('energy_community_user', [
            'energy_community_id' => $this->community->id,
            'user_id' => $this->newUser->id,
            'role' => 'member',
        ]);
    }

    #[Test]
    public function a_manager_can_add_another_manager(): void
    {
        Sanctum::actingAs($this->manager);

        $this->add(['user_id' => $this->newUser->id, 'role' => 'manager'])
            ->assertCreated()
            ->assertJsonPath('data.role', 'manager');
    }

    #[Test]
    public function an_admin_can_add_users_without_being_in_the_community(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->add(['user_id' => $this->newUser->id, 'role' => 'member'])->assertCreated();
    }

    #[Test]
    public function a_member_gets_403(): void
    {
        $member = User::factory()->create();
        $this->community->memberships()->create(['user_id' => $member->id, 'role' => 'member']);
        Sanctum::actingAs($member);

        $this->add(['user_id' => $this->newUser->id, 'role' => 'member'])->assertForbidden();
    }

    #[Test]
    public function an_outsider_gets_403_even_with_an_invalid_body(): void
    {
        // Authorization runs before validation: no hints about the body for outsiders.
        Sanctum::actingAs(User::factory()->create());

        $this->add([])->assertForbidden();
    }

    #[Test]
    public function a_user_already_in_the_community_is_a_422(): void
    {
        Sanctum::actingAs($this->manager);

        $this->add(['user_id' => $this->manager->id, 'role' => 'member'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id' => 'already exists']);

        // The existing role is not changed.
        $this->assertDatabaseHas('energy_community_user', [
            'energy_community_id' => $this->community->id,
            'user_id' => $this->manager->id,
            'role' => 'manager',
        ]);
    }

    #[Test]
    public function the_same_user_may_join_different_communities(): void
    {
        $other = EnergyCommunity::factory()->withManager($this->manager)->create();
        $other->memberships()->create(['user_id' => $this->newUser->id, 'role' => 'member']);
        Sanctum::actingAs($this->manager);

        $this->add(['user_id' => $this->newUser->id, 'role' => 'member'])->assertCreated();
    }

    #[Test]
    public function a_rejected_community_is_a_409(): void
    {
        $rejected = EnergyCommunity::factory()->rejected()->withManager($this->manager)->create();
        Sanctum::actingAs($this->manager);

        $this->add(['user_id' => $this->newUser->id, 'role' => 'member'], $rejected)
            ->assertConflict()
            ->assertJsonPath('message', 'Users cannot be added to a rejected energy community.');

        $this->assertDatabaseMissing('energy_community_user', ['user_id' => $this->newUser->id]);
    }

    #[Test]
    public function an_activated_community_accepts_users(): void
    {
        $activated = EnergyCommunity::factory()->activated()->withManager($this->manager)->create();
        Sanctum::actingAs($this->manager);

        $this->add(['user_id' => $this->newUser->id, 'role' => 'member'], $activated)->assertCreated();
    }

    public static function invalidPayloads(): array
    {
        return [
            'missing user_id' => [['role' => 'member'], 'user_id'],
            'unknown user' => [['user_id' => 999999, 'role' => 'member'], 'user_id'],
            'user_id not an integer' => [['user_id' => 'abc', 'role' => 'member'], 'user_id'],
            'missing role' => [['user_id' => 'NEW'], 'role'],
            'unknown role' => [['user_id' => 'NEW', 'role' => 'owner'], 'role'],
        ];
    }

    #[Test]
    #[DataProvider('invalidPayloads')]
    public function invalid_payloads_are_422(array $payload, string $field): void
    {
        if (($payload['user_id'] ?? null) === 'NEW') {
            $payload['user_id'] = $this->newUser->id;
        }
        Sanctum::actingAs($this->manager);

        $this->add($payload)->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    #[Test]
    public function an_unknown_community_is_404_and_guests_get_401(): void
    {
        $this->postJson('/api/energy-communities/999999/users', [])->assertUnauthorized();

        Sanctum::actingAs($this->manager);
        $this->postJson('/api/energy-communities/999999/users', [])->assertNotFound();
    }
}
