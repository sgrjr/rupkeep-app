<?php

namespace Tests\Feature;

use App\Livewire\AnnualVehicleReportModal;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use App\Models\Vehicle;
use App\Models\VehicleMaintenanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-476. The report's query and arithmetic lived twice (page and modal),
 * guarded only by a 200-status check; raw dates 500'd or silently showed
 * nothing; and a vehicle retired mid-year vanished with its miles and its
 * maintenance cost.
 */
class AnnualVehicleReportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $manager;

    private Vehicle $lead;

    private Vehicle $chase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->organization = Organization::factory()->create();
        $this->manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $job = PilotCarJob::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);

        $this->lead = Vehicle::create(['name' => 'Car 1', 'organization_id' => $this->organization->id, 'odometer' => 0, 'odometer_updated_at' => now()]);
        $this->chase = Vehicle::create(['name' => 'Car 2', 'organization_id' => $this->organization->id, 'odometer' => 0, 'odometer_updated_at' => now()]);

        $drivers = User::factory()->standard()->count(2)->create(['organization_id' => $this->organization->id]);

        // Lead car: clock on 1000, job 1030..1230, clock off 1250. 250 driven:
        // 30 approach (deadhead), 200 under load (billable), 20 home (release).
        UserLog::create([
            'job_id' => $job->id, 'car_driver_id' => $drivers[0]->id, 'vehicle_id' => $this->lead->id,
            'organization_id' => $this->organization->id, 'vehicle_position' => 'lead', 'approval_status' => 'confirmed',
            'start_mileage' => 1000, 'start_job_mileage' => 1030, 'end_job_mileage' => 1230, 'end_mileage' => 1250,
            'dead_head_driven' => 30, 'started_at' => now()->startOfYear()->addDays(10),
        ]);

        // Chase car: 500..515..715..760. 260 driven: 15 deadhead, 200 billable, 45 release.
        UserLog::create([
            'job_id' => $job->id, 'car_driver_id' => $drivers[1]->id, 'vehicle_id' => $this->chase->id,
            'organization_id' => $this->organization->id, 'vehicle_position' => 'chase', 'approval_status' => 'confirmed',
            'start_mileage' => 500, 'start_job_mileage' => 515, 'end_job_mileage' => 715, 'end_mileage' => 760,
            'dead_head_driven' => 15, 'started_at' => now()->startOfYear()->addDays(10),
        ]);

        // A second lead-car trip a month later, clocking on 40 miles past the
        // last clock-off: personal miles.
        UserLog::create([
            'job_id' => $job->id, 'car_driver_id' => $drivers[0]->id, 'vehicle_id' => $this->lead->id,
            'organization_id' => $this->organization->id, 'vehicle_position' => 'lead', 'approval_status' => 'confirmed',
            'start_mileage' => 1290, 'start_job_mileage' => 1300, 'end_job_mileage' => 1400, 'end_mileage' => 1410,
            'dead_head_driven' => 10, 'started_at' => now()->startOfYear()->addDays(40),
        ]);
    }

    private function row(array $rows, Vehicle $vehicle): array
    {
        foreach ($rows as $row) {
            if ($row['vehicle']->id === $vehicle->id) {
                return $row;
            }
        }

        $this->fail("No row for {$vehicle->name}");
    }

    public function test_the_page_reports_total_billable_deadhead_release_and_personal_miles_per_car(): void
    {
        $response = $this->actingAs($this->manager)->get(route('my.reports.annual-vehicle-report'))->assertOk();
        $rows = $response->viewData('reportData');

        $lead = $this->row($rows, $this->lead);
        $this->assertEquals(370.0, $lead['total_miles'], '250 + 120');
        $this->assertEquals(300.0, $lead['billable_miles'], '200 + 100');
        $this->assertEquals(40.0, $lead['deadhead_miles'], '30 + 10');
        $this->assertEquals(30.0, $lead['release_miles'], '20 + 10');
        $this->assertEquals(40.0, $lead['personal_miles'], '1250 to 1290 between trips');
        $this->assertSame(2, $lead['logs_count']);

        $chase = $this->row($rows, $this->chase);
        $this->assertEquals(260.0, $chase['total_miles']);
        $this->assertEquals(200.0, $chase['billable_miles']);
        $this->assertEquals(15.0, $chase['deadhead_miles']);
        $this->assertEquals(45.0, $chase['release_miles']);
        $this->assertEquals(0.0, $chase['personal_miles']);

        // Every mile is accounted for, by name, never as a residual.
        foreach ([$lead, $chase] as $row) {
            $this->assertEquals($row['total_miles'], $row['billable_miles'] + $row['deadhead_miles'] + $row['release_miles']);
        }
    }

    public function test_the_modal_shows_the_same_figures_as_the_page(): void
    {
        $page = $this->actingAs($this->manager)->get(route('my.reports.annual-vehicle-report'))->viewData('reportData');

        $modal = Livewire::actingAs($this->manager)
            ->test(AnnualVehicleReportModal::class, ['vehicleId' => $this->lead->id])
            ->call('openModal')
            ->assertSet('showModal', true)
            ->get('reportData');

        $this->assertCount(1, $modal);

        foreach (['total_miles', 'billable_miles', 'deadhead_miles', 'release_miles', 'personal_miles', 'logs_count'] as $key) {
            $this->assertEquals($this->row($page, $this->lead)[$key], $modal[0][$key], $key);
        }
    }

    public function test_a_retired_vehicle_keeps_its_miles_and_maintenance_in_the_report(): void
    {
        VehicleMaintenanceRecord::create([
            'vehicle_id' => $this->chase->id,
            'organization_id' => $this->organization->id,
            'type' => 'oil_change',
            'cost' => 89.5,
            'performed_at' => now()->startOfYear()->addDays(20),
        ]);

        $this->chase->delete();

        $rows = $this->actingAs($this->manager)->get(route('my.reports.annual-vehicle-report'))->assertOk()->viewData('reportData');
        $chase = $this->row($rows, $this->chase);

        $this->assertEquals(260.0, $chase['total_miles']);
        $this->assertEquals(89.5, $chase['maintenance_total_cost']);
        $this->assertSame(1, $chase['maintenance_count']);
    }

    public function test_bad_dates_fall_back_to_the_year_with_a_message_instead_of_a_500(): void
    {
        $this->actingAs($this->manager)
            ->get(route('my.reports.annual-vehicle-report', ['start_date' => 'garbage', 'end_date' => '2026-02-01']))
            ->assertRedirect(route('my.reports.annual-vehicle-report'))
            ->assertSessionHas('error');

        $this->actingAs($this->manager)
            ->get(route('my.reports.annual-vehicle-report', ['start_date' => '2026-06-01', 'end_date' => '2026-01-01']))
            ->assertRedirect(route('my.reports.annual-vehicle-report'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'end date'));

        $start = now()->startOfYear()->format('Y-m-d');
        $end = now()->startOfYear()->addDays(20)->format('Y-m-d');

        $rows = $this->actingAs($this->manager)
            ->get(route('my.reports.annual-vehicle-report', ['start_date' => $start, 'end_date' => $end]))
            ->assertOk()
            ->viewData('reportData');

        $this->assertEquals(250.0, $this->row($rows, $this->lead)['total_miles'], 'only the first trip falls inside the range');
    }
}
