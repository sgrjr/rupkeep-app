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
            'Customer',
            'Job Number',
            'Billable Miles',
            'Amount',
            'Paid',
            'Memo',
        ], $header);

        $data = str_getcsv($lines[1]);
        $this->assertSame($invoice->invoice_number, $data[0]);
        $this->assertSame(optional($invoice->created_at)->toDateString(), $data[1]);
        $this->assertSame($customer->name, $data[2]);
        $this->assertSame('JOB-001', $data[3]);
        $this->assertSame('200', $data[4]);
        $this->assertSame('450.25', $data[5]);
        $this->assertSame('No', $data[6]);
        $this->assertSame('Test memo', $data[7]);
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
        $this->assertSame('Yes', $dataRow[6]);
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
}


