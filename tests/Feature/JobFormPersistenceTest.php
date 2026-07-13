<?php

namespace Tests\Feature;

use App\Livewire\CreatePilotCarJob;
use App\Livewire\EditUserLog;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class JobFormPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creation_persists_all_bound_fields(): void
    {
        $org = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $org->id]);
        $customer = Customer::factory()->create(['organization_id' => $org->id]);
        $driver = User::factory()->standard()->create(['organization_id' => $org->id]);
        $truckDriver = CustomerContact::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'name' => 'Truck Driver',
            'phone' => '555-0100',
        ]);

        Livewire::actingAs($manager)->test(CreatePilotCarJob::class)
            ->set('form.job_no', 'JOB-PERSIST')
            ->set('form.customer_id', $customer->id)
            ->set('form.load_no', 'LOAD-PERSIST')
            ->set('form.pickup_address', '1 Pickup St')
            ->set('form.delivery_address', '2 Delivery Ave')
            ->set('form.scheduled_pickup_at', '2026-07-20 08:00')
            ->set('form.scheduled_delivery_at', '2026-07-21 09:00')
            ->set('form.check_no', 'CHK-777')
            ->set('form.invoice_no', 'INV-555')
            ->set('form.rate_code', 'per_mile_rate_2_00')
            ->set('form.memo', 'internal memo')
            ->set('form.public_memo', 'customer-facing memo')
            ->set('form.default_driver_id', $driver->id)
            ->set('form.default_truck_driver_id', $truckDriver->id)
            ->call('createJob')
            ->assertHasNoErrors();

        $job = PilotCarJob::where('job_no', 'JOB-PERSIST')->firstOrFail();

        // The fields historically prone to silent drops must all persist.
        $this->assertSame($customer->id, $job->customer_id);
        $this->assertSame('LOAD-PERSIST', $job->load_no);
        $this->assertSame('1 Pickup St', $job->pickup_address);
        $this->assertSame('2 Delivery Ave', $job->delivery_address);
        $this->assertSame('CHK-777', $job->check_no);
        $this->assertSame('INV-555', $job->invoice_no);
        $this->assertSame('internal memo', $job->memo);
        $this->assertSame('customer-facing memo', $job->public_memo);
        $this->assertSame($driver->id, $job->default_driver_id);
        $this->assertSame($truckDriver->id, $job->default_truck_driver_id);
        $this->assertNotNull($job->scheduled_pickup_at);
        $this->assertNotNull($job->scheduled_delivery_at);
    }

    public function test_log_edit_persists_driver_and_vehicle_fields(): void
    {
        $org = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $org->id]);
        $customer = Customer::factory()->create(['organization_id' => $org->id]);
        $job = PilotCarJob::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
        ]);
        // "Before" state the log is created with.
        $initialDriver = User::factory()->standard()->create(['organization_id' => $org->id]);
        $initialVehicle = Vehicle::factory()->create(['organization_id' => $org->id]);
        $initialTruckDriver = CustomerContact::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'name' => 'Old Truck Driver',
            'phone' => '555-0000',
        ]);

        // "After" state we will edit the log to.
        $driver = User::factory()->standard()->create(['organization_id' => $org->id]);
        $vehicle = Vehicle::factory()->create(['organization_id' => $org->id]);
        $truckDriver = CustomerContact::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'name' => 'Truck Driver',
            'phone' => '555-0200',
        ]);

        $log = UserLog::create([
            'job_id' => $job->id,
            'organization_id' => $org->id,
            'car_driver_id' => $initialDriver->id,
            'truck_driver_id' => $initialTruckDriver->id,
            'vehicle_id' => $initialVehicle->id,
            'vehicle_position' => 'chase',
            'truck_no' => 'OLD-TRUCK',
            'trailer_no' => 'OLD-TRAILER',
            'started_at' => Carbon::now()->subDay(),
            'ended_at' => Carbon::now(),
        ]);

        // Act as the manager (not the assigned driver) to avoid the pending-approval guard.
        Livewire::actingAs($manager)->test(EditUserLog::class, ['log' => $log])
            ->set('form.car_driver_id', $driver->id)
            ->set('form.truck_driver_id', $truckDriver->id)
            ->set('form.vehicle_id', $vehicle->id)
            ->set('form.vehicle_position', 'lead')
            ->set('form.truck_no', 'TRUCK-9')
            ->set('form.trailer_no', 'TRAILER-9')
            ->call('saveLog')
            ->assertHasNoErrors();

        $log->refresh();

        $this->assertSame($driver->id, $log->car_driver_id);
        $this->assertSame($truckDriver->id, $log->truck_driver_id);
        $this->assertSame($vehicle->id, $log->vehicle_id);
        $this->assertSame('lead', $log->vehicle_position);
        $this->assertSame('TRUCK-9', $log->truck_no);
        $this->assertSame('TRAILER-9', $log->trailer_no);
    }

    public function test_log_edit_saves_when_driver_and_vehicle_are_cleared_to_none(): void
    {
        // The log editor offers a "(none selected)" option for car driver and
        // vehicle. Before TASK-318 those columns were wrongly NOT NULL, so
        // choosing "none" (null) failed a constraint and the whole save was
        // silently swallowed. This must now persist.
        $org = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $org->id]);
        $customer = Customer::factory()->create(['organization_id' => $org->id]);
        $job = PilotCarJob::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
        ]);
        $driver = User::factory()->standard()->create(['organization_id' => $org->id]);
        $vehicle = Vehicle::factory()->create(['organization_id' => $org->id]);

        $log = UserLog::create([
            'job_id' => $job->id,
            'organization_id' => $org->id,
            'car_driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'started_at' => Carbon::now()->subDay(),
            'ended_at' => Carbon::now(),
        ]);

        Livewire::actingAs($manager)->test(EditUserLog::class, ['log' => $log])
            ->set('form.car_driver_id', null)
            ->set('form.vehicle_id', null)
            ->set('form.extra_load_stops_count', null)  // empty count must not abort the save
            ->set('form.memo', 'edited with driver cleared')
            ->call('saveLog')
            ->assertHasNoErrors();

        $log->refresh();

        $this->assertNull($log->car_driver_id, 'Clearing the car driver must persist as null.');
        $this->assertNull($log->vehicle_id, 'Clearing the vehicle must persist as null.');
        $this->assertSame(0, $log->extra_load_stops_count, 'An empty stop count must save as 0, not abort.');
        $this->assertSame('edited with driver cleared', $log->memo, 'The rest of the edit must persist.');
    }
}
