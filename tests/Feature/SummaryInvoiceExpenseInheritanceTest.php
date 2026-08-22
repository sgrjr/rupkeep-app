<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-379 — a summary invoice must not carry one arbitrary child's figures.
 *
 * buildSummaryValues() seeded itself from the FIRST child invoice's whole
 * values array, so a summary inherited that child's hotel / tolls /
 * extra_charge / rate_code as dead keys. The summary document never printed
 * them, but the QuickBooks export reads `values` directly and wrote them out as
 * though they described the whole summary.
 */
class SummaryInvoiceExpenseInheritanceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private Customer $customer;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function childInvoice(string $jobNo, array $logAttributes = []): Invoice
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
            'scheduled_pickup_at' => '2025-12-18 09:00:00',
        ]);

        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);

        UserLog::create(array_merge([
            'job_id' => $job->id,
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'approval_status' => 'confirmed',
            'start_mileage' => 0,
            'end_mileage' => 100,
            'start_job_mileage' => 0,
            'end_job_mileage' => 80,
        ], $logAttributes));

        return $job->fresh()->createInvoice();
    }

    private function summaryOf(array $children): Invoice
    {
        $this->actingAs($this->admin)
            ->post(route('my.invoices.create-summary'), [
                'invoice_ids' => collect($children)->pluck('id')->all(),
            ]);

        return Invoice::where('invoice_type', 'summary')->latest('id')->firstOrFail();
    }

    public function test_a_summary_does_not_inherit_the_first_childs_expenses(): void
    {
        // Only the first child has expenses. Before the fix the summary claimed
        // them as its own.
        $withExpenses = $this->childInvoice('JOB-A', ['hotel' => 125, 'tolls' => 10, 'extra_charge' => 340]);
        $without = $this->childInvoice('JOB-B');

        $this->assertSame(125.0, (float) $withExpenses->values['hotel'], 'the child should still carry them');

        $values = $this->summaryOf([$withExpenses, $without])->values;

        foreach (['hotel', 'tolls', 'gas', 'extra_charge', 'extra_charges', 'wait_time_hours', 'dead_head_charge'] as $key) {
            $this->assertArrayNotHasKey($key, $values, "a summary must not carry `{$key}`");
        }
    }

    public function test_a_summary_does_not_inherit_the_first_childs_job_identity(): void
    {
        $a = $this->childInvoice('JOB-A');
        $b = $this->childInvoice('JOB-B');

        $values = $this->summaryOf([$a, $b])->values;

        foreach (['job_no', 'load_no', 'pickup_address', 'delivery_address', 'rate_code', 'rate_value'] as $key) {
            $this->assertArrayNotHasKey($key, $values, "a summary must not carry `{$key}`");
        }

        // Those details ride per row instead, where they belong.
        $this->assertSame(
            ['JOB-A', 'JOB-B'],
            collect($values['summary_items'])->pluck('job_no')->sort()->values()->all()
        );
    }

    public function test_a_summary_keeps_its_own_totals_and_its_billing_addresses(): void
    {
        $a = $this->childInvoice('JOB-A', ['hotel' => 125]);
        $b = $this->childInvoice('JOB-B');

        $values = $this->summaryOf([$a, $b])->values;

        $this->assertSame('SUMMARY INVOICE', $values['title']);
        $this->assertEqualsWithDelta(
            (float) $a->values['total'] + (float) $b->values['total'],
            (float) $values['total'],
            0.01,
            'the child expenses are already inside the totals this sums'
        );
        $this->assertArrayHasKey('bill_to', $values);
        $this->assertArrayHasKey('bill_from', $values);
        $this->assertCount(2, $values['summary_items']);
    }

    public function test_the_quickbooks_export_no_longer_bills_one_childs_hotel_against_the_summary(): void
    {
        $a = $this->childInvoice('JOB-A', ['hotel' => 125, 'tolls' => 10]);
        $b = $this->childInvoice('JOB-B');
        $summary = $this->summaryOf([$a, $b]);

        $csv = $this->actingAs($this->admin)
            ->get(route('my.invoices.export.quickbooks'))
            ->streamedContent();

        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));
        $header = array_shift($rows);
        $rows = collect($rows)->keyBy(fn ($row) => $row[array_search('Invoice Number', $header)]);

        $hotelColumn = array_search('Expenses (Hotel)', $header);
        $tollsColumn = array_search('Expenses (Tolls)', $header);

        $this->assertSame('0.00', $rows[$summary->invoice_number][$hotelColumn]);
        $this->assertSame('0.00', $rows[$summary->invoice_number][$tollsColumn]);

        // Still reported, on the invoice that actually incurred it.
        $this->assertSame('125.00', $rows[$a->fresh()->invoice_number][$hotelColumn]);
        $this->assertSame('10.00', $rows[$a->fresh()->invoice_number][$tollsColumn]);
    }

    public function test_the_summary_edit_page_offers_no_per_job_overrides(): void
    {
        $a = $this->childInvoice('JOB-A', ['hotel' => 125]);
        $b = $this->childInvoice('JOB-B');
        $summary = $this->summaryOf([$a, $b]);

        $response = $this->actingAs($this->admin)
            ->get(route('my.invoices.edit', ['invoice' => $summary->id]))
            ->assertOk();

        // Offering these on a cover sheet invited writing one job's figures onto
        // the whole summary.
        foreach (['values[hotel]', 'values[tolls]', 'values[rate_code]', 'values[pickup_address]'] as $field) {
            $response->assertDontSee($field, false);
        }

        // The two genuine rollups stay editable.
        $response->assertSee('values[total]', false);
        $response->assertSee('values[billable_miles]', false);

        // A single invoice keeps the full form.
        $this->actingAs($this->admin)
            ->get(route('my.invoices.edit', ['invoice' => $a->id]))
            ->assertOk()
            ->assertSee('values[hotel]', false)
            ->assertSee('values[rate_code]', false);
    }

    public function test_the_migration_strips_inherited_keys_and_keeps_the_summarys_own(): void
    {
        $a = $this->childInvoice('JOB-A', ['hotel' => 125, 'tolls' => 10]);
        $b = $this->childInvoice('JOB-B');
        $summary = $this->summaryOf([$a, $b]);

        // Reproduce a summary created before the fix.
        $legacy = array_merge($a->fresh()->values, $summary->values, [
            'payments' => [['amount' => 50]],
            'late_fees' => ['late_fee_amount' => 12.5],
            'notes' => 'Net 30 agreed by phone',
        ]);
        $summary->forceFill(['values' => $legacy])->save();
        $this->assertArrayHasKey('hotel', $summary->fresh()->values);

        $migration = require database_path('migrations/2026_08_21_000005_strip_inherited_job_scalars_from_summary_invoices.php');
        $migration->up();

        $values = $summary->fresh()->values;

        foreach (['hotel', 'tolls', 'job_no', 'rate_code', 'extra_charge'] as $key) {
            $this->assertArrayNotHasKey($key, $values);
        }

        $this->assertSame('SUMMARY INVOICE', $values['title']);
        $this->assertCount(2, $values['summary_items']);
        $this->assertSame([['amount' => 50]], $values['payments']);
        $this->assertSame(12.5, $values['late_fees']['late_fee_amount']);
        $this->assertSame('Net 30 agreed by phone', $values['notes']);

        // Child invoices are untouched -- they are where the expenses live.
        $this->assertSame(125.0, (float) $a->fresh()->values['hotel']);
    }
}
