<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-365: the invoice total already included wait time / extra stops /
 * mileage, but the itemized lines rendered $0.00 because
 * PilotCarJob::invoiceValues() never persisted the per-charge dollar amounts
 * the invoice template reads (cost_of_wait_time, cost_of_extra_stop,
 * cost_for_mileage). The money silently rolled into "Pilot Car Service".
 */
class InvoiceItemizationTest extends TestCase
{
    use RefreshDatabase;

    private function jobWithExtras(array $logAttributes = [], array $jobAttributes = []): PilotCarJob
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        $job = PilotCarJob::create(array_merge([
            'job_no' => 'JOB-ITEMIZE',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'pickup_address' => '123 Demo St',
            'delivery_address' => '456 Demo Ave',
            'rate_code' => 'lead_chase_per_mile',
            'rate_value' => '2.00',
        ], $jobAttributes));

        UserLog::create(array_merge([
            'job_id' => $job->id,
            'organization_id' => $organization->id,
            'start_mileage' => 0,
            'end_mileage' => 120,
            'start_job_mileage' => 0,
            'end_job_mileage' => 100,
            'extra_load_stops_count' => 1,
            'wait_time_hours' => 3,
        ], $logAttributes));

        return $job->fresh();
    }

    public function test_invoice_values_itemize_extra_stops(): void
    {
        $values = $this->jobWithExtras()->invoiceValues()['values'];

        $this->assertSame(1.0, (float) $values['extra_load_stops_count']);
        $this->assertSame(30.0, (float) $values['cost_of_extra_stop'], 'per-stop rate must be persisted');
    }

    public function test_invoice_values_itemize_wait_time(): void
    {
        $values = $this->jobWithExtras()->invoiceValues()['values'];

        // 3 hrs, first hour free at the configured minimum => 2 × $30.00
        $this->assertSame(60.0, (float) $values['cost_of_wait_time']);
    }

    public function test_invoice_values_itemize_mileage(): void
    {
        $values = $this->jobWithExtras()->invoiceValues()['values'];

        $this->assertSame(200.0, (float) $values['cost_for_mileage'], '100 billable mi × $2.00');
    }

    public function test_itemized_lines_sum_to_the_invoice_total(): void
    {
        $values = $this->jobWithExtras()->invoiceValues()['values'];

        $sum = (float) $values['cost_for_mileage']
            + (float) $values['cost_of_wait_time']
            + ((float) $values['extra_load_stops_count'] * (float) $values['cost_of_extra_stop'])
            + (float) $values['tolls']
            + (float) $values['hotel']
            + (float) $values['extra_charge'];

        $this->assertEqualsWithDelta((float) $values['total'], $sum, 0.01);
    }

    public function test_printed_invoice_shows_the_extra_stop_amount(): void
    {
        $job = $this->jobWithExtras();
        $invoice = $job->createInvoice();

        $manager = User::factory()->manager()->create([
            'organization_id' => $job->organization_id,
        ]);

        $response = $this->actingAs($manager)->get(route('my.invoices.print', $invoice));

        $response->assertOk();
        $response->assertSee('Extra Stops');
        // The $30 stop must appear as its own line amount, not be swallowed
        // by the Pilot Car Service line.
        $response->assertSee('$30.00');
        $response->assertSee('$60.00'); // wait time
    }

    public function test_backfill_itemizes_an_invoice_whose_total_still_reconciles(): void
    {
        $job = $this->jobWithExtras();
        $invoice = $job->createInvoice();

        // Simulate a snapshot written before the fix: correct total, no itemization.
        $values = $invoice->values;
        unset($values['cost_of_wait_time'], $values['cost_of_extra_stop'], $values['cost_for_mileage']);
        $invoice->values = $values;
        $invoice->save();

        $this->artisan('invoices:backfill-itemization --write')->assertSuccessful();

        $values = $invoice->fresh()->values;
        $this->assertSame(60.0, (float) $values['cost_of_wait_time']);
        $this->assertSame(30.0, (float) $values['cost_of_extra_stop']);
        $this->assertSame(200.0, (float) $values['cost_for_mileage']);
    }

    public function test_backfill_refuses_to_restate_an_invoice_whose_total_has_drifted(): void
    {
        $job = $this->jobWithExtras();
        $invoice = $job->createInvoice();

        // A legacy CSV-imported invoice: the stored total is the spreadsheet's
        // figure and no longer matches what the job math produces.
        $values = $invoice->values;
        unset($values['cost_of_wait_time'], $values['cost_of_extra_stop'], $values['cost_for_mileage']);
        $values['total'] = 999.00;
        $invoice->values = $values;
        $invoice->save();

        $this->artisan('invoices:backfill-itemization --write')->assertSuccessful();

        $values = $invoice->fresh()->values;
        $this->assertSame(999.00, (float) $values['total'], 'an issued total must never be silently restated');
        $this->assertArrayNotHasKey('cost_of_wait_time', $values);
    }

    public function test_backfill_dry_run_writes_nothing(): void
    {
        $job = $this->jobWithExtras();
        $invoice = $job->createInvoice();

        $values = $invoice->values;
        unset($values['cost_of_wait_time'], $values['cost_of_extra_stop'], $values['cost_for_mileage']);
        $invoice->values = $values;
        $invoice->save();

        $this->artisan('invoices:backfill-itemization')->assertSuccessful();

        $this->assertArrayNotHasKey('cost_of_wait_time', $invoice->fresh()->values);
    }

    public function test_wait_time_honors_configured_minimum_hours(): void
    {
        config()->set('pricing.charges.wait_time.minimum_hours', 0);

        $values = $this->jobWithExtras()->invoiceValues()['values'];

        // With no free hour, all 3 hours bill: 3 × $30.00
        $this->assertSame(90.0, (float) $values['cost_of_wait_time']);
    }
}
