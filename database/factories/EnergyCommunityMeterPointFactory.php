<?php

namespace Database\Factories;

use App\Enums\EnergyCommunityMeterPointState;
use App\Models\EnergyCommunity;
use App\Models\EnergyCommunityMeterPoint;
use App\Models\MeterPoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnergyCommunityMeterPoint>
 */
class EnergyCommunityMeterPointFactory extends Factory
{
    public function definition(): array
    {
        return [
            'energy_community_id' => EnergyCommunity::factory(),
            'meter_point_id' => MeterPoint::factory(),
            'state' => EnergyCommunityMeterPointState::New,
            'from_date' => '2026-01-01',
            'to_date' => null,
            'consent_date' => '2025-12-01',
            'status_code' => null,
        ];
    }

    public function inState(EnergyCommunityMeterPointState $state): static
    {
        return $this->state(['state' => $state]);
    }

    public function period(string $from, ?string $to = null): static
    {
        return $this->state(['from_date' => $from, 'to_date' => $to]);
    }
}
