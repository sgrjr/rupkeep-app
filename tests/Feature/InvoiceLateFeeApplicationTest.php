<?php

namespace Tests\Feature;

use App\Livewire\InvoicePaymentForm;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-443. "Apply to Invoice" wrote the late fee into values.total while also
 * saving it beside the total, so every later read billed it twice: $1,000 due
 * $1,100 became $1,200 on Total Due, Remaining Balance, the payment form's
 * paid-in-full test, the print view and the emailed PDF.
 *
 * These drive the real controller action, the real payment form, the real
 * summary refresh and the data migration that repairs existing rows.
 */
class InvoiceLateFeeApplicationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private Customer $customer;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->organization = Organization::factory()->create();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** A $1,000 invoice cut on 1 July 2026 with no job behind it. */
    private function invoice(float $total = 1000.0, array $values = []): Invoice
    {
        Carbon::setTestNow(Carbon::parse('2026-07-01 12:00:00'));

        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'values' => ['title' => 'INVOICE', 'total' => $total] + $values,
        ]);

        return $invoice->fresh();
    }

    /** A child invoice generated from a real job, so create-summary accepts it. */
    private function childInvoice(string $jobNo): Invoice
    {
        $job = PilotCarJob::create([
            'job_no' => $jobNo,
            'customer_id' => $this->customer->id,
            'organization_id' => $this->organization->id,
            'load_no' => 'LOAD-'.$jobNo,
            'pickup_address' => 'Gorham, ME',
            'delivery_address' => 'Boston, MA',
            'rate_code' => 'flat_rate',
            'rate_value' => '575.00',
            'scheduled_pickup_at' => '2026-06-18 09:00:00',
        ]);

        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);

        UserLog::create([
            'job_id' => $job->id,
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'approval_status' => 'confirmed',
            'start_mileage' => 0,
            'end_mileage' => 100,
            'start_job_mileage' => 0,
            'end_job_mileage' => 80,
        ]);

        return $job->fresh()->createInvoice();
    }

    private function apply(Invoice $invoice)
    {
        return $this->actingAs($this->admin)
            ->post(route('my.invoices.apply-late-fees', $invoice));
    }

    /** The reproduction from the task, end to end. */
    public function test_applying_a_late_fee_leaves_the_subtotal_alone_and_bills_the_fee_once(): void
    {
        $invoice = $this->invoice();

        Carbon::setTestNow(Carbon::parse('2026-09-04 12:00:00')); // 65 days: one period

        $this->assertSame(1100.0, $invoice->calculateLateFees()['total_with_late_fees'], 'before apply');

        $this->apply($invoice)
            ->assertRedirect(route('my.invoices.edit', $invoice))
            ->assertSessionHas('success');

        $invoice->refresh();
        $fees = $invoice->calculateLateFees();

        $this->assertEquals(1000.0, data_get($invoice->values, 'total'), 'subtotal must not absorb the fee');
        $this->assertEquals(100.0, data_get($invoice->values, 'late_fees.late_fee_amount'));
        $this->assertSame(1, (int) data_get($invoice->values, 'late_fees.late_fee_periods'));
        $this->assertSame(100.0, $fees['late_fee_amount']);
        $this->assertSame(1100.0, $fees['total_with_late_fees'], 'after apply: the same $1,100, not $1,200');
        $this->assertSame(1100.0, $invoice->remaining_balance);
    }

    public function test_applying_twice_in_the_same_period_is_refused_and_changes_nothing(): void
    {
        $invoice = $this->invoice();
        Carbon::setTestNow(Carbon::parse('2026-09-04 12:00:00'));

        $this->apply($invoice);
        $before = $invoice->fresh()->values;

        $this->apply($invoice)
            ->assertRedirect(route('my.invoices.edit', $invoice))
            ->assertSessionHas('info');

        $this->assertSame($before, $invoice->fresh()->values);
        $this->assertSame(1100.0, $invoice->fresh()->calculateLateFees()['total_with_late_fees']);
    }

    /**
     * Re-applying a month later used to reset original_total to the
     * fee-inclusive figure and compound. Now it adds exactly one more period on
     * the subtotal and keeps the earlier application on record.
     */
    public function test_a_later_period_applies_only_the_delta_and_keeps_history(): void
    {
        $invoice = $this->invoice();

        Carbon::setTestNow(Carbon::parse('2026-09-04 12:00:00'));
        $this->apply($invoice);

        Carbon::setTestNow(Carbon::parse('2026-10-04 12:00:00')); // second period
        $this->apply($invoice)->assertSessionHas('success');

        $invoice->refresh();
        $fees = $invoice->calculateLateFees();

        $this->assertEquals(1000.0, data_get($invoice->values, 'total'));
        $this->assertEquals(200.0, data_get($invoice->values, 'late_fees.late_fee_amount'));
        $this->assertSame(2, (int) data_get($invoice->values, 'late_fees.late_fee_periods'));
        $this->assertCount(1, data_get($invoice->values, 'late_fees.history'));
        $this->assertEquals(100.0, data_get($invoice->values, 'late_fees.history.0.late_fee_amount'));
        $this->assertSame(200.0, $fees['late_fee_amount']);
        $this->assertSame(1200.0, $fees['total_with_late_fees']);
        $this->assertSame(0.0, $fees['additional_late_fee_amount']);
    }

    /**
     * The payment form tested "paid in full" against the doubled figure, so a
     * customer who paid exactly what the invoice said still showed a balance.
     */
    public function test_paying_the_stated_total_due_settles_the_invoice_without_phantom_credit(): void
    {
        $invoice = $this->invoice();
        Carbon::setTestNow(Carbon::parse('2026-09-04 12:00:00'));
        $this->apply($invoice);
        $invoice->refresh();

        Livewire::actingAs($this->admin)
            ->test(InvoicePaymentForm::class, ['invoice' => $invoice])
            ->set('paymentAmount', '1100')
            ->set('paymentMethod', 'check')
            ->set('checkNumber', '1001')
            ->set('paymentDate', '2026-09-04')
            ->call('applyPayment')
            ->assertHasNoErrors();

        $invoice->refresh();

        $this->assertTrue($invoice->paid_in_full);
        $this->assertSame(0.0, $invoice->remaining_balance);
        $this->assertSame(1100.0, $invoice->calculateLateFees()['total_with_late_fees'], 'a paid invoice keeps the fee it was paid');
        $this->assertEquals(0.0, (float) $this->customer->fresh()->account_credit, 'no overpayment was invented');
    }

    /**
     * The fee on a summary lives outside the keys SummaryInvoiceValues::refresh
     * owns, so a child edit that regenerates the summary total must keep it.
     * Before, the fee sat inside `total`, which refresh overwrote, so it
     * vanished while `late_fees` still claimed it was applied.
     */
    public function test_a_summary_keeps_its_late_fee_when_a_child_changes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-01 12:00:00'));
        $a = $this->childInvoice('JOB-443-A');
        $b = $this->childInvoice('JOB-443-B');

        $this->actingAs($this->admin)
            ->post(route('my.invoices.create-summary'), ['invoice_ids' => [$a->id, $b->id]]);

        $summary = Invoice::where('invoice_type', 'summary')->latest('id')->firstOrFail();
        $summaryTotal = (float) data_get($summary->values, 'total');
        $this->assertGreaterThan(0, $summaryTotal);

        Carbon::setTestNow(Carbon::parse('2026-09-04 12:00:00'));
        $this->apply($summary)->assertSessionHas('success');

        $summary->refresh();
        $fee = (float) data_get($summary->values, 'late_fees.late_fee_amount');
        $this->assertEqualsWithDelta($summaryTotal * 0.10, $fee, 0.01);
        $this->assertEqualsWithDelta($summaryTotal, (float) data_get($summary->values, 'total'), 0.005);

        // Edit a child through the real form; the observer regenerates the summary.
        $newChildTotal = (float) data_get($a->fresh()->values, 'total') + 250.0;
        $this->actingAs($this->admin)
            ->put(route('my.invoices.update', $a), [
                'paid_in_full' => 'no',
                'values' => ['total' => (string) $newChildTotal],
            ])
            ->assertRedirect(route('my.invoices.edit', $a));

        $summary->refresh();
        $fees = $summary->calculateLateFees();

        $this->assertEqualsWithDelta($summaryTotal + 250.0, (float) data_get($summary->values, 'total'), 0.005, 'summary followed its child');
        $this->assertEquals($fee, (float) data_get($summary->values, 'late_fees.late_fee_amount'), 'the applied fee survived the refresh');
        $this->assertTrue($fees['late_fees_applied']);
        $this->assertEqualsWithDelta($summaryTotal + 250.0 + $fee, $fees['total_with_late_fees'], 0.01);
    }

    public function test_a_child_of_a_summary_cannot_take_its_own_late_fee(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-01 12:00:00'));
        $a = $this->childInvoice('JOB-443-C');
        $b = $this->childInvoice('JOB-443-D');

        $this->actingAs($this->admin)
            ->post(route('my.invoices.create-summary'), ['invoice_ids' => [$a->id, $b->id]]);

        Carbon::setTestNow(Carbon::parse('2026-09-04 12:00:00'));
        $a->refresh();
        $this->assertNotNull($a->parent_invoice_id);

        $this->apply($a)->assertSessionHas('error');

        $a->refresh();
        $this->assertNull(data_get($a->values, 'late_fees'));
        $this->assertSame(0.0, $a->calculateLateFees()['late_fee_amount']);
        $this->assertEquals((float) data_get($a->values, 'total'), $a->calculateLateFees()['total_with_late_fees']);
    }

    /**
     * Rows written by the old code carry total = original_total + fee. The
     * migration takes the fee back out where that identity holds exactly, and
     * leaves a total the admin has since edited by hand alone.
     */
    public function test_the_migration_unfolds_fees_that_were_written_into_totals(): void
    {
        // The old apply wrote total = 1000 + 100 and saved the doubled
        // 1200 it then displayed.
        $folded = $this->invoice(1100.0, ['late_fees' => [
            'applied_at' => '2026-09-04 12:00:00',
            'applied_by' => $this->admin->id,
            'late_fee_periods' => 1,
            'late_fee_amount' => 100.0,
            'original_total' => 1000.0,
            'total_with_late_fees' => 1200.0,
        ]]);

        $editedSince = $this->invoice(1500.0, ['late_fees' => [
            'applied_at' => '2026-09-04 12:00:00',
            'applied_by' => $this->admin->id,
            'late_fee_periods' => 1,
            'late_fee_amount' => 100.0,
            'original_total' => 1000.0,
        ]]);

        $untouched = $this->invoice(800.0);

        $migration = require database_path('migrations/2026_09_30_000002_unfold_late_fees_from_invoice_totals.php');
        $migration->up();
        $migration->up(); // idempotent

        $this->assertEquals(1000.0, data_get($folded->fresh()->values, 'total'));
        $this->assertNull(data_get($folded->fresh()->values, 'late_fees.total_with_late_fees'));
        $this->assertEquals(100.0, data_get($folded->fresh()->values, 'late_fees.late_fee_amount'));

        $this->assertEquals(1500.0, data_get($editedSince->fresh()->values, 'total'));
        $this->assertEquals(800.0, data_get($untouched->fresh()->values, 'total'));

        Carbon::setTestNow(Carbon::parse('2026-09-04 12:00:00'));
        $this->assertSame(1100.0, $folded->fresh()->calculateLateFees()['total_with_late_fees']);
    }
}
