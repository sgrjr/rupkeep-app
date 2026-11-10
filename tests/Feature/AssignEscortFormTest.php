<?php

namespace Tests\Feature;

use App\Livewire\ShowPilotCarJob;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-496 / TASK-497 (drive 003). The Assign driver & vehicle selects showed
 * a driver and car the browser had restored on its own, which Livewire never
 * received. The save failed as "required", or stored the log without its
 * vehicle when only some selects were picked again.
 */
class AssignEscortFormTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $manager;

    private User $driver;

    private PilotCarJob $job;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Event::fake();

        $this->organization = Organization::factory()->create();
        $this->manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
        $this->driver = User::factory()->standard()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->vehicle = Vehicle::create(['name' => 'Car 001', 'organization_id' => $this->organization->id, 'odometer' => 1000, 'odometer_updated_at' => now()]);

        $this->job = PilotCarJob::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'scheduled_pickup_at' => now()->addDays(2),
        ]);
    }

    public function test_the_assign_selects_refuse_browser_restored_values(): void
    {
        $html = Livewire::actingAs($this->manager)
            ->test(ShowPilotCarJob::class, ['job' => $this->job->id])
            ->html();

        $this->assertMatchesRegularExpression('/<form wire:submit="assignJob" autocomplete="off"/', $html);

        foreach (['car_driver_id', 'vehicle_id', 'vehicle_position'] as $field) {
            $this->assertMatchesRegularExpression(
                '/<select wire:model="assignment\.'.$field.'" autocomplete="off" x-init="[^"]*dispatchEvent/',
                $html,
                "assignment.{$field} must opt out of browser restoration and sync any restored value"
            );
        }
    }

    public function test_the_log_keeps_the_driver_vehicle_and_position_that_were_chosen(): void
    {
        Livewire::actingAs($this->manager)
            ->test(ShowPilotCarJob::class, ['job' => $this->job->id])
            ->set('assignment.car_driver_id', $this->driver->id)
            ->set('assignment.vehicle_id', $this->vehicle->id)
            ->set('assignment.vehicle_position', 'lead')
            ->call('assignJob')
            ->assertHasNoErrors()
            ->assertSet('assignment.car_driver_id', null)
            ->assertSet('assignment.vehicle_id', null);

        $log = UserLog::where('job_id', $this->job->id)->sole();

        $this->assertSame($this->driver->id, (int) $log->car_driver_id);
        $this->assertSame($this->vehicle->id, (int) $log->vehicle_id);
        $this->assertSame('lead', $log->vehicle_position);
    }

    public function test_a_second_escort_after_the_first_is_assigned_cleanly(): void
    {
        $other = User::factory()->standard()->create(['organization_id' => $this->organization->id]);
        $car2 = Vehicle::create(['name' => 'Car 002', 'organization_id' => $this->organization->id, 'odometer' => 1000, 'odometer_updated_at' => now()]);

        Livewire::actingAs($this->manager)
            ->test(ShowPilotCarJob::class, ['job' => $this->job->id])
            ->set('assignment.car_driver_id', $this->driver->id)
            ->set('assignment.vehicle_id', $this->vehicle->id)
            ->set('assignment.vehicle_position', 'lead')
            ->call('assignJob')
            ->assertHasNoErrors()
            ->set('assignment.car_driver_id', $other->id)
            ->set('assignment.vehicle_id', $car2->id)
            ->set('assignment.vehicle_position', 'chase')
            ->call('assignJob')
            ->assertHasNoErrors();

        $logs = UserLog::where('job_id', $this->job->id)->orderBy('id')->get();

        $this->assertSame([$this->vehicle->id, $car2->id], $logs->pluck('vehicle_id')->map(fn ($id) => (int) $id)->all());
    }
}
