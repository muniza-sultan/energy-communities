<?php

namespace Database\Factories;

use App\Models\GridOperator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GridOperator>
 */
class GridOperatorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Netz GmbH',
            'identifier' => 'AT'.fake()->unique()->numerify('9#####'),
        ];
    }
}
