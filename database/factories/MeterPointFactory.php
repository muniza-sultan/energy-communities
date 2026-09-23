<?php

namespace Database\Factories;

use App\Enums\EnergyDirection;
use App\Models\GridOperator;
use App\Models\MeterPoint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeterPoint>
 */
class MeterPointFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'energy_direction' => EnergyDirection::Consumption,
            'grid_operator_id' => fn () => GridOperator::factory()->create()->identifier,
            'name' => fn (array $attributes) => $attributes['grid_operator_id']
                .fake()->unique()->numerify(str_repeat('#', 25)),
        ];
    }

    public function generation(): static
    {
        return $this->state(['energy_direction' => EnergyDirection::Generation]);
    }

    public function consumption(): static
    {
        return $this->state(['energy_direction' => EnergyDirection::Consumption]);
    }

    public function ownedBy(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }

    public function operatedBy(GridOperator $operator): static
    {
        return $this->state(['grid_operator_id' => $operator->identifier]);
    }
}
