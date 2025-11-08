<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_printable_invoice(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create([
            'organization_id' => $organization->id,
        ]);
        $customer = Customer::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $job = PilotCarJob::create([
            'job_no' => 'JOB-PRINT',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'load_no' => 'LOAD-PRINT',
            'pickup_address' => '123 Demo St',
            'delivery_address' => '456 Demo Ave',
            'rate_code' => 'per_mile_rate_2_00',
            'rate_value' => '2.00',
        ]);

        $invoice = Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'pilot_car_job_id' => $job->id,
            'values' => [
                'title' => 'INVOICE',
                'total' => 325.50,
                'bill_from' => [
                    'company' => 'Casco Bay Pilot Car',
                    'street' => 'P.O. Box 104',
                    'city' => 'Gorham',
                    'state' => 'ME',
                    'zip' => '04038',
                ],
                'bill_to' => [
                    'company' => $customer->name,
                ],
                'billable_miles' => 160,
                'rate_value' => 2.35,
                'notes' => 'Test printable invoice.',
            ],
        ]);

        $response = $this->actingAs($manager)->get(route('my.invoices.print', $invoice));

        $response->assertOk();
        $response->assertSee($invoice->invoice_number, false);
        $response->assertSee('Test printable invoice.');
        $response->assertSee('Casco Bay Pilot Car');
        $response->assertSee('$325.50');
    }

    public function test_customer_cannot_access_staff_print_route(): void
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
        $job = PilotCarJob::create([
            'job_no' => 'JOB-PRINT-C',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'load_no' => 'LOAD-PRINT-C',
            'pickup_address' => 'Pickup',
            'delivery_address' => 'Delivery',
        ]);

        $invoice = Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'pilot_car_job_id' => $job->id,
        ]);

        $response = $this->actingAs($customerUser)->get(route('my.invoices.print', $invoice));

        $response->assertForbidden();
    }
}


