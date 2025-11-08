<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PilotCarJob>
 */
class PilotCarJobFactory extends Factory
{
    protected $model = PilotCarJob::class;

    public function definition(): array
    {
        $organization = Organization::factory();
        $customer = Customer::factory()->for($organization);

        $pickup = Carbon::now()->addDays($this->faker->numberBetween(1, 5));
        $delivery = (clone $pickup)->addDays($this->faker->numberBetween(1, 3));

        return [
            'job_no' => 'JOB-' . $this->faker->unique()->numberBetween(1000, 9999),
            'customer_id' => $customer,
            'organization_id' => $organization,
            'scheduled_pickup_at' => $pickup,
            'scheduled_delivery_at' => $delivery,
            'load_no' => 'LOAD-' . $this->faker->unique()->numberBetween(100, 999),
            'pickup_address' => $this->faker->streetAddress(),
            'delivery_address' => $this->faker->streetAddress(),
            'rate_code' => 'per_mile_rate_2_00',
            'rate_value' => '2.00',
        ];
    }
}


