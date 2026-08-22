<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobCsvExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_download_the_job_csv(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create([
            'organization_id' => $organization->id,
        ]);
        $customer = Customer::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $job = PilotCarJob::create([
            'job_no' => 'JOB-001',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'load_no' => 'LOAD-100',
            'pickup_address' => '123 Pickup',
            'delivery_address' => '456 Delivery',
            'rate_code' => 'per_mile_rate_2_00',
            'rate_value' => '2.00',
        ]);

        $invoice = Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'pilot_car_job_id' => $job->id,
            'values' => [
                'total' => 450.25,
                'billable_miles' => 200,
                'notes' => 'Test memo',
            ],
        ]);

        $response = $this->actingAs($manager)->get(route('my.invoices.export.jobs'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($content))));
        $this->assertNotEmpty($lines);

        $header = str_getcsv($lines[0]);
        $this->assertSame([
            'Invoice Number',
            'Invoice Date',
            'Customer Name',
            'Customer Address',
            'Job Number',
            'Load Number',
            'Billable Miles',
            'Rate Code',
            'Rate Value',
            'Subtotal',
            'Expenses (Hotel)',
            'Expenses (Tolls)',
            'Expenses (Gas)',
            'Expenses (Wait Time)',
            'Expenses (Extra Charges)',
            'Extra Charges (Detail)',
            'Deadhead Count',
            'Deadhead Amount',
            'Mini Charge',
            'Total Amount',
            'Paid Status',
            'Payment Date',
            'Check Number',
            'Memo',
            'Summary Includes',
        ], $header);

        $data = str_getcsv($lines[1]);
        $this->assertSame($invoice->invoice_number, $data[0]);
        $this->assertSame(optional($invoice->created_at)->format('m/d/Y'), $data[1]);
        $this->assertSame($customer->name, $data[2]);
        $this->assertSame('JOB-001', $data[4]);
        $this->assertSame('LOAD-100', $data[5]);
        $this->assertSame('200.0', $data[6]);
        // Indices past 14 shifted by one when Extra Charges (Detail) was added
        // at 15 (TASK-378).
        $this->assertSame('450.25', $data[19]);
        $this->assertSame('Unpaid', $data[20]);
        $this->assertSame('Test memo', $data[23]);
    }

    public function test_filters_by_date_and_paid_status(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create([
            'organization_id' => $organization->id,
        ]);
        $customer = Customer::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $job = PilotCarJob::create([
            'job_no' => 'JOB-002',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'load_no' => 'LOAD-200',
            'pickup_address' => 'Pickup',
            'delivery_address' => 'Delivery',
            'rate_code' => 'per_mile_rate_2_00',
            'rate_value' => '2.00',
        ]);

        $paidInvoice = Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'pilot_car_job_id' => $job->id,
            'paid_in_full' => true,
            'values' => ['total' => 100],
        ]);

        $paidInvoice->forceFill(['created_at' => now()->subDays(10)])->save();

        $discarded = Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'pilot_car_job_id' => $job->id,
            'paid_in_full' => false,
            'values' => ['total' => 200],
        ]);
        $discarded->forceFill(['created_at' => now()->subDays(2)])->save();

        $response = $this->actingAs($manager)->get(route('my.invoices.export.jobs', [
            'from' => now()->subDays(15)->toDateString(),
            'to' => now()->subDays(5)->toDateString(),
            'paid' => 'yes',
        ]));

        $content = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($content))));

        $rows = array_map('str_getcsv', $lines);
        $this->assertGreaterThan(1, count($rows));

        $dataRow = $rows[1];
        // Column 20 is Paid Status (Paid/Unpaid) -- it was 19 before Extra
        // Charges (Detail) was inserted at 15 (TASK-378). Filter `paid=yes`
        // should only include the paid invoice.
        $this->assertSame('Paid', $dataRow[20]);
        $this->assertNotSame((string) $discarded->invoice_number, $dataRow[0]);
    }

    public function test_customer_cannot_access_export(): void
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $customerUser = User::factory()->create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'organization_role' => User::ROLE_CUSTOMER,
        ]);

        $response = $this->actingAs($customerUser)->get(route('my.invoices.export.jobs'));

        $response->assertForbidden();
    }

    /**
     * TASK-378. The CSV is one row per invoice, so the named charges TASK-330
     * introduced cannot each become a column. They ride in one text column
     * instead, while the scalar stays the figure that totals against.
     */
    public function test_named_extra_charges_are_listed_in_the_detail_column(): void
    {
        $manager = $this->exportFixture([
            'total' => 482.00,
            'extra_charge' => '475.00',
            'extra_charges' => [
                ['description' => 'Equipment rental', 'amount' => 340.00, 'log_id' => 1],
                ['description' => 'Ferry crossing', 'amount' => 60.00, 'log_id' => 1],
                ['description' => 'Permit expediting', 'amount' => 75.00, 'log_id' => 1],
            ],
        ]);

        $data = $this->firstDataRow($manager);

        // The scalar column is untouched, so the CSV still totals correctly.
        $this->assertSame('475.00', $data[14]);
        $this->assertSame(
            'Equipment rental $340.00; Ferry crossing $60.00; Permit expediting $75.00',
            $data[15]
        );
    }

    /**
     * Invoices issued before TASK-330 recorded only the total with no
     * itemization. An empty cell is honest there; an invented one is not.
     */
    public function test_a_legacy_invoice_leaves_the_detail_column_empty(): void
    {
        $manager = $this->exportFixture([
            'total' => 450.25,
            'extra_charge' => '45.00',
        ]);

        $data = $this->firstDataRow($manager);

        $this->assertSame('45.00', $data[14]);
        $this->assertSame('', $data[15]);
    }

    private function exportFixture(array $values): User
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        $job = PilotCarJob::create([
            'job_no' => 'JOB-378',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'pickup_address' => '123 Pickup',
            'delivery_address' => '456 Delivery',
            'rate_code' => 'flat_rate',
            'rate_value' => '575.00',
        ]);

        Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'pilot_car_job_id' => $job->id,
            'values' => $values,
        ]);

        return $manager;
    }

    private function firstDataRow(User $manager): array
    {
        $response = $this->actingAs($manager)->get(route('my.invoices.export.jobs'));
        $response->assertOk();

        $lines = array_values(array_filter(explode("\n", trim($response->streamedContent()))));

        return str_getcsv($lines[1]);
    }

    /**
     * One row per JOB is the whole contract of this file, and a summary invoice
     * is not a job -- it is a cover sheet over several. Where both a summary and
     * the jobs under it are in range, the children are the rows worth keeping.
     *
     * Note this resolves the TASK-383 double-count in the OPPOSITE direction to
     * the QuickBooks export, which keeps the summary and drops the children.
     * Both files must sum to the revenue actually billed; they disagree only on
     * which row carries it, because they answer different questions.
     */
    public function test_a_summary_is_not_exported_alongside_the_jobs_it_covers(): void
    {
        [$manager, $organization, $customer] = $this->exportOrg();

        $summary = Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'invoice_type' => 'summary',
            'values' => ['total' => 3000],
        ]);

        $childNumbers = [];

        foreach ([1, 2] as $ignored) {
            $child = Invoice::create([
                'organization_id' => $organization->id,
                'customer_id' => $customer->id,
                'parent_invoice_id' => $summary->id,
                'values' => ['total' => 1500],
            ]);

            $childNumbers[] = (string) $child->invoice_number;
        }

        sort($childNumbers);

        $rows = $this->dataRows($manager);

        $this->assertCount(2, $rows, 'The jobs survive; their cover sheet does not.');

        $numbers = array_map(fn ($r) => $r[0], $rows);
        sort($numbers);
        $this->assertSame($childNumbers, $numbers);

        $total = array_sum(array_map(fn ($r) => (float) $r[19], $rows));
        $this->assertSame(3000.0, $total, 'The export must sum to the revenue actually billed, not double it.');
    }

    /**
     * Dropping a summary whose children are NOT in the export would silently
     * lose that revenue - a worse failure than double-counting it, because
     * nothing on the sheet hints anything is missing. A range that catches the
     * summary but not the jobs under it keeps the summary.
     */
    public function test_a_summary_is_kept_when_the_jobs_it_covers_are_outside_the_export(): void
    {
        [$manager, $organization, $customer] = $this->exportOrg();

        $summary = $this->summaryCutLater($organization, $customer);

        $rows = $this->dataRows($manager, ['from' => '2026-04-01', 'to' => '2026-04-30']);

        $this->assertCount(1, $rows);
        $this->assertSame((string) $summary->fresh()->invoice_number, $rows[0][0]);
        $this->assertSame('3000.00', $rows[0][19]);
    }

    /**
     * TASK-383 follow-up: a summary stores no expense scalars of its own
     * (TASK-379), so a summary row that survives on its own would report zero
     * expenses for jobs that plainly had them. It rolls its children up at
     * export time instead, and names what it stands for in a "Summary Includes"
     * column.
     */
    public function test_a_surviving_summary_row_names_the_invoices_it_stands_for(): void
    {
        [$manager, $organization, $customer] = $this->exportOrg();

        $this->summaryCutLater($organization, $customer);

        $rows = $this->dataRows($manager, ['from' => '2026-04-01', 'to' => '2026-04-30']);
        $this->assertCount(1, $rows);

        $row = $rows[0];

        // Roll-up, not inheritance: both children contribute. Giving only the
        // first child a hotel bill would make an inherited 120 and a summed 120
        // the same number, and the assertion could not tell them apart.
        $this->assertSame('120.00', $row[10], 'Hotel must be the sum across children.');
        $this->assertSame('30.00', $row[11], 'Tolls must be the sum across children.');

        // The revenue total is still the summary total, not doubled.
        $this->assertSame('3000.00', $row[19]);

        $detail = $row[24];
        $this->assertStringContainsString('JOB-ALPHA', $detail);
        $this->assertStringContainsString('JOB-BETA', $detail);
        $this->assertStringContainsString('1800.00', $detail);
        $this->assertStringContainsString('1200.00', $detail);
        $this->assertStringContainsString('Hotel 120.00', $detail);
    }

    /**
     * A single invoice has no children, so the summary column stays empty
     * rather than repeating the invoice against itself.
     */
    public function test_a_single_invoice_leaves_the_summary_column_empty(): void
    {
        $manager = $this->exportFixture(['total' => 450.25, 'hotel' => 75]);

        $data = $this->firstDataRow($manager);

        $this->assertSame('', $data[24]);
        $this->assertSame('75.00', $data[10], 'A single invoice still reports its own expenses.');
    }

    private function exportOrg(): array
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        return [$manager, $organization, $customer];
    }

    /**
     * Two jobs invoiced in March, covered by a summary not cut until April, so
     * an export of April alone sees the summary without its children.
     *
     * Both children carry expenses on purpose -- see the roll-up test.
     */
    private function summaryCutLater(Organization $organization, Customer $customer): Invoice
    {
        $summary = Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'invoice_type' => 'summary',
            'values' => ['total' => 3000],
        ]);

        $children = [
            ['total' => 1800, 'job_no' => 'JOB-ALPHA', 'hotel' => 120],
            ['total' => 1200, 'job_no' => 'JOB-BETA', 'tolls' => 30],
        ];

        foreach ($children as $values) {
            $child = Invoice::create([
                'organization_id' => $organization->id,
                'customer_id' => $customer->id,
                'parent_invoice_id' => $summary->id,
                'values' => $values,
            ]);

            $child->forceFill(['created_at' => '2026-03-10 09:00:00'])->save();
        }

        $summary->forceFill(['created_at' => '2026-04-02 09:00:00'])->save();

        return $summary;
    }

    private function dataRows(User $manager, array $filters = []): array
    {
        $response = $this->actingAs($manager)->get(route('my.invoices.export.jobs', $filters));
        $response->assertOk();

        $lines = array_values(array_filter(explode("\n", trim($response->streamedContent()))));

        return array_map('str_getcsv', array_slice($lines, 1));
    }
}
