<?php

namespace Tests\Feature;

use App\Livewire\EditUserLog;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\PricingSetting;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * End-to-end coverage for deadhead as two explicit quantities (TASK-354).
 *
 * The doctrine these tests exist to protect: every deadhead mile a vehicle
 * drives is tracked, always; how much of it becomes money is a separate,
 * opt-in decision a human makes, bounded by the allowance the organization
 * publishes on its own price sheet. Measurement never bills by itself, and
 * billing can never reach a mile that was advertised as free.
 */
class DeadheadBillingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $manager;
    private PilotCarJob $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->manager = User::factory()->manager()->create(['organization_id' => $this->org->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $this->job = PilotCarJob::factory()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $customer->id,
            'rate_code' => 'lead_chase_per_mile',
        ]);
    }

    private function log(array $attributes = []): UserLog
    {
        return UserLog::create(array_merge([
            'job_id' => $this->job->id,
            'organization_id' => $this->org->id,
        ], $attributes));
    }

    private function editComponent(UserLog $log)
    {
        return Livewire::actingAs($this->manager)->test(EditUserLog::class, ['log' => $log]);
    }

    // ---------------------------------------------------------------
    // Measurement
    // ---------------------------------------------------------------

    /**
     * The drive to the pickup is already described by the odometer, so the
     * form offers it rather than making the driver work it out again.
     */
    public function test_driven_field_is_seeded_from_the_odometer_approach(): void
    {
        $log = $this->log([
            'start_mileage' => 1000,
            'start_job_mileage' => 1069,
            'end_job_mileage' => 1198,
            'end_mileage' => 1337,
        ]);

        $this->editComponent($log)->assertSet('form.dead_head_driven', 69.0);
    }

    /**
     * A stored value is a human's answer and outranks the derived one, even
     * when the odometer disagrees. Correcting the number must stick.
     */
    public function test_stored_driven_value_wins_over_the_derived_approach(): void
    {
        $log = $this->log([
            'start_mileage' => 1000,
            'start_job_mileage' => 1069,
            'end_job_mileage' => 1198,
            'end_mileage' => 1337,
            'dead_head_driven' => 85,
        ]);

        $this->editComponent($log)->assertSet('form.dead_head_driven', '85.0');
    }

    public function test_driven_and_billed_persist_to_the_log(): void
    {
        $log = $this->log();

        $this->editComponent($log)
            ->set('form.dead_head_driven', 279)
            ->set('form.dead_head_billed', 200)
            ->call('saveLog')
            ->assertHasNoErrors();

        $log->refresh();

        $this->assertSame(279.0, (float) $log->dead_head_driven);
        $this->assertSame(200.0, (float) $log->dead_head_billed);
    }

    // ---------------------------------------------------------------
    // The published allowance is a ceiling
    // ---------------------------------------------------------------

    /**
     * Billing past driven-minus-free would charge for miles the price sheet
     * promises are free, making the invoice contradict the quote.
     */
    public function test_billing_beyond_the_ceiling_is_rejected(): void
    {
        $log = $this->log();

        $this->editComponent($log)
            ->set('form.dead_head_driven', 100)
            ->set('form.dead_head_billed', 90) // ceiling is 25
            ->call('saveLog')
            ->assertHasErrors('form.dead_head_billed');

        $log->refresh();

        $this->assertNull($log->dead_head_billed);
    }

    public function test_billing_exactly_at_the_ceiling_is_allowed(): void
    {
        $log = $this->log();

        $this->editComponent($log)
            ->set('form.dead_head_driven', 279)
            ->set('form.dead_head_billed', 204)
            ->call('saveLog')
            ->assertHasNoErrors();

        $this->assertSame(204.0, (float) $log->refresh()->dead_head_billed);
    }

    /**
     * An approach inside the allowance is still logged in full; it just has
     * nothing billable in it. Tracking does not depend on chargeability.
     */
    public function test_short_approach_is_recorded_but_cannot_be_billed(): void
    {
        $log = $this->log();

        $this->editComponent($log)
            ->set('form.dead_head_driven', 69)
            ->set('form.dead_head_billed', 1)
            ->call('saveLog')
            ->assertHasErrors('form.dead_head_billed');

        $this->editComponent($log)
            ->set('form.dead_head_driven', 69)
            ->call('saveLog')
            ->assertHasNoErrors();

        $this->assertSame(69.0, (float) $log->refresh()->dead_head_driven);
    }

    /**
     * The ceiling reads the organization's own allowance, not the config
     * default, so an org that publishes a different number bills against it.
     */
    public function test_ceiling_follows_the_organizations_published_allowance(): void
    {
        PricingSetting::create([
            'organization_id' => $this->org->id,
            'setting_key' => 'charges.dead_head.free_miles',
            'setting_value' => '25',
            'setting_type' => 'float',
        ]);

        $log = $this->log(['dead_head_driven' => 100]);

        $this->assertSame(25.0, $log->deadHeadFreeMiles());
        $this->assertSame(75.0, $log->deadHeadBillingCeiling());
    }

    // ---------------------------------------------------------------
    // Billing is opt-in
    // ---------------------------------------------------------------

    /**
     * The whole reason billing defaults to nothing: 95% of historical logs
     * carry real approach miles, and auto-charging them would have invoiced
     * roughly $22,800 nobody ever agreed to.
     */
    public function test_driven_miles_alone_put_nothing_on_the_invoice(): void
    {
        $this->log([
            'start_job_mileage' => 0,
            'end_job_mileage' => 100,
            'dead_head_driven' => 279,
        ]);

        $values = $this->job->refresh()->invoiceValues()['values'];

        $this->assertSame(279.0, (float) $values['dead_head_driven']);
        $this->assertSame(0.0, (float) $values['dead_head_billed']);
        $this->assertSame(0.0, (float) $values['dead_head_charge']);
        $this->assertSame(0, $values['dead_head']); // no billed legs
        $this->assertSame(200.0, (float) $values['total']); // 100 mi escort only
    }

    public function test_billed_miles_reach_the_invoice_total(): void
    {
        $this->log([
            'start_job_mileage' => 0,
            'end_job_mileage' => 100,
            'dead_head_driven' => 279,
            'dead_head_billed' => 204,
        ]);

        $values = $this->job->refresh()->invoiceValues()['values'];

        $this->assertSame(204.0, (float) $values['dead_head_charge']);
        $this->assertSame(404.0, (float) $values['total']);
        $this->assertSame(1, $values['dead_head']);
    }

    /**
     * Two escorts, two allowances, one billed and one forgiven. This is how
     * the office answers "should the second car's deadhead bill?" without the
     * system having to take a position on it.
     */
    public function test_a_two_car_job_can_bill_one_escort_and_forgive_the_other(): void
    {
        $this->log([
            'start_job_mileage' => 0,
            'end_job_mileage' => 100,
            'dead_head_driven' => 200,
            'dead_head_billed' => 125,
        ]);
        $this->log([
            'start_job_mileage' => 0,
            'end_job_mileage' => 0,
            'dead_head_driven' => 200,
            'dead_head_billed' => 0,
        ]);

        $values = $this->job->refresh()->invoiceValues()['values'];

        $this->assertSame(400.0, (float) $values['dead_head_driven']); // both tracked
        $this->assertSame(125.0, (float) $values['dead_head_charge']); // one billed
        $this->assertSame(1, $values['dead_head']);
    }

    // ---------------------------------------------------------------
    // What the customer sees
    // ---------------------------------------------------------------

    /**
     * The concession is stated on the invoice rather than applied silently.
     * A line that just said "204 x $1.00" would hide the fact that 75 miles
     * were driven and given away.
     */
    public function test_invoice_line_states_what_was_driven_and_not_billed(): void
    {
        $this->log([
            'start_job_mileage' => 0,
            'end_job_mileage' => 100,
            'dead_head_driven' => 279,
            'dead_head_billed' => 200,
        ]);

        $values = $this->job->refresh()->invoiceValues()['values'];
        $line = collect(\App\Services\InvoiceLineItems::build($values))
            ->firstWhere('key', 'dead_head');

        $this->assertSame(200.0, (float) $line['quantity']);
        $this->assertSame(1.00, (float) $line['rate']);
        $this->assertStringContainsString('279', $line['description']);
        $this->assertStringContainsString('79', $line['description']);
    }

    // ---------------------------------------------------------------
    // The re-runnable backfill
    // ---------------------------------------------------------------

    /**
     * The reason this is a command and not a step inside the migration: a
     * migration runs once. Logs that arrive afterwards -- an import, a
     * restored dump, real history landing on a database that held test data
     * when the schema changed -- still need seeding, and this can be re-run
     * for them.
     */
    public function test_backfill_seeds_from_the_odometer_approach(): void
    {
        $log = $this->log([
            'start_mileage' => 1000,
            'start_job_mileage' => 1069,
            'end_job_mileage' => 1198,
            'end_mileage' => 1337,
        ]);

        $this->artisan('deadhead:backfill-driven --write')->assertSuccessful();

        $this->assertSame(69.0, (float) $log->refresh()->dead_head_driven);
    }

    public function test_backfill_dry_run_writes_nothing(): void
    {
        $log = $this->log([
            'start_mileage' => 1000,
            'start_job_mileage' => 1069,
            'end_job_mileage' => 1198,
            'end_mileage' => 1337,
        ]);

        $this->artisan('deadhead:backfill-driven')->assertSuccessful();

        $this->assertNull($log->refresh()->dead_head_driven);
    }

    /**
     * Idempotent: a figure a human entered or corrected outranks the odometer
     * and must survive any number of re-runs.
     */
    public function test_backfill_never_overwrites_a_recorded_value(): void
    {
        $log = $this->log([
            'start_mileage' => 1000,
            'start_job_mileage' => 1069,
            'end_job_mileage' => 1198,
            'end_mileage' => 1337,
            'dead_head_driven' => 85,
        ]);

        $this->artisan('deadhead:backfill-driven --write')->assertSuccessful();
        $this->artisan('deadhead:backfill-driven --write')->assertSuccessful();

        $this->assertSame(85.0, (float) $log->refresh()->dead_head_driven);
    }

    /**
     * Production holds a log implying a 190,065-mile drive to the pickup.
     * Seeding that would put a six-figure suggested charge in front of
     * someone, so an unbelievable reading stays blank.
     */
    public function test_backfill_skips_an_implausible_approach(): void
    {
        $log = $this->log([
            'start_mileage' => 1000,
            'start_job_mileage' => 195000,
            'end_job_mileage' => 195100,
            'end_mileage' => 195200,
        ]);

        $this->artisan('deadhead:backfill-driven --write')->assertSuccessful();

        $this->assertNull($log->refresh()->dead_head_driven);
        $this->assertNull($log->suggestedDeadHeadMiles());
    }

    public function test_backfill_skips_readings_that_are_out_of_order(): void
    {
        $log = $this->log([
            'start_mileage' => 1000,
            'start_job_mileage' => 900,
            'end_job_mileage' => 1198,
            'end_mileage' => 1337,
        ]);

        $this->artisan('deadhead:backfill-driven --write')->assertSuccessful();

        $this->assertNull($log->refresh()->dead_head_driven);
    }

    /**
     * The backfill is a ledger operation. Inferring a charge from mileage is
     * precisely what this whole change exists to stop.
     */
    public function test_backfill_never_touches_billed_miles(): void
    {
        $log = $this->log([
            'start_mileage' => 1000,
            'start_job_mileage' => 1200,
            'end_job_mileage' => 1300,
            'end_mileage' => 1400,
        ]);

        $this->artisan('deadhead:backfill-driven --write')->assertSuccessful();

        $log->refresh();

        $this->assertSame(200.0, (float) $log->dead_head_driven);
        $this->assertNull($log->dead_head_billed);
    }

    /**
     * Historical invoices stored `dead_head` as a count of flagged logs and
     * carried no mileage at all. Re-rendering one must not invent a charge --
     * and because zero-amount rows are dropped (TASK-367), the deadhead line
     * disappears entirely rather than printing "3 x $0.00" at a customer.
     */
    public function test_a_legacy_snapshot_renders_no_deadhead_line_at_all(): void
    {
        $lines = collect(\App\Services\InvoiceLineItems::build([
            'total' => 200.0,
            'dead_head' => 3,
        ]));

        $this->assertNull($lines->firstWhere('key', 'dead_head'));
        $this->assertSame(0.0, $lines->sum(fn ($line) => (float) $line['amount']) - 200.0);
    }
}
