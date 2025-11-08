<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' Org',
            'primary_contact' => $this->faker->name(),
            'telephone' => $this->faker->phoneNumber(),
            'fax' => null,
            'email' => $this->faker->companyEmail(),
            'street' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'zip' => $this->faker->postcode(),
            'user_id' => User::factory()->admin(),
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Organization $organization) {
            if ($organization->owner && $organization->owner->organization_id !== $organization->id) {
                $organization->owner->organization_id = $organization->id;
                $organization->owner->save();
            }
        });
    }
}

