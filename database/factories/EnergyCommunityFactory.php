<?php

namespace Database\Factories;

use App\Enums\EnergyCommunityState;
use App\Enums\EnergyCommunityUserRole;
use App\Models\EnergyCommunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnergyCommunity>
 */
class EnergyCommunityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ecid' => 'AT'.fake()->unique()->numerify(str_repeat('#', 31)),
            'name' => 'Energiegemeinschaft '.fake()->city(),
            'state' => EnergyCommunityState::New,
        ];
    }

    public function activated(): static
    {
        return $this->state(['state' => EnergyCommunityState::Activated]);
    }

    public function rejected(): static
    {
        return $this->state(['state' => EnergyCommunityState::Rejected]);
    }

    public function withManager(User $user): static
    {
        return $this->hasAttached($user, ['role' => EnergyCommunityUserRole::Manager->value], 'users');
    }

    public function withMember(User $user): static
    {
        return $this->hasAttached($user, ['role' => EnergyCommunityUserRole::Member->value], 'users');
    }
}
