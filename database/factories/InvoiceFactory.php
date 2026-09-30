<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $organization = Organization::factory();
        $customer = Customer::factory()->for($organization);

        return [
            'paid_in_full' => false,
            // A factory invoice stands in for one the customer has received.
            // Use draft() for one that has not been sent yet (TASK-480).
            'status' => Invoice::STATUS_SENT,
            'values' => [
                'title' => 'INVOICE',
                'total' => $this->faker->randomFloat(2, 100, 1500),
                'bill_from' => [
                    'company' => 'Casco Bay Pilot Car',
                    'street' => 'P.O. Box 104',
                    'city' => 'Gorham',
                    'state' => 'ME',
                    'zip' => '04038',
                ],
                'bill_to' => [
                    'company' => $this->faker->company(),
                    'street' => $this->faker->streetAddress(),
                    'city' => $this->faker->city(),
                    'state' => $this->faker->stateAbbr(),
                    'zip' => $this->faker->postcode(),
                ],
            ],
            'organization_id' => $organization,
            'customer_id' => $customer,
            'pilot_car_job_id' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => Invoice::STATUS_DRAFT]);
    }

    public function void(): static
    {
        return $this->state(fn () => ['status' => Invoice::STATUS_VOID, 'voided_at' => now()]);
    }
}

