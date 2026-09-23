<?php

namespace Tests\Feature\Api;

use App\Models\GridOperator;
use App\Models\MeterPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MeterPointTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = 'AT0030000000000000000000004711001';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        GridOperator::factory()->create(['identifier' => 'AT003000']);
        $this->user = User::factory()->create();
    }

    // ---- POST /api/meter-points ---------------------------------------------

    #[Test]
    public function a_user_registers_a_meter_point_of_their_own(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/meter-points', [
            'name' => self::CODE,
            'energy_direction' => 'generation',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', self::CODE)
            ->assertJsonPath('data.energy_direction', 'generation')
            ->assertJsonPath('data.grid_operator_id', 'AT003000')
            ->assertJsonPath('data.user_id', $this->user->id);

        $this->assertDatabaseHas('meter_points', [
            'name' => self::CODE,
            'grid_operator_id' => 'AT003000',
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function the_owner_is_always_the_caller(): void
    {
        $someoneElse = User::factory()->create();
        Sanctum::actingAs($this->user);

        $this->postJson('/api/meter-points', [
            'name' => self::CODE,
            'energy_direction' => 'consumption',
            'user_id' => $someoneElse->id,
            'grid_operator_id' => 'AT999999',
        ])->assertCreated()
            ->assertJsonPath('data.user_id', $this->user->id)
            ->assertJsonPath('data.grid_operator_id', 'AT003000');
    }

    #[Test]
    public function an_unknown_grid_operator_is_rejected(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/meter-points', [
            'name' => 'AT0040000000000000000000004711001',
            'energy_direction' => 'consumption',
        ])->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('meter_points', 0);
    }

    #[Test]
    public function a_duplicate_code_is_rejected(): void
    {
        MeterPoint::factory()->create(['name' => self::CODE, 'grid_operator_id' => 'AT003000']);
        Sanctum::actingAs($this->user);

        $this->postJson('/api/meter-points', [
            'name' => self::CODE,
            'energy_direction' => 'consumption',
        ])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public static function invalidPayloads(): array
    {
        return [
            'missing name' => [['energy_direction' => 'consumption'], 'name'],
            'empty name' => [['name' => '', 'energy_direction' => 'consumption'], 'name'],
            'malformed name' => [['name' => 'at003000-bad', 'energy_direction' => 'consumption'], 'name'],
            'missing direction' => [['name' => self::CODE], 'energy_direction'],
            'unknown direction' => [['name' => self::CODE, 'energy_direction' => 'storage'], 'energy_direction'],
        ];
    }

    #[Test]
    #[DataProvider('invalidPayloads')]
    public function invalid_payloads_are_rejected(array $payload, string $field): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/meter-points', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    #[Test]
    public function guests_get_401(): void
    {
        $this->postJson('/api/meter-points', [])->assertUnauthorized();
        $this->getJson('/api/meter-points')->assertUnauthorized();
    }

    // ---- GET /api/meter-points ----------------------------------------------

    #[Test]
    public function a_user_sees_only_their_own_meter_points(): void
    {
        $mine = MeterPoint::factory()->ownedBy($this->user)->count(2)->create();
        MeterPoint::factory()->count(3)->create();
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/meter-points')->assertOk();

        $this->assertEqualsCanonicalizing(
            $mine->pluck('id')->all(),
            collect($response->json('data'))->pluck('id')->all(),
        );
    }

    #[Test]
    public function an_admin_sees_all_meter_points(): void
    {
        MeterPoint::factory()->ownedBy($this->user)->create();
        MeterPoint::factory()->count(3)->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/meter-points')
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    #[Test]
    public function the_list_is_filterable_by_energy_direction(): void
    {
        $generation = MeterPoint::factory()->ownedBy($this->user)->generation()->create();
        MeterPoint::factory()->ownedBy($this->user)->consumption()->create();
        Sanctum::actingAs($this->user);

        $this->getJson('/api/meter-points?energy_direction=generation')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $generation->id);
    }

    #[Test]
    public function an_invalid_filter_value_is_a_422(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/meter-points?energy_direction=storage')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('energy_direction');
    }

    #[Test]
    public function the_list_is_paginated(): void
    {
        MeterPoint::factory()->ownedBy($this->user)->count(16)->create();
        Sanctum::actingAs($this->user);

        $this->getJson('/api/meter-points')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 16);
    }
}
