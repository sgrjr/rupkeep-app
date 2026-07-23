<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\CreatesMultiTenantFixtures;
use Tests\TestCase;

/**
 * Cross-tenant read isolation.
 *
 * These tests pin down holes found in a security audit where a record from one
 * organization could be read by a member of another:
 *
 *  - TASK-356: MyCustomersController::show()/edit() and CustomersController::edit()
 *              loaded a Customer by id with no org scoping and no authorize().
 *  - TASK-357: EditUserLog::mount() skipped authorization entirely for a log in
 *              the "denied" approval state, leaking driver/mileage/expense detail
 *              to any authenticated user from any organization.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMultiTenantFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /** TASK-356 */
    public function test_manager_cannot_view_customer_from_another_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $managerA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_MANAGER);
        $customerB = $this->createCustomerForOrganization($orgB);

        $this->actingAs($managerA)
            ->get(route('my.customers.show', ['customer' => $customerB->id]))
            ->assertForbidden();
    }

    /** TASK-356 */
    public function test_manager_cannot_edit_customer_from_another_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $managerA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_MANAGER);
        $customerB = $this->createCustomerForOrganization($orgB);

        // Both the /my/customers and the resource /customers edit surfaces.
        $this->actingAs($managerA)
            ->get(route('my.customers.edit', ['customer' => $customerB->id]))
            ->assertForbidden();

        $this->actingAs($managerA)
            ->get(route('customers.edit', ['customer' => $customerB->id]))
            ->assertForbidden();
    }

    /** TASK-357 */
    public function test_user_cannot_view_denied_log_from_another_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $driverA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_STANDARD);
        $driverB = $this->createUserForOrganization($orgB, User::ROLE_EMPLOYEE_STANDARD);
        $vehicleB = $this->createVehicleForOrganization($orgB);

        $customerB = $this->createCustomerForOrganization($orgB);
        $contact = $this->createCustomerContact($customerB, [
            'name' => 'Truck Driver B',
            'phone' => '555-0101',
        ]);
        $jobB = $this->createJobForOrganization($orgB, $customerB);

        $logB = $this->createLogForOrganization($orgB, $jobB, $driverB, $vehicleB, $contact, [
            'approval_status' => 'denied',
        ]);

        $this->actingAs($driverA)
            ->get(route('logs.edit', ['log' => $logB->id]))
            ->assertForbidden();
    }

    /**
     * Positive control (TASK-356): the fix must not lock same-org staff out of
     * their own customers.
     */
    public function test_manager_can_still_edit_own_organizations_customer(): void
    {
        $org = $this->createOrganization('Org A');
        $manager = $this->createUserForOrganization($org, User::ROLE_EMPLOYEE_MANAGER);
        $customer = $this->createCustomerForOrganization($org);

        $this->actingAs($manager)
            ->get(route('my.customers.edit', ['customer' => $customer->id]))
            ->assertOk();
    }

    /**
     * Positive control (TASK-357): a denied log stays readable by the assigned
     * driver — they may still VIEW it, they just can't edit it.
     */
    public function test_assigned_driver_can_still_view_their_own_denied_log(): void
    {
        $org = $this->createOrganization('Org A');
        $driver = $this->createUserForOrganization($org, User::ROLE_EMPLOYEE_STANDARD);
        $vehicle = $this->createVehicleForOrganization($org);
        $customer = $this->createCustomerForOrganization($org);
        $contact = $this->createCustomerContact($customer, [
            'name' => 'Truck Driver',
            'phone' => '555-0102',
        ]);
        $job = $this->createJobForOrganization($org, $customer);

        $log = $this->createLogForOrganization($org, $job, $driver, $vehicle, $contact, [
            'approval_status' => 'denied',
        ]);

        $this->actingAs($driver)
            ->get(route('logs.edit', ['log' => $log->id]))
            ->assertOk();
    }
}
