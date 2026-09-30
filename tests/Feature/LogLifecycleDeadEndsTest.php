<?php

namespace Tests\Feature;

use App\Events\LogCompleted;
use App\Livewire\EditUserLog;
use App\Livewire\LogExtraCharges;
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
 * TASK-456. Four dead ends in the log lifecycle:
 *
 *  1. a denied log was frozen forever, even for a manager;
 *  2. an empty log could be marked complete, which notified the office and
 *     locked the driver out of the fields they had skipped;
 *  3. a completed (or denied, or pending) log rendered every field live for
 *     the driver, and the save that followed was refused;
 *  4. every save wrote the job's customer-facing memo back from a page-load
 *     snapshot, so a manager's edit was reverted by any driver's later save,
 *     and any driver could rewrite what the customer reads on the invoice.
 */
class LogLifecycleDeadEndsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private PilotCarJob $job;
    private User $driver;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->driver = User::factory()->standard()->create(['organization_id' => $this->organization->id]);
        $this->manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);

        $this->job = PilotCarJob::create([
            'job_no' => 'JOB-456',
            'customer_id' => $customer->id,
            'organization_id' => $this->organization->id,
            'pickup_address' => 'Gorham, ME',
            'delivery_address' => 'Boston, MA',
            'rate_code' => 'lead_chase_per_mile',
            'rate_value' => '2.00',
            'public_memo' => 'Deliver to gate 4.',
        ]);
    }

    private function log(array $attributes = []): UserLog
    {
        return UserLog::create(array_merge([
            'job_id' => $this->job->id,
            'organization_id' => $this->organization->id,
            'car_driver_id' => $this->driver->id,
            'approval_status' => 'confirmed',
        ], $attributes));
    }

    private function withMiles(array $attributes = []): UserLog
    {
        return $this->log(array_merge([
            'start_mileage' => 1000,
            'end_mileage' => 1150,
        ], $attributes));
    }

    // -----------------------------------------------------------------
    // 1. A denial has a way out
    // -----------------------------------------------------------------

    public function test_a_manager_can_reset_a_denied_log_to_pending(): void
    {
        $log = $this->log(['approval_status' => 'denied', 'approved_at' => now(), 'approved_by_id' => $this->driver->id]);

        Livewire::actingAs($this->manager)
            ->test(EditUserLog::class, ['log' => $log])
            ->assertSee('Reset to Pending')
            ->call('resetToPending')
            ->assertHasNoErrors();

        $log->refresh();
        $this->assertSame('pending', $log->approval_status);
        $this->assertNull($log->approved_at);
        $this->assertNull($log->approved_by_id);

        // The driver is asked again, exactly as on first assignment.
        Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $log])
            ->assertSee('Log Assignment Pending Approval')
            ->assertDontSee('Log Denied')
            ->call('confirmLog');

        $this->assertSame('confirmed', $log->fresh()->approval_status);
    }

    public function test_the_driver_cannot_reset_their_own_denial(): void
    {
        $log = $this->log(['approval_status' => 'denied']);

        Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $log])
            ->assertDontSee('Reset to Pending')
            ->assertSee('Ask a manager to reset it to pending')
            ->call('resetToPending')
            ->assertForbidden();

        $this->assertSame('denied', $log->fresh()->approval_status);
    }

    public function test_a_manager_from_another_organization_cannot_reset_it(): void
    {
        $outsider = User::factory()->manager()->create(['organization_id' => Organization::factory()->create()->id]);
        $log = $this->log(['approval_status' => 'denied']);

        Livewire::actingAs($outsider)
            ->test(EditUserLog::class, ['log' => $log])
            ->assertForbidden();

        $this->assertSame('denied', $log->fresh()->approval_status);
    }

    public function test_only_a_pending_assignment_can_be_confirmed_or_denied(): void
    {
        // The buttons are hidden once answered, but each method is its own
        // endpoint. A driver could deny a log they had already confirmed and
        // filled in, or re-confirm a denial a manager was about to reset.
        $confirmed = $this->withMiles(['completed_at' => now(), 'completed_by_id' => $this->driver->id]);

        Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $confirmed])
            ->call('denyLog')
            ->assertSee('Only a pending assignment can be denied.');

        $this->assertSame('confirmed', $confirmed->fresh()->approval_status);

        $denied = $this->log(['approval_status' => 'denied']);

        Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $denied])
            ->call('confirmLog');

        $this->assertSame('denied', $denied->fresh()->approval_status);
    }

    // -----------------------------------------------------------------
    // 2. An empty log is not a finished log
    // -----------------------------------------------------------------

    public function test_a_log_without_odometer_readings_cannot_be_marked_complete(): void
    {
        Event::fake([LogCompleted::class]);
        $log = $this->log();

        Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $log])
            ->set('form.tolls', '4.50')
            ->call('markComplete')
            ->assertHasErrors(['form.start_mileage', 'form.end_mileage'])
            ->assertSee('Enter the start and end mileage before marking the log complete.')
            ->assertSet('isTripTimingOpen', true);

        $log->refresh();
        $this->assertNull($log->completed_at, 'an empty log must stay open');
        $this->assertNull($log->tolls, 'the refusal happens before the save, so nothing half-lands');
        Event::assertNotDispatched(LogCompleted::class);
    }

    public function test_end_mileage_below_start_mileage_is_refused(): void
    {
        $log = $this->log();

        Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $log])
            ->set('form.start_mileage', 1200)
            ->set('form.end_mileage', 1100)
            ->call('markComplete')
            ->assertHasErrors(['form.end_mileage'])
            ->assertHasNoErrors(['form.start_mileage']);

        $this->assertNull($log->fresh()->completed_at);
    }

    public function test_typing_the_readings_and_completing_in_one_go_works(): void
    {
        Event::fake([LogCompleted::class]);
        $log = $this->log();

        Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $log])
            ->set('form.start_mileage', 1000)
            ->set('form.end_mileage', 1150)
            ->call('markComplete')
            ->assertHasNoErrors();

        $log->refresh();
        $this->assertNotNull($log->completed_at);
        $this->assertSame(1150.0, (float) $log->end_mileage);
        Event::assertDispatched(LogCompleted::class);
    }

    public function test_a_canceled_load_can_be_completed_without_mileage(): void
    {
        $log = $this->log();

        Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $log])
            ->set('form.load_canceled', true)
            ->call('markComplete')
            ->assertHasNoErrors();

        $this->assertNotNull($log->fresh()->completed_at);
    }

    // -----------------------------------------------------------------
    // 3. A closed log looks closed
    // -----------------------------------------------------------------

    /** Every form control on the page, by id. */
    private const FORM_CONTROLS = [
        'vehicle_id', 'vehicle_position', 'memo', 'clock_in', 'clock_out', 'start_mileage', 'end_mileage',
        'started_at', 'ended_at', 'start_job_mileage', 'end_job_mileage', 'dead_head_driven', 'dead_head_billed',
        'extra_load_stops_count', 'tolls', 'hotel', 'wait_time_hours', 'truck_driver_id', 'truck_no', 'trailer_no',
    ];

    private function assertControlsDisabled(string $html, bool $disabled): void
    {
        foreach (self::FORM_CONTROLS as $id) {
            preg_match('/<(?:input|select|textarea)[^>]*\bid="' . $id . '"[^>]*>/', $html, $m);
            $this->assertNotEmpty($m, "#{$id} should be on the page");
            $isDisabled = (bool) preg_match('/\sdisabled(?:=|\s|>)/', $m[0]);
            $this->assertSame($disabled, $isDisabled, "#{$id} should " . ($disabled ? '' : 'not ') . 'be disabled');
        }
    }

    public function test_a_completed_log_is_read_only_for_the_driver(): void
    {
        $log = $this->withMiles(['completed_at' => now(), 'completed_by_id' => $this->driver->id]);

        $html = Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $log])
            ->assertSee('Marked complete: read-only')
            ->assertSee('The fields below are now read-only.')
            ->assertDontSee('Save Changes')
            ->assertDontSee('All changes saved')
            ->html();

        $this->assertControlsDisabled($html, true);
    }

    public function test_a_denied_log_is_read_only_for_everyone(): void
    {
        $log = $this->log(['approval_status' => 'denied']);

        $html = Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $log])
            ->assertSee('Denied: read-only')
            ->html();
        $this->assertControlsDisabled($html, true);

        $html = Livewire::actingAs($this->manager)
            ->test(EditUserLog::class, ['log' => $log])
            ->html();
        $this->assertControlsDisabled($html, true);
    }

    public function test_a_pending_log_is_read_only_for_the_driver_until_they_answer(): void
    {
        $log = $this->log(['approval_status' => 'pending']);

        $html = Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $log])
            ->assertSee('Confirm the assignment above to start editing')
            ->html();
        $this->assertControlsDisabled($html, true);

        // The alert() that used to guard the form is gone with it.
        $this->assertStringNotContainsString('onsubmit=', $html);
    }

    public function test_a_manager_keeps_a_completed_log_editable(): void
    {
        $log = $this->withMiles(['completed_at' => now(), 'completed_by_id' => $this->driver->id]);

        $html = Livewire::actingAs($this->manager)
            ->test(EditUserLog::class, ['log' => $log])
            ->assertDontSee('read-only')
            ->assertSee('Reopen for Edits')
            ->html();

        $this->assertControlsDisabled($html, false);
    }

    public function test_an_open_log_is_editable_by_its_driver(): void
    {
        $html = Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $this->withMiles()])
            ->assertDontSee('read-only')
            ->html();

        $this->assertControlsDisabled($html, false);
    }

    public function test_extra_charges_are_closed_on_a_completed_log_too(): void
    {
        $log = $this->withMiles(['completed_at' => now(), 'completed_by_id' => $this->driver->id]);

        // The charges component is its own endpoint inside the same form.
        Livewire::actingAs($this->driver)
            ->test(LogExtraCharges::class, ['log' => $log])
            ->assertDontSee('wire:click="addCharge"', false)
            ->set('description', 'Extra permit')
            ->set('amount', 40)
            ->call('addCharge')
            ->assertForbidden();

        $this->assertSame(0, $log->extraCharges()->count());

        Livewire::actingAs($this->manager)
            ->test(LogExtraCharges::class, ['log' => $log])
            ->set('description', 'Extra permit')
            ->set('amount', 40)
            ->call('addCharge')
            ->assertHasNoErrors();

        $this->assertSame(1, $log->extraCharges()->count());
    }

    // -----------------------------------------------------------------
    // 4. The customer-facing memo belongs to the office
    // -----------------------------------------------------------------

    public function test_a_drivers_save_does_not_revert_a_managers_memo_edit(): void
    {
        $log = $this->withMiles();

        // Driver has the page open with the memo as it was at load time...
        $page = Livewire::actingAs($this->driver)->test(EditUserLog::class, ['log' => $log]);

        // ...the office changes it underneath...
        $this->job->update(['public_memo' => 'Deliver to gate 7, ask for Sal.']);

        // ...and the driver saves an unrelated field.
        $page->set('form.tolls', '6.00')->call('saveLog')->assertHasNoErrors();

        $this->assertSame('Deliver to gate 7, ask for Sal.', $this->job->fresh()->public_memo);
        $this->assertSame(6.0, (float) $log->fresh()->tolls);
    }

    public function test_a_driver_cannot_change_the_customer_facing_memo(): void
    {
        $log = $this->withMiles();

        Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $log])
            ->assertDontSee('wire:model.blur="form.job_public_memo"', false)
            ->assertSee('Deliver to gate 4.')
            ->set('form.job_public_memo', 'Free delivery, no charge!')
            ->set('form.tolls', '6.00')
            ->call('saveLog')
            ->assertHasErrors(['form.job_public_memo']);

        $this->assertSame('Deliver to gate 4.', $this->job->fresh()->public_memo);
        $this->assertNull($log->fresh()->tolls, 'the whole save is refused, not just the memo');
    }

    public function test_a_manager_can_change_the_customer_facing_memo_from_the_log(): void
    {
        $log = $this->withMiles();

        Livewire::actingAs($this->manager)
            ->test(EditUserLog::class, ['log' => $log])
            ->assertSee('wire:model.blur="form.job_public_memo"', false)
            ->set('form.job_public_memo', 'Deliver to gate 7.')
            ->call('saveLog')
            ->assertHasNoErrors()
            // A second save with no further change writes nothing, so a
            // colleague's edit in between would survive it.
            ->set('form.tolls', '1.00')
            ->call('saveLog')
            ->assertHasNoErrors();

        $this->assertSame('Deliver to gate 7.', $this->job->fresh()->public_memo);
    }
}
