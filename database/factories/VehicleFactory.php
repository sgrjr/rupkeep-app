<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company() . ' Escort',
            'odometer' => $this->faker->numberBetween(1000, 250000),
            'odometer_updated_at' => Carbon::now()->subDays($this->faker->numberBetween(0, 30)),
            'organization_id' => Organization::factory(),
            'is_in_service' => true,
        ];
    }
}


