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
        $manager = User::factory()->manager()->create([
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

        // Managers may edit but not void: voiding is the admin-only `delete`
        // ability (TASK-446).
        $response->assertForbidden();
        $this->assertFalse($invoice->fresh()->isVoid());

        $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);

        $response = $this->actingAs($admin)->put(route('my.invoices.update', $invoice), [
            'paid_in_full' => 'no',
            'delete' => 'on',
        ]);

        $response->assertRedirect(route('my.jobs.show', ['job' => $job->id]));
        $response->assertSessionHas('success');

        // Void replaces delete (TASK-480): the row stays, the status changes.
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => Invoice::STATUS_VOID]);
        $this->assertNotNull($invoice->fresh()->voided_at);
        $this->assertSame(0.0, $invoice->fresh()->remaining_balance);
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
            $this->assertDatabaseHas('summary_invoice_jobs', [
                'invoice_id' => $summary->id,
                'pilot_car_job_id' => $job->id,
            ]);
        }

        // Everything is a draft until sent (TASK-480): the customer hears
        // nothing at creation, and only from Send.
        Event::assertNotDispatched(InvoiceReady::class);
        $this->assertTrue($summary->isDraft());
        $this->assertTrue($children->every(fn (Invoice $child) => $child->isDraft()));

        // A summary cannot go out while a child is still a draft ...
        $this->actingAs($admin)->post(route('my.invoices.send', $summary))->assertSessionHas('error');
        Event::assertNotDispatched(InvoiceReady::class);

        // ... so send the children, then the summary.
        foreach ($children as $child) {
            $this->actingAs($admin)->post(route('my.invoices.send', $child))->assertSessionHas('success');
        }
        $this->actingAs($admin)->post(route('my.invoices.send', $summary))->assertSessionHas('success');

        Event::assertDispatchedTimes(InvoiceReady::class, 3);
        Event::assertDispatched(InvoiceReady::class, fn ($event) => $event->invoice->id === $summary->id);
        $this->assertTrue($summary->fresh()->isSent());
        $this->assertNotNull($summary->fresh()->sent_at);
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

        // Voided, not deleted (TASK-480).
        $this->assertDatabaseHas('invoices', ['id' => $summary->id, 'status' => Invoice::STATUS_VOID]);

        foreach ($children as $child) {
            $fresh = $child->fresh();
            $this->assertDatabaseHas('invoices', ['id' => $child->id]);
            $this->assertFalse($fresh->isVoid(), 'Released child stays live.');
            $this->assertNull($fresh->parent_invoice_id, 'Released child should no longer be parented by the summary.');
            $this->assertNotNull($fresh->pilot_car_job_id, 'Released child should still be associated with its job via pilot_car_job_id (single invoices use the FK, not the summary_invoice_jobs pivot).');
        }

        // The summary's pivot rows should have been cleared as part of the delete.
        $this->assertDatabaseMissing('summary_invoice_jobs', [
            'invoice_id' => $summary->id,
        ]);
    }

    /**
     * TASK-382: the edit form had an "Invoice Notes" textarea and a "Truck Notes"
     * text input both named values[notes]. The later one in the DOM won on submit,
     * so whatever an admin typed into Invoice Notes - the field that prints as the
     * Notes block on the invoice - was silently replaced by the (usually blank)
     * Truck Notes value. Truck Notes now owns values[truck_notes].
     */
    public function test_invoice_notes_and_truck_notes_do_not_share_a_field_name(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->admin()->create([
            'organization_id' => $organization->id,
        ]);

        $customer = Customer::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $job = PilotCarJob::create([
            'job_no' => 'JOB-NOTES',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'rate_code' => 'per_mile_rate_2_00',
            'rate_value' => '2.00',
        ]);

        $invoice = Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'pilot_car_job_id' => $job->id,
            'values' => ['title' => 'INVOICE', 'total' => 500],
        ]);

        JobInvoice::create([
            'invoice_id' => $invoice->id,
            'pilot_car_job_id' => $job->id,
        ]);

        $html = $this->actingAs($manager)
            ->get(route('my.invoices.edit', $invoice))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            substr_count($html, 'name="values[notes]"'),
            'The edit form must post exactly one values[notes] field, or the last one in the DOM silently wins.'
        );
        $this->assertSame(1, substr_count($html, 'name="values[truck_notes]"'));
    }

    /**
     * TASK-382: the two fields must survive a save independently - filling Truck
     * Notes must not blank out the invoice Notes block, and vice versa.
     */
    public function test_truck_notes_does_not_clobber_invoice_notes_on_save(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->admin()->create([
            'organization_id' => $organization->id,
        ]);

        $customer = Customer::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $job = PilotCarJob::create([
            'job_no' => 'JOB-NOTES-SAVE',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
        ]);

        $invoice = Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'pilot_car_job_id' => $job->id,
            'values' => ['title' => 'INVOICE'],
        ]);

        JobInvoice::create([
            'invoice_id' => $invoice->id,
            'pilot_car_job_id' => $job->id,
        ]);

        $this->actingAs($manager)->put(route('my.invoices.update', $invoice), [
            'paid_in_full' => 'no',
            'values' => [
                'notes' => 'Please remit within 30 days.',
                'truck_notes' => 'Blue Peterbilt, refrigerated',
            ],
        ])->assertRedirect(route('my.invoices.edit', $invoice));

        $invoice->refresh();

        $this->assertSame('Please remit within 30 days.', data_get($invoice->values, 'notes'));
        $this->assertSame('Blue Peterbilt, refrigerated', data_get($invoice->values, 'truck_notes'));
    }
}
