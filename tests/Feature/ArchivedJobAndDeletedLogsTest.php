<?php

namespace Tests\Feature;

use App\Livewire\EditUserLog;
use App\Livewire\ShowPilotCarJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-457. The "Deleted Job Logs" section was fed only by restoreLog(),
 * so it never rendered on load and nothing could be restored from the UI.
 * UserLog::job() did not see archived jobs, so a log of an archived job
 * threw on open. Actions on an archived job still ran under its "archived"
 * banner, and a missing job id on the edit page was a 500.
 */
class ArchivedJobAndDeletedLogsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private Customer $customer;
    private User $manager;
    private User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->organization = Organization::factory()->create();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
        $this->driver = User::factory()->standard()->create(['organization_id' => $this->organization->id]);
    }

    private function job(string $no = 'JOB-457'): PilotCarJob
    {
        return PilotCarJob::create([
            'job_no' => $no,
            'customer_id' => $this->customer->id,
            'organization_id' => $this->organization->id,
            'pickup_address' => 'Gorham, ME',
            'delivery_address' => 'Boston, MA',
            'rate_code' => 'flat_rate',
            'rate_value' => '500.00',
        ]);
    }

    private function log(PilotCarJob $job): UserLog
    {
        return UserLog::create([
            'job_id' => $job->id,
            'organization_id' => $this->organization->id,
            'car_driver_id' => $this->driver->id,
            'approval_status' => 'confirmed',
            'billable_miles' => 100,
            'completed_at' => now(),
        ]);
    }

    public function test_deleted_logs_are_listed_on_load_and_can_be_restored(): void
    {
        $job = $this->job();
        $kept = $this->log($job);
        $deleted = $this->log($job);
        $deleted->delete();

        $component = Livewire::actingAs($this->manager)
            ->test(ShowPilotCarJob::class, ['job' => $job->id])
            ->assertSee('Deleted Job Logs')
            ->assertSee('Deleted Log ID: ' . $deleted->id)
            ->assertSee('Restore Log');

        $component->call('restoreLog', $deleted->id)
            ->assertSee('Log restored.')
            ->assertDontSee('Deleted Job Logs');

        $this->assertNull($deleted->fresh()->deleted_at);
        $this->assertNotNull($kept->fresh());
    }

    public function test_a_log_of_an_archived_job_still_opens(): void
    {
        $job = $this->job();
        $log = $this->log($job);
        $job->delete();

        $this->assertNotNull($log->fresh()->job, 'the relation sees the archived job');

        Livewire::actingAs($this->manager)
            ->test(EditUserLog::class, ['log' => $log->fresh()])
            ->assertOk()
            ->assertSee('JOB-457');

        $this->actingAs($this->manager)->get(route('logs.edit', $log))->assertOk();
    }

    public function test_an_archived_job_refuses_changes_until_it_is_restored(): void
    {
        $job = $this->job();
        $this->log($job);
        $job->delete();

        $component = Livewire::actingAs($this->manager)
            ->test(ShowPilotCarJob::class, ['job' => $job->id])
            ->assertSee('archived');

        $component->call('generateInvoice')->assertSee('This job is archived');
        $this->assertSame(0, Invoice::count());

        $component->set('assignment.car_driver_id', $this->driver->id)
            ->set('assignment.vehicle_position', 'lead')
            ->call('assignJob')
            ->assertSee('This job is archived');
        $this->assertSame(1, UserLog::where('job_id', $job->id)->count(), 'no second assignment');

        // Restoring a job is an admin's call (PilotCarJobPolicy::restore).
        $component->call('restoreJob')->assertForbidden();
        $this->assertNotNull($job->fresh()->deleted_at);

        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        Livewire::actingAs($admin)->test(ShowPilotCarJob::class, ['job' => $job->id])->call('restoreJob');
        $this->assertNull($job->fresh()->deleted_at);

        // A fresh instance: a 403 leaves a Livewire test instance without a snapshot.
        Livewire::actingAs($this->manager)->test(ShowPilotCarJob::class, ['job' => $job->id])->call('generateInvoice');
        $this->assertSame(1, Invoice::where('pilot_car_job_id', $job->id)->count(), 'restored, it works again');
    }

    public function test_editing_a_job_that_does_not_exist_is_a_404(): void
    {
        $this->actingAs($this->manager)->get(route('my.jobs.edit', 999999))->assertNotFound();
    }
}
