<?php

namespace Tests\Fixtures;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use App\Models\Vehicle;

trait CreatesMultiTenantFixtures
{
    protected function createOrganization(string $name = null): Organization
    {
        return Organization::factory()->create([
            'name' => $name ?? fake()->unique()->company(),
        ]);
    }

    protected function createUserForOrganization(Organization $organization, string $role = User::ROLE_EMPLOYEE_STANDARD): User
    {
        $factory = User::factory()->forOrganization($organization);

        return match ($role) {
            User::ROLE_ADMIN => $factory->admin()->create(),
            User::ROLE_EMPLOYEE_MANAGER => $factory->manager()->create(),
            User::ROLE_EMPLOYEE_STANDARD => $factory->standard()->create(),
            User::ROLE_CUSTOMER => $factory->asCustomer($this->createCustomerForOrganization($organization))->create(),
            default => $factory->create(['organization_role' => $role]),
        };
    }

    protected function createCustomerForOrganization(Organization $organization): Customer
    {
        return Customer::factory()->create([
            'organization_id' => $organization->id,
        ]);
    }

    protected function createVehicleForOrganization(Organization $organization, array $overrides = []): Vehicle
    {
        return Vehicle::factory()->create(array_merge([
            'organization_id' => $organization->id,
        ], $overrides));
    }

    protected function createJobForOrganization(Organization $organization, ?Customer $customer = null, array $overrides = []): PilotCarJob
    {
        $customer ??= $this->createCustomerForOrganization($organization);

        return PilotCarJob::factory()
            ->for($customer)
            ->create(array_merge([
                'organization_id' => $organization->id,
            ], $overrides));
    }

    protected function createCustomerContact(Customer $customer, array $overrides = []): CustomerContact
    {
        return CustomerContact::create(array_merge([
            'customer_id' => $customer->id,
            'organization_id' => $customer->organization_id,
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
        ], $overrides));
    }

    protected function createLogForOrganization(
        Organization $organization,
        PilotCarJob $job,
        User $driver,
        Vehicle $vehicle,
        CustomerContact $contact,
        array $overrides = []
    ): UserLog {
        return UserLog::create(array_merge([
            'job_id' => $job->id,
            'car_driver_id' => $driver->id,
            'organization_id' => $organization->id,
            'vehicle_id' => $vehicle->id,
            'vehicle_position' => 'lead',
            'truck_driver_id' => $contact->id,
        ], $overrides));
    }

    protected function createInvoiceForOrganization(
        Organization $organization,
        Customer $customer,
        ?PilotCarJob $job = null,
        array $overrides = []
    ): Invoice {
        $invoice = Invoice::factory()
            ->for($organization)
            ->for($customer)
            ->create(array_merge([
                'pilot_car_job_id' => $job?->id,
            ], $overrides));

        if ($job) {
            $job->invoices()->syncWithoutDetaching([$invoice->id]);
        }

        return $invoice;
    }
}
