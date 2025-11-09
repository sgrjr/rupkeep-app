<?php

namespace Tests\Feature;

use App\Events\InvoiceReady;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JobInvoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class InvoiceUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_manager_can_update_invoice_snapshot_fields(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->admin()->create([
            'organization_id' => $organization->id,
        ]);

        $customer = Customer::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $job = PilotCarJob::create([
            'job_no' => 'JOB-UPDATE',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'rate_code' => 'per_mile_rate_2_00',
            'rate_value' => '2.00',
        ]);

        $invoice = Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'pilot_car_job_id' => $job->id,
            'values' => [
                'title' => 'INVOICE',
                'total' => 500,
                'billable_miles' => 200,
                'bill_to' => [
                    'company' => 'Original Company',
                ],
            ],
        ]);

        JobInvoice::create([
            'invoice_id' => $invoice->id,
            'pilot_car_job_id' => $job->id,
        ]);

        $response = $this->actingAs($manager)->put(route('my.invoices.update', $invoice), [
            'paid_in_full' => 'yes',
            'values' => [
                'bill_to' => [
                    'company' => 'Updated Company LLC',
                    'street' => '123 Main Street',
                ],
                'total' => '725.25',
                'billable_miles' => '275',
                'notes' => 'Manual override note',
            ],
        ]);

        $response->assertRedirect(route('my.invoices.edit', $invoice));
        $response->assertSessionHas('success');

        $invoice->refresh();

        $this->assertTrue($invoice->paid_in_full);
        $this->assertSame('Updated Company LLC', data_get($invoice->values, 'bill_to.company'));
        $this->assertSame('123 Main Street', data_get($invoice->values, 'bill_to.street'));
        $this->assertEquals(725.25, data_get($invoice->values, 'total'));
        $this->assertEquals(275.0, data_get($invoice->values, 'billable_miles'));
        $this->assertSame('Manual override note', data_get($invoice->values, 'notes'));
    }

    public function test_manager_can_delete_invoice_snapshot(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->admin()->create([
            'organization_id' => $organization->id,
        ]);

        $customer = Customer::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $job = PilotCarJob::create([
            'job_no' => 'JOB-DELETE',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
        ]);

        $invoice = Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'pilot_car_job_id' => $job->id,
        ]);

        JobInvoice::create([
            'invoice_id' => $invoice->id,
            'pilot_car_job_id' => $job->id,
        ]);

        $response = $this->actingAs($manager)->put(route('my.invoices.update', $invoice), [
            'paid_in_full' => 'no',
            'delete' => 'on',
        ]);

        $response->assertRedirect(route('my.jobs.show', ['job' => $job->id]));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('invoices', [
            'id' => $invoice->id,
        ]);

        $this->assertDatabaseMissing('jobs_invoices', [
            'invoice_id' => $invoice->id,
            'pilot_car_job_id' => $job->id,
        ]);
    }

    public function test_creating_summary_invoice_groups_child_invoices(): void
    {
        Event::fake([InvoiceReady::class]);

        $organization = Organization::factory()->create();
        $admin = User::factory()->admin()->create([
            'organization_id' => $organization->id,
        ]);

        $customer = Customer::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $jobA = PilotCarJob::create([
            'job_no' => 'JOB-A',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'rate_code' => 'per_mile_rate_2_00',
            'rate_value' => '2.00',
        ]);

        $jobB = PilotCarJob::create([
            'job_no' => 'JOB-B',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'rate_code' => 'per_mile_rate_2_00',
            'rate_value' => '2.00',
        ]);

        $response = $this->actingAs($admin)->post(route('my.invoices.store'), [
            'invoice_this' => [$jobA->id, $jobB->id],
        ]);

        $summary = Invoice::where('invoice_type', 'summary')->first();
        $this->assertNotNull($summary, 'Summary invoice was not created.');

        $response->assertRedirect(route('my.invoices.edit', ['invoice' => $summary->id]));

        $children = Invoice::where('invoice_type', 'single')
            ->where('parent_invoice_id', $summary->id)
            ->get();

        $this->assertCount(2, $children);
        $this->assertTrue($children->every(fn (Invoice $child) => $child->parent_invoice_id === $summary->id));

        $expectedTotal = $children->sum(fn (Invoice $child) => (float) data_get($child->values, 'total', 0));
        $this->assertEquals($expectedTotal, (float) data_get($summary->values, 'total'));

        foreach ([$jobA, $jobB] as $job) {
            $this->assertDatabaseHas('jobs_invoices', [
                'invoice_id' => $summary->id,
                'pilot_car_job_id' => $job->id,
            ]);
        }

        Event::assertDispatchedTimes(InvoiceReady::class, 1);
        Event::assertDispatched(InvoiceReady::class, fn ($event) => $event->invoice->id === $summary->id);
    }

    public function test_summary_invoice_can_release_child_invoices_on_delete(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->admin()->create([
            'organization_id' => $organization->id,
        ]);

        $customer = Customer::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $jobA = PilotCarJob::create([
            'job_no' => 'JOB-REL-A',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
        ]);

        $jobB = PilotCarJob::create([
            'job_no' => 'JOB-REL-B',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($admin)->post(route('my.invoices.store'), [
            'invoice_this' => [$jobA->id, $jobB->id],
        ]);

        $summary = Invoice::where('invoice_type', 'summary')->firstOrFail();
        $children = Invoice::where('parent_invoice_id', $summary->id)->get();

        $response = $this->actingAs($admin)->put(route('my.invoices.update', $summary), [
            'paid_in_full' => 'no',
            'delete' => 'on',
            'delete_mode' => 'release_children',
        ]);

        $response->assertRedirect(route('my.jobs.show', ['job' => $jobA->id]));

        $this->assertDatabaseMissing('invoices', ['id' => $summary->id]);

        foreach ($children as $child) {
            $this->assertDatabaseHas('invoices', ['id' => $child->id]);
            $this->assertNull($child->fresh()->parent_invoice_id);
            $this->assertDatabaseHas('jobs_invoices', [
                'invoice_id' => $child->id,
            ]);
        }
    }
}

