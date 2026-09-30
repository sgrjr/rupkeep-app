<?php

namespace Tests\Feature;

use App\Livewire\CreatePilotCarJob;
use App\Livewire\ShowPilotCarJob;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\Fixtures\CreatesMultiTenantFixtures;
use Tests\TestCase;

/**
 * TASK-433 regression. ShowPilotCarJob::mount() authorized only `view` (any
 * same-org role) and assignJob / generateInvoice / uploadFile /
 * notifyCustomerContact checked nothing, so a driver could invoice a job and
 * assign a driver from another organization (Poc2Test proved it).
 * CreatePilotCarJob had no authorization at all.
 */
class JobPageAuthorizationTest extends TestCase
{
    use RefreshDatabase, CreatesMultiTenantFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Event::fake();
    }

    private function open(User $as, int $jobId)
    {
        return Livewire::actingAs($as)->test(ShowPilotCarJob::class, ['job' => $jobId]);
    }

    public function test_customer_portal_user_is_kept_off_the_job_pages(): void
    {
        $a = $this->createOrganization('A');
        $customer = $this->createUserForOrganization($a, User::ROLE_CUSTOMER);
        $job = $this->createJobForOrganization($a);

        // Route middleware bounces customers to their portal; the component
        // itself refuses too, since a Livewire request skips the route.
        $this->actingAs($customer)->get(route('my.jobs.show', $job))->assertRedirect(route('customer.invoices.index'));
        $this->actingAs($customer)->get(route('my.jobs.create'))->assertRedirect(route('customer.invoices.index'));
        $this->actingAs($customer)->get(route('my.jobs.edit', $job))->assertRedirect(route('customer.invoices.index'));
        $this->open($customer, $job->id)->assertForbidden();
    }

    public function test_driver_can_view_and_upload_but_cannot_invoice_assign_or_notify(): void
    {
        $a = $this->createOrganization('A');
        $driver = $this->createUserForOrganization($a, User::ROLE_EMPLOYEE_STANDARD);
        $customer = $this->createCustomerForOrganization($a);
        $job = $this->createJobForOrganization($a, $customer);
        $contact = $this->createCustomerContact($customer);

        $this->open($driver, $job->id)->assertOk();

        // Authorized for uploads: fails on the missing file, not on permission.
        $this->open($driver, $job->id)->call('uploadFile')->assertOk()->assertHasErrors(['file']);

        $this->open($driver, $job->id)->call('generateInvoice')->assertForbidden();
        $this->open($driver, $job->id)->call('notifyCustomerContact', $contact->id)->assertForbidden();
        $this->open($driver, $job->id)
            ->set('assignment.car_driver_id', $driver->id)
            ->set('assignment.vehicle_position', 'lead')
            ->call('assignJob')
            ->assertForbidden();

        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, UserLog::count());
    }

    public function test_assignment_rejects_a_driver_or_vehicle_from_another_organization(): void
    {
        $a = $this->createOrganization('A');
        $b = $this->createOrganization('B');
        $manager = $this->createUserForOrganization($a, User::ROLE_EMPLOYEE_MANAGER);
        $driverB = $this->createUserForOrganization($b, User::ROLE_EMPLOYEE_STANDARD);
        $vehicleB = $this->createVehicleForOrganization($b);
        $job = $this->createJobForOrganization($a);

        $this->open($manager, $job->id)
            ->set('assignment.car_driver_id', $driverB->id)
            ->set('assignment.vehicle_id', $vehicleB->id)
            ->set('assignment.vehicle_position', 'lead')
            ->call('assignJob')
            ->assertHasErrors(['assignment.car_driver_id', 'assignment.vehicle_id']);

        $this->assertSame(0, UserLog::count());
    }

    public function test_manager_can_assign_their_own_driver_and_generate_an_invoice(): void
    {
        $a = $this->createOrganization('A');
        $manager = $this->createUserForOrganization($a, User::ROLE_EMPLOYEE_MANAGER);
        $driverA = $this->createUserForOrganization($a, User::ROLE_EMPLOYEE_STANDARD);
        $job = $this->createJobForOrganization($a);

        $this->open($manager, $job->id)
            ->set('assignment.car_driver_id', $driverA->id)
            ->set('assignment.vehicle_position', 'lead')
            ->call('assignJob')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('user_logs', ['job_id' => $job->id, 'car_driver_id' => $driverA->id]);

        $this->open($manager, $job->id)->call('generateInvoice')->assertHasNoErrors();
        $this->assertSame(1, Invoice::where('pilot_car_job_id', $job->id)->count());
    }

    public function test_staff_of_another_organization_cannot_open_the_job(): void
    {
        $a = $this->createOrganization('A');
        $b = $this->createOrganization('B');
        $adminB = $this->createUserForOrganization($b, User::ROLE_ADMIN);
        $job = $this->createJobForOrganization($a);

        $this->open($adminB, $job->id)->assertForbidden();
        $this->actingAs($adminB)->get(route('my.jobs.show', $job))->assertForbidden();
    }

    public function test_only_admins_and_managers_can_create_jobs(): void
    {
        $a = $this->createOrganization('A');
        $driver = $this->createUserForOrganization($a, User::ROLE_EMPLOYEE_STANDARD);
        $manager = $this->createUserForOrganization($a, User::ROLE_EMPLOYEE_MANAGER);

        $this->actingAs($driver)->get(route('my.jobs.create'))->assertForbidden();
        Livewire::actingAs($driver)->test(CreatePilotCarJob::class)->assertForbidden();

        $this->actingAs($manager)->get(route('my.jobs.create'))->assertOk();
        Livewire::actingAs($manager)->test(CreatePilotCarJob::class)->assertOk();
    }
}
