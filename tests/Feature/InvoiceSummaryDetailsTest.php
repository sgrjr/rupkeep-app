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
 * TASK-345 — the required-data pass (TASK-344) applied to summary invoices.
 *
 * A summary aggregates several child jobs, so it carries per-row detail rather
 * than the single-invoice job block. Two gaps: truck driver / truck / trailer
 * were never copied into summary_items, and "Date Of Service" was the child
 * INVOICE's created_at rather than when the work actually happened — so every
 * row on a monthly summary showed the same date.
 */
class InvoiceSummaryDetailsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private Customer $customer;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        // Creating a summary is admin-gated (InvoicePolicy::create).
        $this->manager = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function childInvoice(string $jobNo, string $pickupDate, array $logAttributes = []): Invoice
    {
        $job = PilotCarJob::create([
            'job_no' => $jobNo,
            'customer_id' => $this->customer->id,
            'organization_id' => $this->organization->id,
            'load_no' => 'LOAD-' . $jobNo,
            'pickup_address' => 'Gorham, ME',
            'delivery_address' => 'Boston, MA',
            'rate_code' => 'flat_rate',
            'rate_value' => '575.00',
            'scheduled_pickup_at' => $pickupDate,
        ]);

        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);

        UserLog::create(array_merge([
            'job_id' => $job->id,
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'approval_status' => 'confirmed',
            'truck_no' => '69',
            'trailer_no' => 'Steerable',
            'start_mileage' => 0,
            'end_mileage' => 100,
            'start_job_mileage' => 0,
            'end_job_mileage' => 80,
        ], $logAttributes));

        return $job->fresh()->createInvoice();
    }

    private function summaryOf(array $children): Invoice
    {
        $this->actingAs($this->manager)
            ->post(route('my.invoices.create-summary'), [
                'invoice_ids' => collect($children)->pluck('id')->all(),
            ]);

        return Invoice::where('invoice_type', 'summary')->latest('id')->firstOrFail();
    }

    public function test_an_admin_can_actually_create_a_summary(): void
    {
        // TASK-371: the controller authorizes against Invoice::class, so the
        // Gate calls the policy with only the user. InvoicePolicy::create
        // required a second parameter, making every summary creation an
        // ArgumentCountError 500 before it reached the controller.
        $a = $this->childInvoice('JOB-A', '2025-12-18 09:00:00');
        $b = $this->childInvoice('JOB-B', '2025-12-22 07:00:00');

        $this->actingAs($this->manager)
            ->post(route('my.invoices.create-summary'), ['invoice_ids' => [$a->id, $b->id]])
            ->assertRedirect();

        $this->assertSame(1, Invoice::where('invoice_type', 'summary')->count());
    }

    public function test_a_manager_still_cannot_create_a_summary(): void
    {
        // Relaxing the signature must not relax the permission.
        $manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
        $a = $this->childInvoice('JOB-A', '2025-12-18 09:00:00');
        $b = $this->childInvoice('JOB-B', '2025-12-22 07:00:00');

        $this->actingAs($manager)
            ->post(route('my.invoices.create-summary'), ['invoice_ids' => [$a->id, $b->id]])
            ->assertForbidden();

        $this->assertSame(0, Invoice::where('invoice_type', 'summary')->count());
    }

    public function test_date_of_service_is_the_job_date_not_the_invoice_creation_date(): void
    {
        $a = $this->childInvoice('JOB-A', '2025-12-18 09:00:00');
        $b = $this->childInvoice('JOB-B', '2025-12-22 07:00:00');

        $items = collect($this->summaryOf([$a, $b])->values['summary_items']);

        $dates = $items->pluck('date_of_service')->map(fn ($d) => substr((string) $d, 0, 10))->all();

        $this->assertContains('2025-12-18', $dates);
        $this->assertContains('2025-12-22', $dates);

        // The bug: both rows previously carried today's invoice-creation date.
        $this->assertNotEquals($dates[0], $dates[1], 'each row must carry its own service date');
    }

    public function test_summary_items_carry_truck_driver_and_equipment(): void
    {
        $a = $this->childInvoice('JOB-A', '2025-12-18 09:00:00');

        $item = collect($this->summaryOf([$a, $this->childInvoice('JOB-B', '2025-12-22 07:00:00')])->values['summary_items'])
            ->firstWhere('job_no', 'JOB-A');

        $this->assertSame('69', (string) $item['truck_number']);
        $this->assertSame('Steerable', (string) $item['trailer_number']);
        $this->assertArrayHasKey('truck_driver_name', $item);
    }

    public function test_the_rendered_summary_shows_the_equipment_line(): void
    {
        $a = $this->childInvoice('JOB-A', '2025-12-18 09:00:00');
        $b = $this->childInvoice('JOB-B', '2025-12-22 07:00:00');

        $html = $this->actingAs($this->manager)
            ->get(route('my.invoices.print', $this->summaryOf([$a, $b])))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Truck 69', $html);
        $this->assertStringContainsString('Trailer Steerable', $html);
    }

    public function test_the_rendered_summary_shows_each_rows_own_service_date(): void
    {
        $a = $this->childInvoice('JOB-A', '2025-12-18 09:00:00');
        $b = $this->childInvoice('JOB-B', '2025-12-22 07:00:00');

        $html = $this->actingAs($this->manager)
            ->get(route('my.invoices.print', $this->summaryOf([$a, $b])))
            ->getContent();

        $this->assertStringContainsString('12/18/2025', $html);
        $this->assertStringContainsString('12/22/2025', $html);
    }

    public function test_a_canceled_child_job_is_marked_on_its_summary_row(): void
    {
        $a = $this->childInvoice('JOB-A', '2025-12-18 09:00:00');
        $b = $this->childInvoice('JOB-B', '2025-12-22 07:00:00');

        $a->job->update(['canceled_at' => now(), 'canceled_reason' => 'Customer Requested']);

        $html = $this->actingAs($this->manager)
            ->get(route('my.invoices.print', $this->summaryOf([$a, $b])))
            ->getContent();

        $this->assertStringContainsString('CANCELED', $html);
    }

    public function test_a_child_invoice_with_no_job_does_not_break_the_summary(): void
    {
        $a = $this->childInvoice('JOB-A', '2025-12-18 09:00:00');
        $b = $this->childInvoice('JOB-B', '2025-12-22 07:00:00');

        // An orphaned child: the job row is gone but the invoice remains.
        $b->job->forceDelete();

        $summary = $this->summaryOf([$a, $b->fresh()]);

        $this->actingAs($this->manager)
            ->get(route('my.invoices.print', $summary))
            ->assertOk();
    }
}
