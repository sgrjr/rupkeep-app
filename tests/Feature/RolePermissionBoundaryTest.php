<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\CreatesMultiTenantFixtures;
use Tests\TestCase;

/**
 * Per-role permission boundaries (TASK-306).
 *
 * Roles live on `users.organization_role`: admin (a.k.a. super only when the
 * org is 'Reynolds Upkeep'), employee_manager, employee_standard (driver) and
 * customer. Enforcement is a mix of policies (`InvoicePolicy`, `PilotCarJobPolicy`,
 * `CustomerPolicy`, `TaskPolicy`), the `super` middleware (IsSuperAdmin), and
 * inline role checks in controllers. This class asserts that each role is denied
 * the manager/admin/super surfaces above its station, plus a couple of positive
 * controls so a 403 can't be mistaken for a broken route.
 */
class RolePermissionBoundaryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMultiTenantFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    // ---------------------------------------------------------------------
    // Customer role: denied the internal admin / staff surfaces.
    // ---------------------------------------------------------------------

    public function test_customer_cannot_access_admin_task_list(): void
    {
        $org = $this->createOrganization('Org A');
        $customerUser = $this->createUserForOrganization($org, User::ROLE_CUSTOMER);

        $this->actingAs($customerUser)
            ->get(route('tasks.index'))
            ->assertForbidden();
    }

    public function test_customer_cannot_access_admin_task_board(): void
    {
        $org = $this->createOrganization('Org A');
        $customerUser = $this->createUserForOrganization($org, User::ROLE_CUSTOMER);

        $this->actingAs($customerUser)
            ->get(route('tasks.board'))
            ->assertForbidden();
    }

    public function test_customer_cannot_access_server_management(): void
    {
        $org = $this->createOrganization('Org A');
        $customerUser = $this->createUserForOrganization($org, User::ROLE_CUSTOMER);

        $this->actingAs($customerUser)
            ->get(route('admin.server-management'))
            ->assertForbidden();
    }

    public function test_non_super_admin_cannot_access_server_management(): void
    {
        // The 'super' middleware is org-name gated, not merely role gated: an
        // ordinary-org admin must still be denied.
        $org = $this->createOrganization('Some Customer Co');
        $admin = $this->createUserForOrganization($org, User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->get(route('admin.server-management'))
            ->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Driver (standard employee): denied invoice management, and create/
    // update/delete of jobs, customers and vehicles.
    // ---------------------------------------------------------------------

    public function test_driver_cannot_open_invoice_edit(): void
    {
        $org = $this->createOrganization('Org A');
        $driver = $this->createUserForOrganization($org, User::ROLE_EMPLOYEE_STANDARD);
        $customer = $this->createCustomerForOrganization($org);
        $invoice = $this->createInvoiceForOrganization($org, $customer);

        $this->actingAs($driver)
            ->get(route('my.invoices.edit', $invoice))
            ->assertForbidden();
    }

    public function test_driver_cannot_update_invoice(): void
    {
        $org = $this->createOrganization('Org A');
        $driver = $this->createUserForOrganization($org, User::ROLE_EMPLOYEE_STANDARD);
        $customer = $this->createCustomerForOrganization($org);
        $invoice = $this->createInvoiceForOrganization($org, $customer);

        $this->actingAs($driver)
            ->put(route('my.invoices.update', $invoice), ['values' => ['total' => 1]])
            ->assertForbidden();
    }

    public function test_driver_cannot_print_invoice(): void
    {
        $org = $this->createOrganization('Org A');
        $driver = $this->createUserForOrganization($org, User::ROLE_EMPLOYEE_STANDARD);
        $customer = $this->createCustomerForOrganization($org);
        $invoice = $this->createInvoiceForOrganization($org, $customer);

        $this->actingAs($driver)
            ->get(route('my.invoices.print', $invoice))
            ->assertForbidden();
    }

    public function test_driver_cannot_create_job(): void
    {
        $org = $this->createOrganization('Org A');
        $driver = $this->createUserForOrganization($org, User::ROLE_EMPLOYEE_STANDARD);
        $customer = $this->createCustomerForOrganization($org);

        $this->actingAs($driver)
            ->post(route('my.jobs.store'), [
                'job_no' => 'DRIVER-NO',
                'customer_id' => $customer->id,
            ])
            ->assertForbidden();
    }

    public function test_driver_cannot_update_job(): void
    {
        $org = $this->createOrganization('Org A');
        $driver = $this->createUserForOrganization($org, User::ROLE_EMPLOYEE_STANDARD);
        $customer = $this->createCustomerForOrganization($org);
        $job = $this->createJobForOrganization($org, $customer, ['load_no' => 'BEFORE']);

        $this->actingAs($driver)
            ->put(route('my.jobs.update', ['job' => $job->id]), ['load_no' => 'AFTER'])
            ->assertForbidden();

        $this->assertSame('BEFORE', $job->fresh()->load_no);
    }

    public function test_driver_cannot_delete_job(): void
    {
        $org = $this->createOrganization('Org A');
        $driver = $this->createUserForOrganization($org, User::ROLE_EMPLOYEE_STANDARD);
        $customer = $this->createCustomerForOrganization($org);
        $job = $this->createJobForOrganization($org, $customer);

        $this->actingAs($driver)
            ->delete(route('my.jobs.destroy', ['job' => $job->id]))
            ->assertForbidden();

        $this->assertNull($job->fresh()->deleted_at);
    }

    public function test_driver_cannot_create_customer(): void
    {
        $org = $this->createOrganization('Org A');
        $driver = $this->createUserForOrganization($org, User::ROLE_EMPLOYEE_STANDARD);

        $this->actingAs($driver)
            ->post(route('customers.store'), ['name' => 'Driver Made This'])
            ->assertForbidden();

        $this->assertDatabaseMissing('customers', ['name' => 'Driver Made This']);
    }

    public function test_driver_cannot_delete_customer(): void
    {
        $org = $this->createOrganization('Org A');
        $driver = $this->createUserForOrganization($org, User::ROLE_EMPLOYEE_STANDARD);
        $customer = Customer::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($driver)
            ->delete(route('customers.destroy', ['customer' => $customer->id]))
            ->assertForbidden();

        $this->assertNull($customer->fresh()->deleted_at);
    }

    public function test_driver_cannot_create_vehicle(): void
    {
        $org = $this->createOrganization('Org A');
        $driver = $this->createUserForOrganization($org, User::ROLE_EMPLOYEE_STANDARD);

        // Supply odometer explicitly: MyVehiclesController::store builds the
        // Vehicle (reading $data['odometer']) before it calls authorize(), so
        // omitting it would 500 on the read rather than reaching the 403 gate.
        $this->actingAs($driver)
            ->post(route('my.vehicles.store'), ['name' => 'Driver Escort', 'odometer' => 1000])
            ->assertForbidden();

        $this->assertDatabaseMissing('vehicles', ['name' => 'Driver Escort']);
    }

    // ---------------------------------------------------------------------
    // Positive controls: the roles above CAN reach their own surfaces, so a
    // 403 elsewhere is a real boundary and not an accidentally broken route.
    // ---------------------------------------------------------------------

    public function test_manager_can_open_own_jobs_index(): void
    {
        $org = $this->createOrganization('Org A');
        $manager = $this->createUserForOrganization($org, User::ROLE_EMPLOYEE_MANAGER);

        $this->actingAs($manager)
            ->get(route('my.jobs.index'))
            ->assertOk();
    }

    public function test_manager_can_open_own_invoice_edit(): void
    {
        $org = $this->createOrganization('Org A');
        $manager = $this->createUserForOrganization($org, User::ROLE_EMPLOYEE_MANAGER);
        $customer = $this->createCustomerForOrganization($org);
        $invoice = $this->createInvoiceForOrganization($org, $customer);

        $this->actingAs($manager)
            ->get(route('my.invoices.edit', $invoice))
            ->assertOk();
    }

    // ---------------------------------------------------------------------
    // Known permission HOLE: the /my/* and /customers staff surfaces are
    // gated only by auth+verified, with no role check, so a customer-portal
    // user reaches internal staff listings. Skip (named) until fixed.
    // ---------------------------------------------------------------------

    public function test_customer_cannot_reach_staff_jobs_index(): void
    {
        $org = $this->createOrganization('Org A');
        $customerUser = $this->createUserForOrganization($org, User::ROLE_CUSTOMER);

        // EnsureStaff middleware redirects customer accounts to their portal
        // instead of a bare 403 (guests still get 403).
        $this->actingAs($customerUser)
            ->get(route('my.jobs.index'))
            ->assertRedirect(route('customer.invoices.index'));
    }

    public function test_customer_cannot_reach_staff_vehicles_index(): void
    {
        $org = $this->createOrganization('Org A');
        $customerUser = $this->createUserForOrganization($org, User::ROLE_CUSTOMER);

        $this->actingAs($customerUser)
            ->get(route('my.vehicles.index'))
            ->assertRedirect(route('customer.invoices.index'));
    }

    public function test_customer_cannot_reach_staff_customers_index(): void
    {
        $org = $this->createOrganization('Org A');
        $customerUser = $this->createUserForOrganization($org, User::ROLE_CUSTOMER);

        $this->actingAs($customerUser)
            ->get(route('customers.index'))
            ->assertRedirect(route('customer.invoices.index'));
    }
}
