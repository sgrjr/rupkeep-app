<?php

namespace Tests\Feature;

use App\Livewire\CreatePilotCarJob;
use App\Livewire\EditPilotCarJob;
use App\Livewire\ShowPilotCarJob;
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
use PHPUnit\Framework\Attributes\DataProvider;
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

    /* -------------------- Cancellation flat-rates flow into invoicing (TASK-314) -------------------- */

    public static function cancellationRateProvider(): array
    {
        // rate_code => expected flat total (from config/pricing.php)
        return [
            'show but no-go'         => ['show_no_go', 225.00],
            'within 24 hours'        => ['cancellation_24hr', 150.00],
            'cancel without billing' => ['cancel_without_billing', 0.00],
        ];
    }

    #[DataProvider('cancellationRateProvider')]
    public function test_cancellation_flat_rate_flows_into_invoice_total(string $rateCode, float $expectedTotal): void
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        $flatAmount = (string) config("pricing.rates.{$rateCode}.flat_amount");

        // Mirror what CancelJob::cancel() writes to the job on cancellation.
        $job = PilotCarJob::create([
            'job_no' => 'JOB-CANCEL',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'load_no' => 'LOAD-CANCEL',
            'pickup_address' => 'Pickup',
            'delivery_address' => 'Delivery',
            'rate_code' => $rateCode,
            'rate_value' => $flatAmount,
            'canceled_at' => now(),
            'canceled_reason' => 'Customer canceled',
        ]);

        $values = $job->invoiceValues()['values'];

        $this->assertSame($rateCode, $values['effective_rate_code'],
            'The cancellation rate code must be the one that drives the invoice total.');
        $this->assertEqualsWithDelta($expectedTotal, (float) $values['total'], 0.001,
            "Invoice total for {$rateCode} must equal the configured flat amount (no billable miles).");
    }

    /* -------------------- Additive "mini" add-on stacks on top of rate (TASK-307) -------------------- */

    public function test_mini_addon_stacks_on_top_of_flat_rate_job(): void
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        $flatAmount = (float) config('pricing.rates.day_rate.flat_amount'); // 575.00

        $job = PilotCarJob::create([
            'job_no' => 'JOB-MINI-STACK',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'load_no' => 'LOAD-MINI-STACK',
            'pickup_address' => 'Pickup',
            'delivery_address' => 'Delivery',
            'rate_code' => 'day_rate',
            'rate_value' => (string) $flatAmount,
            'mini_addon_amount' => '100.00',
        ]);

        $values = $job->invoiceValues()['values'];

        // No tolls/hotel/extra set on this job, so expenses are 0 here, but assert the
        // formula generically: total must equal flat total + mini add-on + expenses.
        $expectedExpenses = (float) $values['tolls'] + (float) $values['hotel'] + (float) $values['extra_charge'];
        $expectedTotal = $flatAmount + 100.00 + $expectedExpenses;

        $this->assertSame('day_rate', $values['effective_rate_code'],
            'The mini add-on must not replace the existing rate_code.');
        $this->assertEqualsWithDelta(100.00, (float) $values['mini_addon_amount'], 0.001,
            'The mini add-on amount must be surfaced as its own itemized component.');
        $this->assertEqualsWithDelta($expectedTotal, (float) $values['total'], 0.001,
            'Total must equal flat total + mini add-on + expenses (additive stacking).');
    }

    public function test_mini_addon_stacks_on_top_of_flat_rate_job_with_expenses(): void
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        $flatAmount = (float) config('pricing.rates.day_rate.flat_amount'); // 575.00

        $job = PilotCarJob::create([
            'job_no' => 'JOB-MINI-STACK-EXP',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'load_no' => 'LOAD-MINI-STACK-EXP',
            'pickup_address' => 'Pickup',
            'delivery_address' => 'Delivery',
            'rate_code' => 'day_rate',
            'rate_value' => (string) $flatAmount,
            'mini_addon_amount' => '75.50',
        ]);

        UserLog::create([
            'job_id' => $job->id,
            'organization_id' => $organization->id,
            'tolls' => 20.00,
            'started_at' => Carbon::now()->subDay(),
            'ended_at' => Carbon::now(),
        ]);

        $values = $job->invoiceValues()['values'];

        $expectedTotal = $flatAmount + 75.50 + 20.00;

        $this->assertEqualsWithDelta($expectedTotal, (float) $values['total'], 0.001,
            'Mini add-on and tolls must both stack on top of the flat rate total.');
    }

    public function test_mini_addon_is_unset_by_default_and_does_not_change_total(): void
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        $job = PilotCarJob::create([
            'job_no' => 'JOB-NO-MINI',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'load_no' => 'LOAD-NO-MINI',
            'pickup_address' => 'Pickup',
            'delivery_address' => 'Delivery',
            'rate_code' => 'per_mile_rate_2_00',
            'rate_value' => '2.00',
        ]);

        $values = $job->invoiceValues()['values'];

        $this->assertNull($job->mini_addon_amount);
        $this->assertEqualsWithDelta(0.00, (float) $values['mini_addon_amount'], 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $values['total'], 0.001,
            'A job with no billable miles/logs and no mini add-on should have a $0 total (regression).');
    }

    public function test_mini_addon_can_be_saved_via_edit_pilot_car_job(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        $job = PilotCarJob::create([
            'job_no' => 'JOB-MINI-EDIT',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'load_no' => 'LOAD-MINI-EDIT',
            'pickup_address' => 'Pickup',
            'delivery_address' => 'Delivery',
            'rate_code' => 'day_rate',
            'rate_value' => '575.00',
        ]);

        Livewire::actingAs($manager)->test(EditPilotCarJob::class, ['job' => $job->id])
            ->set('form.mini_addon_amount', '50')
            ->call('saveJob')
            ->assertHasNoErrors();

        $job->refresh();

        $this->assertSame('day_rate', $job->rate_code,
            'Saving the mini add-on must not overwrite the existing rate_code.');
        $this->assertEqualsWithDelta(50.00, (float) $job->mini_addon_amount, 0.001);
    }

    public function test_mini_addon_can_be_set_via_create_pilot_car_job(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        Livewire::actingAs($manager)->test(CreatePilotCarJob::class)
            ->set('form.customer_id', $customer->id)
            ->set('form.job_no', 'JOB-MINI-CREATE')
            ->set('form.load_no', 'LOAD-MINI-CREATE')
            ->set('form.pickup_address', '123 Pickup St')
            ->set('form.delivery_address', '456 Delivery Ave')
            ->set('form.rate_code', 'day_rate')
            ->set('form.rate_value', '575.00')
            ->set('form.mini_addon_amount', '40')
            ->call('createJob');

        $job = PilotCarJob::where('job_no', 'JOB-MINI-CREATE')->firstOrFail();

        $this->assertSame('day_rate', $job->rate_code);
        $this->assertEqualsWithDelta(40.00, (float) $job->mini_addon_amount, 0.001);
    }

    public function test_determine_cancellation_type_matches_timing_and_logs(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 12:00:00'));

        $organization = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        $base = [
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'load_no' => 'L', 'pickup_address' => 'P', 'delivery_address' => 'D',
        ];

        // No pickup time at all -> no billing.
        $noPickup = PilotCarJob::create($base + ['job_no' => 'C-NONE']);
        $this->assertSame('cancel_without_billing', $noPickup->determineCancellationType());

        // Pickup 12h out (inside the 24h window) -> 24hr charge.
        $soon = PilotCarJob::create($base + ['job_no' => 'C-24', 'scheduled_pickup_at' => now()->addHours(12)]);
        $this->assertSame('cancellation_24hr', $soon->determineCancellationType());

        // Pickup well outside the window, no logs -> no billing.
        $farNoLogs = PilotCarJob::create($base + ['job_no' => 'C-FAR', 'scheduled_pickup_at' => now()->addDays(5)]);
        $this->assertSame('cancel_without_billing', $farNoLogs->determineCancellationType());

        Carbon::setTestNow();
    }

    /* -------------------- Flat-rate vs. mini billing comparison (TASK-308) -------------------- */

    /**
     * Build a job with a single log carrying an explicit billable-miles override
     * (and optional expense fields) so the comparison inputs are deterministic.
     */
    private function jobWithBillableMiles(
        Organization $organization,
        Customer $customer,
        string $rateCode,
        ?string $rateValue,
        ?float $billableMiles,
        array $logExtra = [],
        ?string $miniAddon = null,
    ): PilotCarJob {
        static $seq = 0;
        $seq++;

        $job = PilotCarJob::create([
            'job_no' => 'JOB-CMP-' . $seq,
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'load_no' => 'LOAD-CMP-' . $seq,
            'pickup_address' => 'Pickup',
            'delivery_address' => 'Delivery',
            'rate_code' => $rateCode,
            'rate_value' => $rateValue,
            'mini_addon_amount' => $miniAddon,
        ]);

        if ($billableMiles !== null) {
            UserLog::create([
                'job_id' => $job->id,
                'organization_id' => $organization->id,
                'billable_miles' => $billableMiles,
                'started_at' => Carbon::now()->subDay(),
                'ended_at' => Carbon::now(),
            ] + $logExtra);
        }

        return $job->fresh();
    }

    public function test_rate_comparison_flags_mini_as_greater_when_per_mile_total_is_lower(): void
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        // 100 billable miles @ $2.00 = $200 per-mile, below the $350 mini flat rate.
        $job = $this->jobWithBillableMiles($organization, $customer, 'per_mile_rate_2_00', '2.00', 100);

        $comparison = $job->getRateComparison();

        $this->assertNotNull($comparison);
        $this->assertEqualsWithDelta(200.00, $comparison['current_cost'], 0.001);
        $this->assertEqualsWithDelta(350.00, $comparison['mini_cost'], 0.001);
        $this->assertTrue($comparison['is_mini_better'], 'Mini flat rate bills more here, so it is the amount to charge.');
        $this->assertEqualsWithDelta(150.00, $comparison['savings'], 0.001);
        $this->assertEqualsWithDelta(100.0, $comparison['billable_miles'], 0.001);
    }

    public function test_rate_comparison_keeps_current_rate_when_per_mile_total_is_higher(): void
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        // 120 billable miles @ $3.50 = $420 per-mile, above the $350 mini flat rate.
        $job = $this->jobWithBillableMiles($organization, $customer, 'per_mile_rate_3_50', '3.50', 120);

        $comparison = $job->getRateComparison();

        $this->assertNotNull($comparison);
        $this->assertEqualsWithDelta(420.00, $comparison['current_cost'], 0.001);
        $this->assertEqualsWithDelta(350.00, $comparison['mini_cost'], 0.001);
        $this->assertFalse($comparison['is_mini_better'], 'The current per-mile rate bills more, so it stays.');
        $this->assertEqualsWithDelta(70.00, $comparison['savings'], 0.001);
    }

    public function test_rate_comparison_includes_expenses_on_both_sides(): void
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        // 100 mi @ $2.00 = $200 rate charge, plus $20 tolls + $30 extra = $50 expenses.
        $job = $this->jobWithBillableMiles(
            $organization,
            $customer,
            'per_mile_rate_2_00',
            '2.00',
            100,
            ['tolls' => 20.00, 'extra_charge' => 30.00],
        );

        $comparison = $job->getRateComparison();

        $this->assertNotNull($comparison);
        // Regression guard: the old comparison read non-existent value keys and
        // silently dropped extra_charge/load_stops/wait_time from the expense sum.
        $this->assertEqualsWithDelta(50.00, $comparison['expenses'], 0.001);
        $this->assertEqualsWithDelta(250.00, $comparison['current_cost'], 0.001, 'rate charge + expenses');
        $this->assertEqualsWithDelta(400.00, $comparison['mini_cost'], 0.001, 'mini flat + same expenses');
    }

    public function test_rate_comparison_is_null_when_billable_miles_exceed_mini_threshold(): void
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        // 200 billable miles is beyond the 125-mile mini threshold: no flat-vs-mini choice.
        $job = $this->jobWithBillableMiles($organization, $customer, 'per_mile_rate_2_00', '2.00', 200);

        $this->assertNull($job->getRateComparison());
    }

    public function test_rate_comparison_is_null_when_no_billable_miles(): void
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        // No logs at all -> nothing to bill per-mile against -> not a meaningful comparison.
        $job = $this->jobWithBillableMiles($organization, $customer, 'per_mile_rate_2_00', '2.00', null);

        $this->assertNull($job->getRateComparison());
    }

    /* -------------------- Mini add-on double-count guard (TASK-335) -------------------- */

    public function test_mini_addon_is_ignored_on_mini_flat_rate_job_but_still_stacks_elsewhere(): void
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        $miniFlat = (float) config('pricing.rates.mini_flat_rate.flat_amount'); // 350.00
        $dayFlat = (float) config('pricing.rates.day_rate.flat_amount');        // 575.00

        // A mini_flat_rate job that ALSO carries a mini add-on must NOT charge the
        // mini twice: the add-on is dropped, total equals the mini flat amount.
        $miniJob = PilotCarJob::create([
            'job_no' => 'JOB-GUARD-MINI',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'load_no' => 'LOAD-GUARD-MINI',
            'pickup_address' => 'Pickup',
            'delivery_address' => 'Delivery',
            'rate_code' => 'mini_flat_rate',
            'rate_value' => (string) $miniFlat,
            'mini_addon_amount' => '100.00',
        ]);

        $miniValues = $miniJob->invoiceValues()['values'];
        $this->assertEqualsWithDelta(0.00, (float) $miniValues['mini_addon_amount'], 0.001,
            'The add-on component must be zeroed out on a mini_flat_rate job.');
        $this->assertEqualsWithDelta($miniFlat, (float) $miniValues['total'], 0.001,
            'Total must equal the single mini flat amount, not mini + mini add-on.');

        // The additive design stays intact for every other rate code.
        $dayJob = PilotCarJob::create([
            'job_no' => 'JOB-GUARD-DAY',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'load_no' => 'LOAD-GUARD-DAY',
            'pickup_address' => 'Pickup',
            'delivery_address' => 'Delivery',
            'rate_code' => 'day_rate',
            'rate_value' => (string) $dayFlat,
            'mini_addon_amount' => '100.00',
        ]);

        $dayValues = $dayJob->invoiceValues()['values'];
        $this->assertEqualsWithDelta(100.00, (float) $dayValues['mini_addon_amount'], 0.001,
            'The add-on still stacks on non-mini rate codes.');
        $this->assertEqualsWithDelta($dayFlat + 100.00, (float) $dayValues['total'], 0.001);
    }

    public function test_edit_rejects_mini_addon_on_mini_flat_rate_job(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        $job = PilotCarJob::create([
            'job_no' => 'JOB-GUARD-EDIT',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'load_no' => 'LOAD-GUARD-EDIT',
            'pickup_address' => 'Pickup',
            'delivery_address' => 'Delivery',
            'rate_code' => 'day_rate',
            'rate_value' => '575.00',
        ]);

        Livewire::actingAs($manager)->test(EditPilotCarJob::class, ['job' => $job->id])
            ->set('form.rate_code', 'mini_flat_rate')
            ->set('form.mini_addon_amount', '100')
            ->call('saveJob')
            ->assertHasErrors('form.mini_addon_amount');

        $job->refresh();

        $this->assertSame('day_rate', $job->rate_code, 'The rejected save must not persist.');
        $this->assertNull($job->mini_addon_amount);
    }

    public function test_edit_allows_mini_addon_on_non_mini_rate(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        $job = PilotCarJob::create([
            'job_no' => 'JOB-GUARD-EDIT-OK',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'load_no' => 'LOAD-GUARD-EDIT-OK',
            'pickup_address' => 'Pickup',
            'delivery_address' => 'Delivery',
            'rate_code' => 'per_mile_rate_2_00',
            'rate_value' => '2.00',
        ]);

        Livewire::actingAs($manager)->test(EditPilotCarJob::class, ['job' => $job->id])
            ->set('form.mini_addon_amount', '60')
            ->call('saveJob')
            ->assertHasNoErrors();

        $job->refresh();

        $this->assertEqualsWithDelta(60.00, (float) $job->mini_addon_amount, 0.001);
    }

    public function test_create_rejects_mini_addon_on_mini_flat_rate_job(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        Livewire::actingAs($manager)->test(CreatePilotCarJob::class)
            ->set('form.customer_id', $customer->id)
            ->set('form.job_no', 'JOB-GUARD-CREATE')
            ->set('form.load_no', 'LOAD-GUARD-CREATE')
            ->set('form.pickup_address', '123 Pickup St')
            ->set('form.delivery_address', '456 Delivery Ave')
            ->set('form.rate_code', 'mini_flat_rate')
            ->set('form.rate_value', '350.00')
            ->set('form.mini_addon_amount', '100')
            ->call('createJob')
            ->assertHasErrors('form.mini_addon_amount');

        $this->assertSame(0, PilotCarJob::where('job_no', 'JOB-GUARD-CREATE')->count(),
            'A job that violates the double-count guard must not be created.');
    }

    public function test_job_show_page_renders_rate_comparison_callout(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        // 100 mi @ $2.00 => $200 current vs $350 mini: a mini-better callout.
        $job = $this->jobWithBillableMiles($organization, $customer, 'per_mile_rate_2_00', '2.00', 100);

        // Rendering the full show component exercises the Blade panel + Money::currency().
        Livewire::actingAs($manager)->test(ShowPilotCarJob::class, ['job' => $job->id])
            ->assertOk()
            ->assertSee('Rate Comparison')
            ->assertSee(\App\Support\Money::currency(350.00));
    }
}


