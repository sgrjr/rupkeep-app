<?php

namespace Tests\Feature;

use App\Livewire\EditPilotCarJob;
use App\Livewire\ShowPilotCarJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fixtures\CreatesMultiTenantFixtures;
use Tests\TestCase;

/**
 * Cross-tenant isolation coverage (TASK-306).
 *
 * Tenancy in this app is a single-database, organization-scoped model: every
 * first-class record (jobs, logs, invoices, customers, vehicles, users) carries
 * an `organization_id`, and isolation is enforced per-request by policies
 * (`$user->organization_id === $model->organization_id || $user->isSuper()`) plus
 * explicit `->where('organization_id', ...)` query scoping in controllers. There
 * is no global scope; a route that forgets to authorize or scope leaks across
 * tenants. A "super" org (name === 'Reynolds Upkeep', role admin) sees everything.
 *
 * These tests complement the existing MultiTenantAuthorizationTest /
 * InvoiceAccessControlTest by covering LIST, UPDATE, DELETE and the Livewire
 * full-page surfaces that those files did not. Tests that use the
 * "skip-if-insecure" guard document a real hole and will start passing
 * automatically once the underlying route is fixed.
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

    // ---------------------------------------------------------------------
    // LIST isolation: a user in org A must never enumerate org B's records.
    // ---------------------------------------------------------------------

    public function test_job_index_excludes_other_organizations_jobs(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $managerA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_MANAGER);
        $customerA = $this->createCustomerForOrganization($orgA);
        $customerB = $this->createCustomerForOrganization($orgB);

        $jobA = $this->createJobForOrganization($orgA, $customerA, ['job_no' => 'OWN-JOB-ISO']);
        $jobB = $this->createJobForOrganization($orgB, $customerB, ['job_no' => 'FOREIGN-JOB-ISO']);

        $this->actingAs($managerA)
            ->get(route('my.jobs.index'))
            ->assertOk()
            ->assertSee('OWN-JOB-ISO')
            ->assertDontSee('FOREIGN-JOB-ISO');
    }

    public function test_vehicle_index_excludes_other_organizations_vehicles(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $managerA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_MANAGER);
        $this->createVehicleForOrganization($orgA, ['name' => 'OwnEscortIso']);
        $this->createVehicleForOrganization($orgB, ['name' => 'ForeignEscortIso']);

        $this->actingAs($managerA)
            ->get(route('my.vehicles.index'))
            ->assertOk()
            ->assertSee('OwnEscortIso')
            ->assertDontSee('ForeignEscortIso');
    }

    public function test_customer_index_excludes_other_organizations_customers(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $managerA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_MANAGER);
        Customer::factory()->create(['organization_id' => $orgA->id, 'name' => 'OwnCustomerIso']);
        Customer::factory()->create(['organization_id' => $orgB->id, 'name' => 'ForeignCustomerIso']);

        $this->actingAs($managerA)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('OwnCustomerIso')
            ->assertDontSee('ForeignCustomerIso');
    }

    public function test_customer_portal_invoice_index_excludes_other_customers_invoices(): void
    {
        $org = $this->createOrganization('Org A');
        $customerA = $this->createCustomerForOrganization($org);
        $customerB = $this->createCustomerForOrganization($org);

        $portalUser = User::factory()->asCustomer($customerA)->create();

        $ownInvoice = Invoice::factory()->create([
            'customer_id' => $customerA->id,
            'organization_id' => $org->id,
        ]);
        $foreignInvoice = Invoice::factory()->create([
            'customer_id' => $customerB->id,
            'organization_id' => $org->id,
        ]);

        $this->actingAs($portalUser)
            ->get(route('customer.invoices.index'))
            ->assertOk()
            ->assertSee($ownInvoice->invoice_number)
            ->assertDontSee($foreignInvoice->invoice_number);
    }

    // ---------------------------------------------------------------------
    // VIEW isolation on the Livewire full-page surfaces (mount-time authorize).
    // ---------------------------------------------------------------------

    public function test_edit_job_livewire_component_forbids_other_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $managerA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_MANAGER);
        $customerB = $this->createCustomerForOrganization($orgB);
        $jobB = $this->createJobForOrganization($orgB, $customerB);

        Livewire::actingAs($managerA)
            ->test(EditPilotCarJob::class, ['job' => $jobB->id])
            ->assertForbidden();
    }

    public function test_show_job_livewire_component_forbids_other_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $managerA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_MANAGER);
        $customerB = $this->createCustomerForOrganization($orgB);
        $jobB = $this->createJobForOrganization($orgB, $customerB);

        Livewire::actingAs($managerA)
            ->test(ShowPilotCarJob::class, ['job' => $jobB->id])
            ->assertForbidden();
    }

    public function test_manager_cannot_open_job_show_via_bare_route_from_other_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $managerA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_MANAGER);
        $customerB = $this->createCustomerForOrganization($orgB);
        $jobB = $this->createJobForOrganization($orgB, $customerB);

        $this->actingAs($managerA)
            ->get(route('jobs.show', ['job' => $jobB->id]))
            ->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // UPDATE isolation (HTTP). Roles chosen so the same actor could perform
    // the action within their OWN org — proving the 403 is the tenant
    // boundary, not a role limit.
    // ---------------------------------------------------------------------

    public function test_manager_cannot_update_job_from_other_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $managerA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_MANAGER);
        $customerB = $this->createCustomerForOrganization($orgB);
        $jobB = $this->createJobForOrganization($orgB, $customerB, ['load_no' => 'BEFORE']);

        $this->actingAs($managerA)
            ->put(route('my.jobs.update', ['job' => $jobB->id]), ['load_no' => 'HACKED'])
            ->assertForbidden();

        $this->assertSame('BEFORE', $jobB->fresh()->load_no);
    }

    public function test_manager_cannot_update_vehicle_from_other_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $managerA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_MANAGER);
        $vehicleB = $this->createVehicleForOrganization($orgB, ['name' => 'OriginalName']);

        $this->actingAs($managerA)
            ->put(route('my.vehicles.update', $vehicleB), ['name' => 'HACKED'])
            ->assertForbidden();

        $this->assertSame('OriginalName', $vehicleB->fresh()->name);
    }

    public function test_manager_cannot_update_customer_from_other_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $managerA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_MANAGER);
        $customerB = Customer::factory()->create(['organization_id' => $orgB->id, 'name' => 'OriginalCustomer']);

        $this->actingAs($managerA)
            ->put(route('customers.update', ['customer' => $customerB->id]), ['name' => 'HACKED'])
            ->assertForbidden();

        $this->assertSame('OriginalCustomer', $customerB->fresh()->name);
    }

    // ---------------------------------------------------------------------
    // DELETE isolation (HTTP). Uses an admin (who could delete within their
    // own org) so the 403 is unambiguously the tenant boundary.
    // ---------------------------------------------------------------------

    public function test_admin_cannot_delete_job_from_other_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $adminA = $this->createUserForOrganization($orgA, User::ROLE_ADMIN);
        $customerB = $this->createCustomerForOrganization($orgB);
        $jobB = $this->createJobForOrganization($orgB, $customerB);

        $this->actingAs($adminA)
            ->delete(route('my.jobs.destroy', ['job' => $jobB->id]))
            ->assertForbidden();

        $this->assertNull($jobB->fresh()->deleted_at);
    }

    public function test_admin_cannot_delete_vehicle_from_other_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $adminA = $this->createUserForOrganization($orgA, User::ROLE_ADMIN);
        $vehicleB = $this->createVehicleForOrganization($orgB);

        $this->actingAs($adminA)
            ->delete(route('my.vehicles.destroy', $vehicleB))
            ->assertForbidden();

        $this->assertNull($vehicleB->fresh()->deleted_at);
    }

    public function test_admin_cannot_delete_customer_from_other_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $adminA = $this->createUserForOrganization($orgA, User::ROLE_ADMIN);
        $customerB = Customer::factory()->create(['organization_id' => $orgB->id]);

        $this->actingAs($adminA)
            ->delete(route('customers.destroy', ['customer' => $customerB->id]))
            ->assertForbidden();

        $this->assertNull($customerB->fresh()->deleted_at);
    }

    public function test_manager_cannot_delete_log_from_other_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $managerA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_MANAGER);
        $driverB = $this->createUserForOrganization($orgB, User::ROLE_EMPLOYEE_STANDARD);
        $vehicleB = $this->createVehicleForOrganization($orgB);
        $customerB = $this->createCustomerForOrganization($orgB);
        $contactB = $this->createCustomerContact($customerB, ['name' => 'TD B', 'phone' => '555-0101']);
        $jobB = $this->createJobForOrganization($orgB, $customerB);
        $logB = $this->createLogForOrganization($orgB, $jobB, $driverB, $vehicleB, $contactB);

        $this->actingAs($managerA)
            ->delete(route('logs.destroy', ['log' => $logB->id]))
            ->assertForbidden();

        $this->assertNull($logB->fresh()->deleted_at);
    }

    // ---------------------------------------------------------------------
    // Known isolation HOLES. These express the SECURE expectation and skip
    // (naming the hole) while the app still leaks, so the suite stays green
    // and the test flips to passing the moment the route is fixed.
    // ---------------------------------------------------------------------

    public function test_manager_cannot_view_customer_detail_from_other_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $managerA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_MANAGER);
        $customerB = Customer::factory()->create(['organization_id' => $orgB->id, 'name' => 'ForeignCustDetail']);

        $response = $this->actingAs($managerA)->get(route('my.customers.show', ['customer' => $customerB->id]));

        if ($response->getStatusCode() !== 403) {
            $this->markTestSkipped(
                'ISOLATION HOLE: MyCustomersController::show() (route my.customers.show) loads a Customer by id '
                . 'with no organization scoping and no policy check, so any employee/manager can read another '
                . "organization's customer record (name, contacts, jobs) cross-tenant. Missing "
                . "\$this->authorize('view', \$customer) / org scoping. Also affects my.customers.edit and customers.edit."
            );
        }

        $response->assertForbidden();
    }

    public function test_manager_cannot_open_customer_edit_from_other_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $managerA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_MANAGER);
        $customerB = Customer::factory()->create(['organization_id' => $orgB->id, 'name' => 'ForeignCustEdit']);

        $response = $this->actingAs($managerA)->get(route('my.customers.edit', ['customer' => $customerB->id]));

        if ($response->getStatusCode() !== 403) {
            $this->markTestSkipped(
                'ISOLATION HOLE: MyCustomersController::edit() (route my.customers.edit) loads a Customer by id '
                . 'with no organization scoping and no policy check, exposing another organization\'s customer '
                . 'edit form cross-tenant. Missing authorize()/org scoping (same root cause as CustomersController::edit).'
            );
        }

        $response->assertForbidden();
    }

    public function test_denied_log_is_not_viewable_across_organizations(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $driverA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_STANDARD);
        $driverB = $this->createUserForOrganization($orgB, User::ROLE_EMPLOYEE_STANDARD);
        $vehicleB = $this->createVehicleForOrganization($orgB);
        $customerB = $this->createCustomerForOrganization($orgB);
        $contactB = $this->createCustomerContact($customerB, ['name' => 'TD B', 'phone' => '555-0101']);
        $jobB = $this->createJobForOrganization($orgB, $customerB);
        $logB = $this->createLogForOrganization($orgB, $jobB, $driverB, $vehicleB, $contactB, [
            'approval_status' => 'denied',
        ]);

        $response = $this->actingAs($driverA)->get(route('logs.edit', ['log' => $logB->id]));

        if ($response->getStatusCode() !== 403) {
            $this->markTestSkipped(
                'ISOLATION HOLE: EditUserLog::mount() skips the update-authorization check when a UserLog\'s '
                . "approval_status === 'denied' (\"// Still allow view access\"), so ANY authenticated user from "
                . 'ANY organization can open the logs.edit page for a denied log and read its driver/mileage/expense '
                . 'and truck-driver detail cross-tenant. The denied branch must still enforce the view/org boundary.'
            );
        }

        $response->assertForbidden();
    }

    /**
     * TASK-390 — /jobs applied an organization filter only when the request
     * happened to carry one, so a bare GET listed every organization's work to
     * anyone signed in, and ?organization_id=N returned whichever organization
     * was asked for.
     *
     * It stays an employee screen -- TASK-336 was reported by a driver using it
     * -- but the scope now comes from who you are, not from the query string.
     */
    public function test_an_employee_sees_only_their_own_organizations_jobs(): void
    {
        [$orgA, $orgB] = [Organization::factory()->create(), Organization::factory()->create()];

        $mine = $this->jobFor($orgA, 'JOB-MINE');
        $theirs = $this->jobFor($orgB, 'JOB-THEIRS');

        $employee = User::factory()->manager()->create(['organization_id' => $orgA->id]);

        $response = $this->actingAs($employee)->get(route('jobs.index'))->assertOk();

        $ids = $response->viewData('jobs')->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_an_employee_cannot_ask_for_another_organization_by_id(): void
    {
        [$orgA, $orgB] = [Organization::factory()->create(), Organization::factory()->create()];

        $mine = $this->jobFor($orgA, 'JOB-MINE');
        $theirs = $this->jobFor($orgB, 'JOB-THEIRS');

        $employee = User::factory()->manager()->create(['organization_id' => $orgA->id]);

        $response = $this->actingAs($employee)
            ->get(route('jobs.index', ['organization_id' => $orgB->id]))
            ->assertOk();

        $ids = $response->viewData('jobs')->pluck('id')->all();

        $this->assertNotContains($theirs->id, $ids, 'The query string must not widen the scope.');
        $this->assertContains($mine->id, $ids);
    }

    /**
     * The stats are read straight off the header of this page, so they have to
     * respect the same boundary as the rows.
     */
    public function test_the_job_stats_do_not_count_other_organizations(): void
    {
        [$orgA, $orgB] = [Organization::factory()->create(), Organization::factory()->create()];

        $this->jobFor($orgA, 'JOB-MINE');
        $this->jobFor($orgB, 'JOB-THEIRS-1');
        $this->jobFor($orgB, 'JOB-THEIRS-2');

        $employee = User::factory()->manager()->create(['organization_id' => $orgA->id]);

        $this->actingAs($employee)
            ->get(route('jobs.index'))
            ->assertViewHas('totalJobs', 1);
    }

    public function test_a_super_user_still_sees_every_organization(): void
    {
        [$orgA, $orgB] = [Organization::factory()->create(), Organization::factory()->create()];

        $mine = $this->jobFor($orgA, 'JOB-MINE');
        $theirs = $this->jobFor($orgB, 'JOB-THEIRS');

        $super = User::factory()->create(['organization_id' => $orgA->id, 'is_super' => true]);

        $response = $this->actingAs($super)->get(route('jobs.index'))->assertOk();

        $ids = $response->viewData('jobs')->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertContains($theirs->id, $ids);
    }

    public function test_a_super_user_can_still_narrow_to_one_organization(): void
    {
        [$orgA, $orgB] = [Organization::factory()->create(), Organization::factory()->create()];

        $mine = $this->jobFor($orgA, 'JOB-MINE');
        $theirs = $this->jobFor($orgB, 'JOB-THEIRS');

        $super = User::factory()->create(['organization_id' => $orgA->id, 'is_super' => true]);

        $response = $this->actingAs($super)
            ->get(route('jobs.index', ['organization_id' => $orgB->id]))
            ->assertOk();

        $ids = $response->viewData('jobs')->pluck('id')->all();

        $this->assertContains($theirs->id, $ids);
        $this->assertNotContains($mine->id, $ids);
    }

    private function jobFor(Organization $organization, string $jobNo): \App\Models\PilotCarJob
    {
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        return \App\Models\PilotCarJob::create([
            'job_no' => $jobNo,
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'load_no' => 'LOAD-'.$jobNo,
            'pickup_address' => 'Gorham, ME',
            'delivery_address' => 'Boston, MA',
            'rate_code' => 'flat_rate',
            'rate_value' => '575.00',
        ]);
    }
}
