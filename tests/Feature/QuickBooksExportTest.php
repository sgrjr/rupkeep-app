<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-384 — the QuickBooks CSV.
 *
 * The job CSV (JobCsvExportTest) is a report: one row per job, every figure in
 * its own column. QuickBooks cannot read that -- it has no idea what "Expenses
 * (Wait Time)" is. QuickBooks models an invoice as a header plus N line items,
 * and its importer expresses that by repeating the invoice number down
 * consecutive rows. This suite pins that shape, and one property above all:
 * the lines of an invoice sum to the total the customer was billed.
 */
class QuickBooksExportTest extends TestCase
{
    use RefreshDatabase;

    private const INVOICE_NO = 0;
    private const CUSTOMER = 1;
    private const INVOICE_DATE = 2;
    private const DUE_DATE = 3;
    private const TERMS = 4;
    private const MEMO = 6;
    private const ITEM = 7;
    private const DESCRIPTION = 8;
    private const QUANTITY = 9;
    private const RATE = 10;
    private const AMOUNT = 11;
    private const SERVICE_DATE = 14;

    public function test_the_header_is_the_quickbooks_import_template(): void
    {
        [$manager, $organization, $customer] = $this->exportOrg();

        $this->invoice($organization, $customer, ['total' => 100]);

        $this->assertSame([
            '*InvoiceNo',
            '*Customer',
            '*InvoiceDate',
            '*DueDate',
            'Terms',
            'Location',
            'Memo',
            'Item(Product/Service)',
            'ItemDescription',
            'ItemQuantity',
            'ItemRate',
            '*ItemAmount',
            'Taxable',
            'TaxRate',
            'Service Date',
        ], $this->headerRow($manager));
    }

    /**
     * The point of the whole restructure: one invoice becomes several rows that
     * share an invoice number, which is how QuickBooks' importer builds a
     * multi-line invoice. In the old single-row CSV these charges could only be
     * separate columns QuickBooks would ignore, or prose in a memo.
     */
    public function test_one_invoice_bills_as_several_lines_sharing_an_invoice_number(): void
    {
        [$manager, $organization, $customer] = $this->exportOrg();

        // A $575 day rate with a $125 hotel and a $10 toll.
        $invoice = $this->invoice($organization, $customer, [
            'total' => 710,
            'hotel' => 125,
            'tolls' => 10,
        ]);

        $rows = $this->dataRows($manager);

        $this->assertCount(3, $rows);

        foreach ($rows as $row) {
            $this->assertSame((string) $invoice->fresh()->invoice_number, $row[self::INVOICE_NO]);
            $this->assertSame($customer->name, $row[self::CUSTOMER]);
        }

        $this->assertSame(
            ['Pilot Car Escort', 'Tolls', 'Lodging'],
            array_map(fn ($r) => $r[self::ITEM], $rows)
        );

        $this->assertSame(
            ['575.00', '10.00', '125.00'],
            array_map(fn ($r) => $r[self::AMOUNT], $rows)
        );
    }

    /**
     * The property everything rests on. If the lines do not add up to the
     * total, QuickBooks bills a different number than the invoice the customer
     * is holding, and the discrepancy is invisible until someone reconciles.
     *
     * It holds by construction -- the Pilot Car Service line is defined as the
     * total minus every itemized charge -- so this guards the construction.
     */
    public function test_the_lines_of_an_invoice_sum_to_the_billed_total(): void
    {
        [$manager, $organization, $customer] = $this->exportOrg();

        $this->invoice($organization, $customer, [
            'total' => 1234.56,
            'hotel' => 125,
            'tolls' => 10.25,
            'cost_of_wait_time' => 60,
            'wait_time_hours' => 3,
            'wait_time_billable_hours' => 2,
            'wait_time_rate' => 30,
            'dead_head_charge' => 45,
            'dead_head' => 30,
            'mini_addon_amount' => 75,
            'extra_charge' => 50,
            'extra_charges' => [
                ['description' => 'Equipment rental', 'amount' => 50],
            ],
        ]);

        $rows = $this->dataRows($manager);

        $this->assertGreaterThan(1, count($rows));

        $sum = array_sum(array_map(fn ($r) => (float) $r[self::AMOUNT], $rows));

        $this->assertEqualsWithDelta(1234.56, $sum, 0.01);
    }

