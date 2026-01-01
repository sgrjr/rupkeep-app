<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\CreatesMultiTenantFixtures;
use Tests\TestCase;

class MultiTenantAuthorizationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMultiTenantFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_manager_cannot_view_vehicle_from_another_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $managerA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_MANAGER);
        $vehicleB = $this->createVehicleForOrganization($orgB);

        $this->actingAs($managerA)
            ->get(route('my.vehicles.edit', $vehicleB))
            ->assertForbidden();
    }

    public function test_manager_cannot_view_job_from_another_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $managerA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_MANAGER);

        $customerB = $this->createCustomerForOrganization($orgB);
        $jobB = $this->createJobForOrganization($orgB, $customerB);

        $this->actingAs($managerA)
            ->get(route('my.jobs.show', ['job' => $jobB->id]))
            ->assertForbidden();
    }

    public function test_driver_cannot_edit_log_from_another_organization(): void
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

        $logB = $this->createLogForOrganization($orgB, $jobB, $driverB, $vehicleB, $contact);

        $this->actingAs($driverA)
            ->get(route('logs.edit', ['log' => $logB->id]))
            ->assertForbidden();
    }

    public function test_super_admin_can_view_vehicle_across_organizations(): void
    {
        $superOrg = $this->createOrganization('Reynolds Upkeep');
        $superAdmin = $this->createUserForOrganization($superOrg, User::ROLE_ADMIN);

        $otherOrg = $this->createOrganization('Org C');
        $vehicle = $this->createVehicleForOrganization($otherOrg);

        $this->actingAs($superAdmin)
            ->get(route('my.vehicles.edit', $vehicle))
            ->assertOk()
            ->assertSeeText($vehicle->name);
    }

    public function test_manager_cannot_edit_invoice_from_another_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $managerA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_MANAGER);

        $customerB = $this->createCustomerForOrganization($orgB);
        $jobB = $this->createJobForOrganization($orgB, $customerB);
        $invoiceB = $this->createInvoiceForOrganization($orgB, $customerB, $jobB);

        $this->actingAs($managerA)
            ->get(route('my.invoices.edit', $invoiceB))
            ->assertForbidden();
    }

    public function test_manager_cannot_print_invoice_from_another_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $managerA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_MANAGER);

        $customerB = $this->createCustomerForOrganization($orgB);
        $invoiceB = $this->createInvoiceForOrganization($orgB, $customerB);

        $this->actingAs($managerA)
            ->get(route('my.invoices.print', $invoiceB))
            ->assertForbidden();
    }
}
