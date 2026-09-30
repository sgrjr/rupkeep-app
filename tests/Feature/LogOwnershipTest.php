<?php

namespace Tests\Feature;

use App\Livewire\EditUserLog;
use App\Livewire\LogExtraCharges;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fixtures\CreatesMultiTenantFixtures;
use Tests\TestCase;

/**
 * TASK-435 regression. UserLogPolicy::update admitted any standard employee
 * in the organization, so one driver could open a colleague's log, rewrite
 * it, reassign it (texting the colleague) and override its billable miles.
 * Drivers now edit only their own log; reassignment and billable overrides
 * are a manager's (`manage`), and the driver/vehicle rules are org-scoped.
 */
class LogOwnershipTest extends TestCase
{
    use RefreshDatabase, CreatesMultiTenantFixtures;

    private $org;
    private User $driver;
    private User $coworker;
    private User $manager;
    private UserLog $log;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->org = $this->createOrganization('A');
        $this->driver = $this->createUserForOrganization($this->org, User::ROLE_EMPLOYEE_STANDARD);
        $this->coworker = $this->createUserForOrganization($this->org, User::ROLE_EMPLOYEE_STANDARD);
        $this->manager = $this->createUserForOrganization($this->org, User::ROLE_EMPLOYEE_MANAGER);
        $customer = $this->createCustomerForOrganization($this->org);
        $job = $this->createJobForOrganization($this->org, $customer);
        $vehicle = $this->createVehicleForOrganization($this->org);
        $contact = $this->createCustomerContact($customer);

        $this->log = $this->createLogForOrganization($this->org, $job, $this->driver, $vehicle, $contact, [
            'approval_status' => 'confirmed',
            'start_mileage' => 0,
            'end_mileage' => 100,
            'start_job_mileage' => 0,
            'end_job_mileage' => 80,
        ]);
    }

    public function test_policy_gives_drivers_their_own_log_only(): void
    {
        $this->assertTrue($this->driver->can('update', $this->log));
        $this->assertFalse($this->coworker->can('update', $this->log));
        $this->assertTrue($this->manager->can('update', $this->log));

        $this->assertFalse($this->driver->can('manage', $this->log));
        $this->assertTrue($this->manager->can('manage', $this->log));
    }

    public function test_coworker_cannot_open_or_charge_a_colleagues_log(): void
    {
        Livewire::actingAs($this->coworker)
            ->test(EditUserLog::class, ['log' => $this->log])
            ->assertForbidden();

        // LogExtraCharges does not authorize in mount, so its actions must.
        Livewire::actingAs($this->coworker)
            ->test(LogExtraCharges::class, ['log' => $this->log])
            ->set('description', 'Tolls')
            ->set('amount', 12)
            ->call('addCharge')
            ->assertForbidden();

        $this->assertSame(0, $this->log->extraCharges()->count());
    }

    public function test_driver_edits_their_own_log_but_cannot_reassign_or_override_billing(): void
    {
        Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $this->log])
            ->assertOk()
            ->assertDontSeeHtml('id="car_driver_id"')
            ->assertDontSeeHtml('id="billable_miles"')
            ->set('form.truck_no', 'T-42')
            ->call('saveLog')
            ->assertHasNoErrors();

        $this->assertSame('T-42', $this->log->fresh()->truck_no);

        Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $this->log])
            ->set('form.car_driver_id', $this->coworker->id)
            ->call('saveLog')
            ->assertHasErrors(['form.car_driver_id']);

        Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $this->log])
            ->set('form.billable_miles', 999)
            ->call('saveLog')
            ->assertHasErrors(['form.billable_miles']);

        $fresh = $this->log->fresh();
        $this->assertSame($this->driver->id, $fresh->car_driver_id);
        $this->assertNull($fresh->billable_miles);
    }

    public function test_manager_can_reassign_and_override_but_only_within_the_organization(): void
    {
        $outsider = $this->createUserForOrganization($this->createOrganization('B'), User::ROLE_EMPLOYEE_STANDARD);

        Livewire::actingAs($this->manager)
            ->test(EditUserLog::class, ['log' => $this->log])
            ->assertSeeHtml('id="car_driver_id"')
            ->set('form.car_driver_id', $outsider->id)
            ->call('saveLog')
            ->assertHasErrors(['form.car_driver_id']);

        $this->assertSame($this->driver->id, $this->log->fresh()->car_driver_id);

        Livewire::actingAs($this->manager)
            ->test(EditUserLog::class, ['log' => $this->log])
            ->set('form.car_driver_id', $this->coworker->id)
            ->set('form.billable_miles', 95)
            ->call('saveLog')
            ->assertHasNoErrors();

        $fresh = $this->log->fresh();
        $this->assertSame($this->coworker->id, $fresh->car_driver_id);
        $this->assertEquals(95, (float) $fresh->billable_miles);
    }
}
