<?php

namespace Tests\Feature;

use App\Events\JobAssigned;
use App\Livewire\CreatePilotCarJob;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-494 (drive 003). A default driver chosen when creating a job was
 * stored on the job and nothing else: no log, no notice, so the driver never
 * learned of the job. It now gets a pending log, which fires JobAssigned.
 */
class JobDefaultDriverAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $manager;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->organization = Organization::factory()->create();
        $this->manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function create(array $fields)
    {
        $component = Livewire::actingAs($this->manager)->test(CreatePilotCarJob::class)
            ->set('form.job_no', 'JOB-494')
            ->set('form.customer_id', $this->customer->id)
            ->set('form.load_no', 'LOAD-494')
            ->set('form.pickup_address', '1 Pickup St')
            ->set('form.delivery_address', '2 Delivery Ave')
            ->set('form.scheduled_pickup_at', '2026-12-01 08:00');

        foreach ($fields as $key => $value) {
            $component->set($key, $value);
        }

        return $component->call('createJob');
    }

    public function test_a_default_driver_gets_a_pending_log_and_is_notified(): void
    {
        Event::fake([JobAssigned::class]);

        $driver = User::factory()->standard()->create(['organization_id' => $this->organization->id]);

        $this->create(['form.default_driver_id' => $driver->id])->assertHasNoErrors();

        $job = PilotCarJob::where('job_no', 'JOB-494')->sole();
        $log = UserLog::where('job_id', $job->id)->sole();

        $this->assertSame($driver->id, (int) $log->car_driver_id);
        $this->assertSame('pending', $log->approval_status);
        $this->assertSame($job->organization_id, (int) $log->organization_id);

        Event::assertDispatched(JobAssigned::class, 1);
    }

    public function test_no_default_driver_means_no_log(): void
    {
        Event::fake([JobAssigned::class]);

        $this->create([])->assertHasNoErrors();

        $job = PilotCarJob::where('job_no', 'JOB-494')->sole();

        $this->assertSame(0, UserLog::where('job_id', $job->id)->count());
        Event::assertNotDispatched(JobAssigned::class);
    }

    public function test_a_driver_from_another_organization_is_refused(): void
    {
        Event::fake([JobAssigned::class]);

        $outsider = User::factory()->standard()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->create(['form.default_driver_id' => $outsider->id])
            ->assertHasErrors('form.default_driver_id');

        $this->assertSame(0, PilotCarJob::where('job_no', 'JOB-494')->count());
        Event::assertNotDispatched(JobAssigned::class);
    }

    public function test_a_customer_login_is_neither_offered_nor_accepted_as_a_driver(): void
    {
        Event::fake([JobAssigned::class]);

        $portalUser = User::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'organization_role' => User::ROLE_CUSTOMER,
        ]);

        $component = Livewire::actingAs($this->manager)->test(CreatePilotCarJob::class);
        $this->assertNotContains($portalUser->id, array_column($component->get('drivers'), 'value'));

        $this->create(['form.default_driver_id' => $portalUser->id])
            ->assertHasErrors('form.default_driver_id');

        Event::assertNotDispatched(JobAssigned::class);
    }
}
