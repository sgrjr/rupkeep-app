<?php

namespace Tests\Feature;

use App\Livewire\CreatePilotCarJob;
use App\Livewire\EditUserLog;
use App\Livewire\ShowPilotCarJob;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use App\Models\Vehicle;
use App\Services\InvoiceLineItems;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The go-live runbook, executed as a test.
 *
 * Every phase of the manual drive-through, driven through the real Livewire
 * components rather than by clicking -- so it exercises the same code paths the
 * UI does, repeats on demand, and fails loudly if any of it regresses.
 *
 * Deliberately one long test rather than several: the point is that the whole
 * lifecycle holds together end to end, and each phase depends on the state the
 * previous one left behind. A per-phase test with fresh fixtures would prove
 * something weaker.
 *
 * Two escorts throughout, because the interesting deadhead cases only appear
 * with more than one car.
 */
class LifecycleDriveThroughTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $manager;
    private User $lead;
    private User $chase;
    private Vehicle $leadCar;
    private Vehicle $chaseCar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->manager = User::factory()->manager()->create(['organization_id' => $this->org->id]);
        $this->lead = User::factory()->standard()->create(['organization_id' => $this->org->id, 'name' => 'Lead Driver']);
        $this->chase = User::factory()->standard()->create(['organization_id' => $this->org->id, 'name' => 'Chase Driver']);
        $this->leadCar = Vehicle::factory()->create(['organization_id' => $this->org->id, 'name' => 'Car 001']);
        $this->chaseCar = Vehicle::factory()->create(['organization_id' => $this->org->id, 'name' => 'Car 002']);
    }

    public function test_the_full_job_lifecycle_holds_together(): void
    {
        // ---------------------------------------------------------------
        // 01. The price sheet is the contract everything downstream honours
        // ---------------------------------------------------------------
        $this->assertSame(1.00, (float) config('pricing.charges.dead_head.rate_per_mile'));
        $this->assertSame(75, (int) config('pricing.charges.dead_head.free_miles'));
        $this->assertSame(2.00, (float) config('pricing.rates.lead_chase_per_mile.rate_per_mile'));

        // A new job must default to a price-list code, never a legacy one:
        // only the former is read back through the organisation's published
        // rate, so a legacy default silently ignores /my/pricing (TASK-415).
        $this->assertArrayHasKey(
            PilotCarJob::DEFAULT_RATE_CODE,
            config('pricing.rates'),
            'the default rate code must come from the price list'
        );
        $this->assertArrayNotHasKey(PilotCarJob::DEFAULT_RATE_CODE, config('pricing.legacy_rates'));

        // Raising the published rate must reach a job created with the default.
        \App\Models\PricingSetting::create([
            'organization_id' => $this->org->id,
            'setting_key' => 'rates.lead_chase_per_mile.rate_per_mile',
            'setting_value' => '2.25',
            'setting_type' => 'float',
        ]);

        $priced = (new PilotCarJob(['organization_id' => $this->org->id]))->calculateTotalDue([
            'organization_id' => $this->org->id, 'tolls' => 0, 'hotel' => 0, 'extra_charge' => 0,
            'extra_load_stops_count' => 0, 'wait_time_hours' => 0, 'dead_head_billed' => 0,
            'billable_miles' => 100, 'mini_addon_amount' => 0,
            'rate_code' => PilotCarJob::DEFAULT_RATE_CODE, 'rate_value' => null,
        ]);

        $this->assertSame(225.0, (float) $priced['total'], 'the published rate must reach the default code');

        \App\Models\PricingSetting::query()->delete();

        // ---------------------------------------------------------------
        // 02. Create the job, inventing the customer in the same submission
        // ---------------------------------------------------------------
        Livewire::actingAs($this->manager)->test(CreatePilotCarJob::class)
            ->set('form.customer_id', CreatePilotCarJob::NEW_CUSTOMER)
            ->set('form.new_customer_name', 'Drive Through Freight')
            ->set('form.job_no', 'DRIVE-001')
            ->set('form.load_no', 'LOAD-001')
            ->set('form.pickup_address', '928 Old Post Rd Arundel, ME')
            ->set('form.delivery_address', '1008 Congress St Portland, ME')
            ->set('form.rate_code', 'lead_chase_per_mile')
            ->call('createJob')
            ->assertHasNoErrors();

        $job = PilotCarJob::where('job_no', 'DRIVE-001')->firstOrFail();
        $customer = Customer::where('name', 'Drive Through Freight')->firstOrFail();

        $this->assertSame($customer->id, $job->customer_id, 'the new customer must be attached');

        // An empty submission must be refused rather than creating a partial job.
        Livewire::actingAs($this->manager)->test(CreatePilotCarJob::class)
            ->call('createJob')
            ->assertHasErrors();

        $this->assertSame(1, PilotCarJob::count(), 'a refused submission must not create a job');

        // ---------------------------------------------------------------
        // 03. Assign two escorts
        // ---------------------------------------------------------------
        $show = Livewire::actingAs($this->manager)->test(ShowPilotCarJob::class, ['job' => $job->id]);

        $show->set('assignment.car_driver_id', $this->lead->id)
            ->set('assignment.vehicle_id', $this->leadCar->id)
            ->set('assignment.vehicle_position', 'lead')
            ->call('assignJob');

        $show->set('assignment.car_driver_id', $this->chase->id)
            ->set('assignment.vehicle_id', $this->chaseCar->id)
            ->set('assignment.vehicle_position', 'chase')
            ->call('assignJob');

        $job->refresh();
        $this->assertCount(2, $job->logs, 'two escorts, two logs');

        $leadLog = $job->logs->firstWhere('car_driver_id', $this->lead->id);
        $chaseLog = $job->logs->firstWhere('car_driver_id', $this->chase->id);

        $this->assertNotNull($leadLog);
        $this->assertNotNull($chaseLog);
        $this->assertSame('pending', $leadLog->approval_status, 'a new log awaits the driver accepting it');

        // Which escort is which must be legible without opening anything.
        $this->assertSame('Lead', $leadLog->vehicle_position_label);
        $this->assertSame('Chase', $chaseLog->vehicle_position_label);

        // ---------------------------------------------------------------
        // 04. Lead car — the one that bills
        // ---------------------------------------------------------------
        Livewire::actingAs($this->lead)->test(EditUserLog::class, ['log' => $leadLog])->call('confirmLog');
        $leadLog->refresh();
        $this->assertSame('confirmed', $leadLog->approval_status);

        $lead = Livewire::actingAs($this->lead)->test(EditUserLog::class, ['log' => $leadLog])
            ->set('form.start_mileage', 10000)
            ->set('form.start_job_mileage', 10279)
            ->set('form.end_job_mileage', 10479)
            ->set('form.end_mileage', 10600);

        // The odometer describes the approach, so the panel offers it (TASK-398).
        $lead->assertSet('form.dead_head_driven', 279.0);

        // Editing anything surfaces the save control; an untouched form says so
        // instead of offering a button with nothing behind it (TASK-417).
        $lead->assertSet('formTouched', true)->assertSee('Save Changes');

        Livewire::actingAs($this->lead)->test(EditUserLog::class, ['log' => $leadLog])
            ->assertSet('formTouched', false)
            ->assertSee('All changes saved')
            ->assertDontSee('Save Changes');

        // Billable tracks the form before any save (TASK-397).
        $lead->assertSee('Billable: 200');

        // The published allowance is a ceiling, not a suggestion.
        $lead->set('form.dead_head_billed', 250)
            ->call('saveLog')
            ->assertHasErrors('form.dead_head_billed');

        // Completing persists the whole form in one action (TASK-399).
        $lead->set('form.dead_head_billed', 204)
            ->set('form.wait_time_hours', 2)
            ->set('form.extra_load_stops_count', 1)
            ->set('form.tolls', 18.50)
            ->call('markComplete')
            ->assertHasNoErrors();

        $leadLog->refresh();
        $this->assertNotNull($leadLog->completed_at, 'the log is handed to the office');
        $this->assertSame(279.0, (float) $leadLog->dead_head_driven);
        $this->assertSame(204.0, (float) $leadLog->dead_head_billed);
        $this->assertSame(2.0, (float) $leadLog->wait_time_hours);
        $this->assertSame(18.50, (float) $leadLog->tolls);
        $this->assertSame(
            '279 deadhead miles driven, 204 billed (first 75 free).',
            $leadLog->deadHeadSummary()
        );

        // ---------------------------------------------------------------
        // 05. Chase car — driven and forgiven
        // ---------------------------------------------------------------
        Livewire::actingAs($this->chase)->test(EditUserLog::class, ['log' => $chaseLog])->call('confirmLog');
        $chaseLog->refresh();

        Livewire::actingAs($this->chase)->test(EditUserLog::class, ['log' => $chaseLog])
            ->set('form.start_mileage', 44000)
            ->set('form.start_job_mileage', 44190)
            ->set('form.end_job_mileage', 44390)
            ->set('form.end_mileage', 44500)
            ->assertSet('form.dead_head_driven', 190.0)
            ->call('markComplete')
            ->assertHasNoErrors();

        $chaseLog->refresh();
        $this->assertSame(190.0, (float) $chaseLog->dead_head_driven);
        $this->assertNull($chaseLog->dead_head_billed, 'billing is opt-in; nothing was billed here');
        $this->assertSame(
            '190 deadhead miles driven, none billed (up to 115 could have been).',
            $chaseLog->deadHeadSummary()
        );

        // ---------------------------------------------------------------
        // 06. Review the job
        // ---------------------------------------------------------------
        $job->refresh();
        $values = $job->invoiceValues()['values'];

        $this->assertSame(469.0, (float) $values['dead_head_driven'], 'both escorts tracked');
        $this->assertSame(204.0, (float) $values['dead_head_billed'], 'one escort billed');
        $this->assertSame(204.0, (float) $values['dead_head_charge']);
        $this->assertSame(1, $values['dead_head'], 'one billed leg, not two');
        $this->assertSame(400.0, (float) $values['billable_miles'], '200 per escort');

        // 400 mi x $2.00 + 204 deadhead + 60 wait + 30 stop + 18.50 tolls
        $this->assertSame(1112.50, (float) $values['total']);

        // The mileage panel must never report a total below its own parts.
        $miles = $job->miles;
        $this->assertGreaterThanOrEqual($miles->billable, $miles->total);
        $this->assertSame(469.0, (float) $miles->deadhead_driven);
        $this->assertSame(204.0, (float) $miles->deadhead_billed);

        // Every segment is named, so the categories account for the whole trip
        // instead of one of them absorbing the remainder (TASK-418). The old
        // "Personal" figure was total minus billable, which swept the deadhead
        // in and then showed it a second time in its own tile.
        $this->assertEqualsWithDelta(
            $miles->total,
            $miles->billable + $miles->deadhead_driven + $miles->release,
            0.01,
            'escort + deadhead + release must account for every mile'
        );
        $this->assertSame(231.0, (float) $miles->release, '1100 total less 400 escort and 469 deadhead');

        // Cross-vehicle mileage keys are gone (TASK-404).
        $this->assertArrayNotHasKey('start_job_mileage', $values);
        $this->assertArrayNotHasKey('end_job_mileage', $values);
        $this->assertArrayNotHasKey('total_due', $values);

        // ---------------------------------------------------------------
        // 07. The invoice
        // ---------------------------------------------------------------
        $lines = collect(InvoiceLineItems::build($values));

        $deadhead = $lines->firstWhere('key', 'dead_head');
        $this->assertNotNull($deadhead);
        $this->assertSame(204.0, (float) $deadhead['quantity'], 'bill what was billed, not what was driven');
        $this->assertSame(1.00, (float) $deadhead['rate']);
        $this->assertStringContainsString('469', $deadhead['description']);
        $this->assertStringContainsString('265', $deadhead['description'], 'the concession is stated, not silent');

        $this->assertSame(800.0, (float) $lines->firstWhere('key', 'mileage')['amount']);
        $this->assertSame(60.0, (float) $lines->firstWhere('key', 'wait_time')['amount']);
        $this->assertSame(30.0, (float) $lines->firstWhere('key', 'extra_stops')['amount']);
        $this->assertSame(18.50, (float) $lines->firstWhere('key', 'tolls')['amount']);

        // Every dollar in the total appears on a line, and no zero rows survive.
        $this->assertEqualsWithDelta(1112.50, $lines->sum(fn ($l) => (float) $l['amount']), 0.01);
        $this->assertSame(0, $lines->filter(fn ($l) => (float) $l['amount'] == 0.0)->count());
        $this->assertNull($lines->firstWhere('key', 'pilot_car_service'), 'a $0 service line is dropped');

        // ---------------------------------------------------------------
        // 08. Reports reconcile
        // ---------------------------------------------------------------
        $this->actingAs($this->manager)->get(route('my.reports.annual-vehicle-report'))->assertOk();
        $this->actingAs($this->manager)->get(route('my.reports.index'))->assertOk();

        // ---------------------------------------------------------------
        // 09. Final sweep
        // ---------------------------------------------------------------
        $this->artisan('jobs:audit')
            ->expectsOutputToContain('All checks passed')
            ->assertSuccessful();

        // Everything the odometer could supply is already recorded.
        $this->artisan('deadhead:backfill-driven')->assertSuccessful();
        $this->assertSame(
            0,
            UserLog::whereNull('dead_head_driven')->whereNotNull('start_job_mileage')->count(),
            'no log with usable readings is left without its deadhead miles'
        );
    }
}
