<?php

namespace Tests\Feature;

use App\Livewire\CreatePilotCarJob;
use App\Livewire\EditPilotCarJob;
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

class PilotCarJobRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_rate_value_is_assigned_when_creating_job(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create([
            'organization_id' => $organization->id,
        ]);
        $customer = Customer::factory()->create([
            'organization_id' => $organization->id,
        ]);

        Livewire::actingAs($manager)->test(CreatePilotCarJob::class)
            ->set('form.customer_id', $customer->id)
            ->set('form.job_no', 'JOB-1001')
            ->set('form.load_no', 'LOAD-123')
            ->set('form.pickup_address', '123 Pickup St')
            ->set('form.delivery_address', '456 Delivery Ave')
            ->set('form.memo', 'Test job')
            ->set('form.rate_code', 'per_mile_rate_2_00')
            ->call('createJob');

        $job = PilotCarJob::firstOrFail();

        $this->assertSame('per_mile_rate_2_00', $job->rate_code);
        $this->assertSame('2.00', $job->rate_value);
    }

    public function test_rate_value_is_sanitized_and_saved_when_editing_job(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create([
            'organization_id' => $organization->id,
        ]);
        $customer = Customer::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $job = PilotCarJob::create([
            'job_no' => 'JOB-2001',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'load_no' => 'LOAD-XYZ',
            'pickup_address' => 'Pickup',
            'delivery_address' => 'Delivery',
            'rate_code' => 'per_mile_rate_2_25',
        ]);

        Livewire::actingAs($manager)->test(EditPilotCarJob::class, ['job' => $job->id])
            ->set('form.rate_code', 'per_mile_rate_2_75')
            ->set('form.rate_value', '2.85')
            ->call('saveJob')
            ->assertHasNoErrors();

        $job->refresh();

        $this->assertSame('per_mile_rate_2_75', $job->rate_code);
        $this->assertSame('2.85', $job->rate_value);
    }

    public function test_invoice_values_use_billable_miles_override(): void
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $driver = User::factory()->standard()->create([
            'organization_id' => $organization->id,
        ]);
        $vehicle = Vehicle::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $truckDriver = CustomerContact::create([
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'name' => 'Truck Driver',
            'phone' => '555-0000',
        ]);

        $job = PilotCarJob::create([
            'job_no' => 'JOB-override',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'load_no' => 'LOAD-override',
            'pickup_address' => 'Pickup',
            'delivery_address' => 'Delivery',
            'rate_code' => 'per_mile_rate_2_00',
            'rate_value' => '2.00',
        ]);

        UserLog::create([
            'job_id' => $job->id,
            'car_driver_id' => $driver->id,
            'truck_driver_id' => $truckDriver->id,
            'vehicle_id' => $vehicle->id,
            'vehicle_position' => null,
            'start_mileage' => 100,
            'end_mileage' => 300,
            'start_job_mileage' => 100,
            'end_job_mileage' => 260,
            'billable_miles' => 180,
            'organization_id' => $organization->id,
            'started_at' => Carbon::now()->subDay(),
            'ended_at' => Carbon::now(),
        ]);

        UserLog::create([
            'job_id' => $job->id,
            'car_driver_id' => $driver->id,
            'truck_driver_id' => $truckDriver->id,
            'vehicle_id' => $vehicle->id,
            'vehicle_position' => null,
            'start_mileage' => 50,
            'end_mileage' => 120,
            'start_job_mileage' => 50,
            'end_job_mileage' => 110,
            'billable_miles' => null,
            'organization_id' => $organization->id,
            'started_at' => Carbon::now()->subDay(),
            'ended_at' => Carbon::now(),
        ]);

        $values = $job->invoiceValues();

        // Billable miles should be override 180 + calculated (110-50)=60 = 240
        $this->assertEquals(240, $values['values']['billable_miles']);
        $this->assertEquals('2.00', $values['values']['rate_value']);
    }
}


