<?php

namespace Tests\Feature;

use App\Console\Commands\SendVehicleMaintenanceReminders;
use App\Mail\UserNotification;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMaintenanceRecord;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * TASK-466. Logging an oil change or inspection changed nothing: the badges
 * and the reminder digest read the vehicle's own last/next columns, which
 * only the vehicle form wrote, so every logged service stayed "overdue"
 * forever. The reminder also stamped vehicles as reminded even when every
 * email failed, and the scheduler carried an hourly quote nobody read.
 */
class VehicleMaintenanceDueDatesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $manager;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-30 15:00:00');
        config(['app.display_timezone' => 'America/New_York']);

        $this->organization = Organization::factory()->create();
        $this->manager = User::factory()->manager()->create(['organization_id' => $this->organization->id, 'email' => 'mary@example.test']);
        $this->vehicle = Vehicle::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Escort 7',
            'last_oil_change_at' => '2026-01-10',
            'next_oil_change_due_at' => '2026-04-10', // long overdue, per the form
            'next_inspection_due_at' => '2027-03-01',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function record(array $attributes): VehicleMaintenanceRecord
    {
        return $this->vehicle->maintenanceRecords()->create(array_merge([
            'organization_id' => $this->organization->id,
            'created_by' => $this->manager->id,
            'type' => VehicleMaintenanceRecord::TYPE_OIL_CHANGE,
        ], $attributes));
    }

    // -----------------------------------------------------------------
    // A logged service moves the vehicle's dates
    // -----------------------------------------------------------------

    public function test_logging_an_oil_change_updates_the_vehicles_last_and_next_dates(): void
    {
        $this->actingAs($this->manager)->post(route('my.vehicles.maintenance.store', $this->vehicle), [
            'type' => VehicleMaintenanceRecord::TYPE_OIL_CHANGE,
            'performed_at' => '2026-09-28',
            'next_due_at' => '2027-03-28',
            'mileage' => 131000,
        ])->assertRedirect();

        $this->vehicle->refresh();
        $this->assertSame('2026-09-28', $this->vehicle->last_oil_change_at->toDateString());
        $this->assertSame('2027-03-28', $this->vehicle->next_oil_change_due_at->toDateString());
        $this->assertSame('ok', $this->vehicle->getOilChangeStatus(), 'no longer overdue');

        // The other schedule is untouched.
        $this->assertSame('2027-03-01', $this->vehicle->next_inspection_due_at->toDateString());
    }

    public function test_a_record_without_a_next_date_keeps_the_schedule_the_office_set(): void
    {
        $this->record(['type' => VehicleMaintenanceRecord::TYPE_INSPECTION, 'performed_at' => '2026-09-29']);

        $this->vehicle->refresh();
        $this->assertSame('2026-09-29', $this->vehicle->last_inspection_at->toDateString());
        $this->assertSame('2027-03-01', $this->vehicle->next_inspection_due_at->toDateString());
    }

    public function test_the_newest_performed_record_wins_and_deleting_it_falls_back(): void
    {
        $older = $this->record(['performed_at' => '2026-06-01', 'next_due_at' => '2026-09-01']);
        $newer = $this->record(['performed_at' => '2026-09-20', 'next_due_at' => '2026-12-20']);
        // Logged out of order: an older service entered later must not win.
        $this->record(['performed_at' => '2026-03-01', 'next_due_at' => '2026-06-01']);

        $this->assertSame('2026-12-20', $this->vehicle->fresh()->next_oil_change_due_at->toDateString());

        $newer->delete();
        $this->assertSame('2026-09-01', $this->vehicle->fresh()->next_oil_change_due_at->toDateString());
        $this->assertSame('2026-06-01', $this->vehicle->fresh()->last_oil_change_at->toDateString());

        // Repairs and other record types do not touch the schedule.
        $this->record(['type' => VehicleMaintenanceRecord::TYPE_REPAIR, 'performed_at' => '2026-09-29', 'next_due_at' => '2026-10-15']);
        $this->assertSame('2026-09-01', $this->vehicle->fresh()->next_oil_change_due_at->toDateString());
    }

    // -----------------------------------------------------------------
    // What the page calls overdue
    // -----------------------------------------------------------------

    public function test_only_the_latest_record_of_a_type_can_be_overdue_or_upcoming(): void
    {
        $this->record(['performed_at' => '2026-03-01', 'next_due_at' => '2026-06-01', 'title' => 'Spring oil change']);
        $this->record(['performed_at' => '2026-09-20', 'next_due_at' => '2026-12-20', 'title' => 'Fall oil change']);
        $this->record(['type' => VehicleMaintenanceRecord::TYPE_INSPECTION, 'performed_at' => '2026-09-01', 'title' => 'State inspection']);
        $this->record(['type' => VehicleMaintenanceRecord::TYPE_REPAIR, 'performed_at' => '2025-11-01', 'next_due_at' => '2026-01-01', 'title' => 'Brake pads']);

        $html = $this->actingAs($this->manager)->get(route('my.vehicles.edit', $this->vehicle))->assertOk()->getContent();

        $overdue = $this->section($html, 'Overdue Maintenance');
        $upcoming = $this->section($html, 'Upcoming Scheduled Maintenance');

        // The spring oil change was superseded in September: history, not overdue.
        $this->assertStringNotContainsString('Spring oil change', $overdue);
        // The brake job is the newest repair and its follow-up date has passed.
        $this->assertStringContainsString('Brake pads', $overdue);
        $this->assertStringContainsString('Fall oil change', $upcoming);
        // No next date means nothing is scheduled.
        $this->assertStringNotContainsString('State inspection', $upcoming);
    }

    public function test_due_today_is_due_soon_and_yesterday_is_overdue(): void
    {
        // 2026-09-30 15:00 UTC is 11:00 AM Eastern on 30 September.
        $this->vehicle->update(['next_oil_change_due_at' => '2026-09-30', 'next_inspection_due_at' => '2026-09-29']);

        $this->assertSame('due_soon', $this->vehicle->fresh()->getOilChangeStatus());
        $this->assertSame('overdue', $this->vehicle->fresh()->getInspectionStatus());
    }

    // -----------------------------------------------------------------
    // The reminder
    // -----------------------------------------------------------------

    public function test_vehicles_are_stamped_only_when_someone_received_the_reminder(): void
    {
        // Nobody could be reached: no stamp, so tomorrow's run tries again.
        config([
            'mail.default' => 'broken',
            'mail.mailers.broken' => ['transport' => 'unsupported-transport'],
        ]);
        Mail::forgetMailers();

        $this->artisan('vehicles:send-maintenance-reminders')->assertSuccessful();
        $this->assertNull($this->vehicle->fresh()->maintenance_reminder_sent_at);

        // Delivered: stamped.
        config(['mail.default' => 'array']);
        Mail::forgetMailers();
        Mail::fake();

        $this->artisan('vehicles:send-maintenance-reminders')->assertSuccessful();

        Mail::assertSent(UserNotification::class, fn ($mail) => $mail->hasTo('mary@example.test'));
        $this->assertNotNull($this->vehicle->fresh()->maintenance_reminder_sent_at);
    }

    public function test_the_scheduler_runs_the_digest_and_not_the_hourly_quote(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($event) => $event->command)->implode(' ');

        $this->assertStringContainsString('vehicles:send-maintenance-reminders', $commands);
        $this->assertStringNotContainsString('inspire', $commands);
    }

    /** The markup of one titled block on the edit page, up to the next block. */
    private function section(string $html, string $heading): string
    {
        $start = strpos($html, $heading);
        $this->assertNotFalse($start, "$heading should be on the page");

        $end = strpos($html, '<h3', $start + strlen($heading));

        return substr($html, $start, $end === false ? null : $end - $start);
    }
}
