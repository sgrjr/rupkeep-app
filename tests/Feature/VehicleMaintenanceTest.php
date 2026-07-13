<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMaintenanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VehicleMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_update_vehicle_details_and_assignment(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create([
            'organization_id' => $organization->id,
        ]);

        $driver = User::factory()->standard()->create([
            'organization_id' => $organization->id,
        ]);

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Escort 101',
            'odometer' => 120000,
        ]);

        $response = $this->actingAs($manager)->put(route('my.vehicles.update', $vehicle), [
            'name' => 'Escort 101 - Updated',
            'odometer' => 125000,
            'last_service_mileage' => 124500,
            'last_oil_change_at' => '2025-10-01',
            'next_oil_change_due_at' => '2025-12-01',
            'last_inspection_at' => '2025-09-15',
            'next_inspection_due_at' => '2026-03-15',
            'current_user_id' => $driver->id,
            'current_assignment_started_at' => '2025-11-01',
            'current_assignment_notes' => 'Assigned for winter routes',
            'is_in_service' => false,
        ]);

        $response->assertRedirect(route('my.vehicles.edit', $vehicle));

        $vehicle->refresh();

        $this->assertSame('Escort 101 - Updated', $vehicle->name);
        $this->assertSame(125000, $vehicle->odometer);
        $this->assertSame(124500, $vehicle->last_service_mileage);
        $this->assertEquals('2025-10-01', optional($vehicle->last_oil_change_at)->toDateString());
        $this->assertEquals('2025-12-01', optional($vehicle->next_oil_change_due_at)->toDateString());
        $this->assertEquals('2025-09-15', optional($vehicle->last_inspection_at)->toDateString());
        $this->assertEquals('2026-03-15', optional($vehicle->next_inspection_due_at)->toDateString());
        $this->assertSame($driver->id, $vehicle->current_user_id);
        $this->assertEquals('2025-11-01', optional($vehicle->current_assignment_started_at)->toDateString());
        $this->assertSame('Assigned for winter routes', $vehicle->current_assignment_notes);
        $this->assertFalse($vehicle->is_in_service);
    }

    public function test_new_vehicle_defaults_to_in_garage(): void
    {
        $organization = Organization::factory()->create();

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->assertTrue($vehicle->fresh()->is_in_garage,
            'A newly created vehicle should default to being in the garage.');
    }

    public function test_manager_can_toggle_vehicle_in_garage_flag(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create([
            'organization_id' => $organization->id,
        ]);
        $vehicle = Vehicle::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Escort 202',
            'is_in_garage' => true,
        ]);

        // Omitting the checkbox (as an unchecked HTML checkbox does) marks it out on a job.
        $this->actingAs($manager)->put(route('my.vehicles.update', $vehicle), [
            'name' => 'Escort 202',
        ])->assertRedirect(route('my.vehicles.edit', $vehicle));

        $this->assertFalse($vehicle->fresh()->is_in_garage,
            'An unchecked in-garage checkbox should set the vehicle out on a job.');

        // Checking it again brings it back to the garage.
        $this->actingAs($manager)->put(route('my.vehicles.update', $vehicle), [
            'name' => 'Escort 202',
            'is_in_garage' => '1',
        ])->assertRedirect(route('my.vehicles.edit', $vehicle));

        $this->assertTrue($vehicle->fresh()->is_in_garage);
    }

    public function test_manager_can_log_vehicle_maintenance_record(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create([
            'organization_id' => $organization->id,
        ]);
        $vehicle = Vehicle::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $payload = [
            'type' => VehicleMaintenanceRecord::TYPE_OIL_CHANGE,
            'title' => 'Full synthetic oil change',
            'performed_at' => '2025-11-05',
            'next_due_at' => '2026-02-05',
            'mileage' => 126500,
            'cost' => 129.95,
            'notes' => 'Used Mobil 1 5W-30. Replaced oil filter.',
        ];

        $response = $this->actingAs($manager)->post(route('my.vehicles.maintenance.store', $vehicle), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('vehicle_maintenance_records', [
            'vehicle_id' => $vehicle->id,
            'organization_id' => $organization->id,
            'type' => VehicleMaintenanceRecord::TYPE_OIL_CHANGE,
            'title' => 'Full synthetic oil change',
            'mileage' => 126500,
        ]);

        $record = VehicleMaintenanceRecord::first();
        $this->assertSame($manager->id, $record->created_by);
        $this->assertEquals('2025-11-05', optional($record->performed_at)->toDateString());
        $this->assertEquals('2026-02-05', optional($record->next_due_at)->toDateString());
        $this->assertEquals('129.95', $record->cost);
        $this->assertSame('Used Mobil 1 5W-30. Replaced oil filter.', $record->notes);
    }
}