    /**
     * A named one-off charge (TASK-330) posts to a single stable QuickBooks
     * item with its own text in the description. Letting the free text become
     * the item would litter the customer's chart of accounts with a new
     * product for every rental, permit and ferry crossing they ever billed.
     */
    public function test_a_named_extra_charge_keeps_its_text_in_the_description(): void
    {
        [$manager, $organization, $customer] = $this->exportOrg();

        $this->invoice($organization, $customer, [
            'total' => 200,
            'extra_charge' => 50,
            'extra_charges' => [
                ['description' => 'Equipment rental', 'amount' => 50],
            ],
        ]);

        $rows = $this->dataRows($manager);
        $extra = $this->rowWithItem($rows, 'Extra Charge');

        $this->assertSame('Equipment rental', $extra[self::DESCRIPTION]);
        $this->assertSame('50.00', $extra[self::AMOUNT]);
    }

    /**
     * TASK-383, resolved properly this time. A summary's total IS the sum of
     * its children, so billing both doubles the revenue. The summary is the
     * document the customer paid against, so it is the invoice QuickBooks gets
     * -- and because a QuickBooks invoice has lines, the children's detail
     * rides inside it rather than being flattened into a text column.
     */
    public function test_a_summary_bills_as_one_invoice_carrying_its_childrens_lines(): void
    {
        [$manager, $organization, $customer] = $this->exportOrg();

        $summary = Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'invoice_type' => 'summary',
            'values' => ['total' => 3000],
        ]);

        $this->child($organization, $customer, $summary, [
            'total' => 1800, 'job_no' => 'JOB-ALPHA', 'hotel' => 120,
        ]);
        $this->child($organization, $customer, $summary, [
            'total' => 1200, 'job_no' => 'JOB-BETA', 'tolls' => 30,
        ]);

        $rows = $this->dataRows($manager);

        // One invoice, not three.
        $numbers = array_unique(array_map(fn ($r) => $r[self::INVOICE_NO], $rows));
        $this->assertCount(1, $numbers);
        $this->assertSame((string) $summary->fresh()->invoice_number, reset($numbers));

        // Whose lines add up to the summary total -- billed once.
        $sum = array_sum(array_map(fn ($r) => (float) $r[self::AMOUNT], $rows));
        $this->assertEqualsWithDelta(3000.0, $sum, 0.01);

        // And whose detail survived: each line says which job it came from.
        $descriptions = array_map(fn ($r) => $r[self::DESCRIPTION], $rows);

        $this->assertContains('JOB-ALPHA - Overnight / Hotel', $descriptions);
        $this->assertContains('JOB-BETA - Tolls', $descriptions);

        $this->assertSame(
            '120.00',
            $this->rowWithDescription($rows, 'JOB-ALPHA - Overnight / Hotel')[self::AMOUNT]
        );
    }

    /**
     * The memo is where a bookkeeper looks to tie the QuickBooks invoice back
     * to the work, so a summary names every job it covers.
     */
    public function test_the_memo_names_the_jobs_the_invoice_covers(): void
    {
        [$manager, $organization, $customer] = $this->exportOrg();

        $summary = Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'invoice_type' => 'summary',
            'values' => ['total' => 3000],
        ]);

        $this->child($organization, $customer, $summary, ['total' => 1800, 'job_no' => 'JOB-ALPHA']);
        $this->child($organization, $customer, $summary, ['total' => 1200, 'job_no' => 'JOB-BETA']);

        $memo = $this->dataRows($manager)[0][self::MEMO];

        $this->assertStringContainsString('JOB-ALPHA', $memo);
        $this->assertStringContainsString('JOB-BETA', $memo);
    }

    /**
     * Same reasoning as the job CSV, mirrored: dropping a child whose summary
     * is not in the export would lose that revenue rather than duplicate it.
     */
    public function test_children_bill_on_their_own_when_their_summary_is_outside_the_export(): void
    {
        [$manager, $organization, $customer] = $this->exportOrg();

        $summary = Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'invoice_type' => 'summary',
            'values' => ['total' => 3000],
        ]);

        $childNumbers = [];

        foreach ([1800, 1200] as $total) {
            $child = $this->child($organization, $customer, $summary, ['total' => $total]);
            $child->forceFill(['created_at' => '2026-03-10 09:00:00'])->save();
            $childNumbers[] = (string) $child->invoice_number;
        }

        sort($childNumbers);

        $summary->forceFill(['created_at' => '2026-04-02 09:00:00'])->save();

        $rows = $this->dataRows($manager, ['from' => '2026-03-01', 'to' => '2026-03-31']);

        $numbers = array_values(array_unique(array_map(fn ($r) => $r[self::INVOICE_NO], $rows)));
        sort($numbers);

        $this->assertSame($childNumbers, $numbers);
    }

    /**
     * An old invoice carrying a total and nothing itemizable still has to bill.
     * Skipping it would quietly drop revenue; one line at the stored total is
     * honest about what we know.
     */
    public function test_an_invoice_with_nothing_itemized_still_bills_its_total(): void
    {
        [$manager, $organization, $customer] = $this->exportOrg();

        $this->invoice($organization, $customer, ['total' => 450.25]);

        $rows = $this->dataRows($manager);

        $this->assertCount(1, $rows);
        $this->assertSame('450.25', $rows[0][self::AMOUNT]);
        $this->assertSame('Pilot Car Escort', $rows[0][self::ITEM]);
    }

    /**
     * A zero-total invoice would import as a $0 invoice in QuickBooks, which is
     * noise in a customer's A/R rather than a record of anything.
     */
    public function test_a_zero_total_invoice_is_not_billed(): void
    {
        [$manager, $organization, $customer] = $this->exportOrg();

        $this->invoice($organization, $customer, ['total' => 0]);

        $this->assertSame([], $this->dataRows($manager));
    }

    public function test_dates_and_terms_are_in_the_format_quickbooks_reads(): void
    {
        [$manager, $organization, $customer] = $this->exportOrg();

        $job = PilotCarJob::create([
            'job_no' => 'JOB-QB-1',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'load_no' => 'LOAD-1',
            'pickup_address' => 'Gorham, ME',
            'delivery_address' => 'Boston, MA',
            'rate_code' => 'flat_rate',
            'rate_value' => '575.00',
            'scheduled_pickup_at' => '2026-05-14 09:00:00',
        ]);

        $invoice = $this->invoice($organization, $customer, ['total' => 575], $job);
        $invoice->forceFill(['created_at' => '2026-05-20 09:00:00'])->save();

        $row = $this->dataRows($manager)[0];

        $this->assertSame('05/20/2026', $row[self::INVOICE_DATE]);
        $this->assertSame('06/19/2026', $row[self::DUE_DATE], 'Thirty days on from the invoice date.');
        $this->assertSame('Net 30', $row[self::TERMS]);
        $this->assertSame('05/14/2026', $row[self::SERVICE_DATE], 'The date the work happened (TASK-345).');
    }

    /**
     * QuickBooks refuses an import over 100 invoices outright, so handing the
     * user a file it will reject is a wasted round trip. Say so before the
     * download instead.
     */
    public function test_an_export_too_large_for_quickbooks_is_refused_with_an_explanation(): void
    {
        [$manager, $organization, $customer] = $this->exportOrg();

        for ($i = 0; $i < 101; $i++) {
            $this->invoice($organization, $customer, ['total' => 100]);
        }

        $this->actingAs($manager)
            ->get(route('my.invoices.export.quickbooks'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_a_customer_cannot_pull_the_accounting_feed(): void
    {
        [, $organization] = $this->exportOrg();

        $customerUser = User::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($customerUser)
            ->get(route('my.invoices.export.quickbooks'))
            ->assertForbidden();
    }

    // ---------------------------------------------------------------- helpers

    private function exportOrg(): array
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        return [$manager, $organization, $customer];
    }

    private function invoice(Organization $organization, Customer $customer, array $values, ?PilotCarJob $job = null): Invoice
    {
        return Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'pilot_car_job_id' => $job?->id,
            'values' => $values,
        ]);
    }

    private function child(Organization $organization, Customer $customer, Invoice $summary, array $values): Invoice
    {
        return Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'parent_invoice_id' => $summary->id,
            'values' => $values,
        ]);
    }

    private function csv(User $manager, array $filters = []): array
    {
        $response = $this->actingAs($manager)->get(route('my.invoices.export.quickbooks', $filters));
        $response->assertOk();

        $lines = array_values(array_filter(explode("\n", trim($response->streamedContent()))));

        return array_map('str_getcsv', $lines);
    }

    private function headerRow(User $manager): array
    {
        return $this->csv($manager)[0];
    }

    private function dataRows(User $manager, array $filters = []): array
    {
        return array_values(array_slice($this->csv($manager, $filters), 1));
    }

    private function rowWithItem(array $rows, string $item): array
    {
        foreach ($rows as $row) {
            if ($row[self::ITEM] === $item) {
                return $row;
            }
        }

        $this->fail("No row billed to the item [{$item}].");
    }

    private function rowWithDescription(array $rows, string $description): array
    {
        foreach ($rows as $row) {
            if ($row[self::DESCRIPTION] === $description) {
                return $row;
            }
        }

        $this->fail("No row described as [{$description}].");
    }
}
