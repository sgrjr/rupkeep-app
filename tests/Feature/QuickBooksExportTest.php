<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickBooksExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_download_quickbooks_csv(): void
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

        $response = $this->actingAs($manager)->get(route('my.invoices.export.quickbooks'));

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

        $response = $this->actingAs($manager)->get(route('my.invoices.export.quickbooks', [
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

        $response = $this->actingAs($customerUser)->get(route('my.invoices.export.quickbooks'));

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
        $response = $this->actingAs($manager)->get(route('my.invoices.export.quickbooks'));
        $response->assertOk();

        $lines = array_values(array_filter(explode("\n", trim($response->streamedContent()))));

        return str_getcsv($lines[1]);
    }
}
