<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use App\Models\Vehicle;
use App\Services\SummaryInvoiceValues;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-381 — a summary invoice must not go stale when a child changes.
 *
 * buildSummaryValues() ran only at creation (both call sites were
 * Invoice::create), so a summary's total, billable_miles and summary_items
 * were a snapshot taken the moment it was cut. Edit a child's total, add an
 * extra charge to one of its logs, or cancel its job, and the summary kept
 * printing and billing the old figure while the child showed the new one --
 * the customer-facing cover sheet and the child invoice disagreed.
 *
 * The resolution has two halves, per the product decision on the task:
 * a summary recomputes itself automatically, UNLESS its total was set by hand,
 * in which case it is left frozen and flagged for an explicit rebuild.
 */
class SummaryInvoiceStaleTotalTest extends TestCase
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
            'scheduled_pickup_at' => '2025-12-18 09:00:00',
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

    private function summaryOf(array $children): Invoice
    {
        $this->actingAs($this->admin)
            ->post(route('my.invoices.create-summary'), [
                'invoice_ids' => collect($children)->pluck('id')->all(),
            ]);

        return Invoice::where('invoice_type', 'summary')->latest('id')->firstOrFail();
    }

    /** Save a new total onto a child through the real edit form. */
    private function setChildTotal(Invoice $child, float $total): void
    {
        $this->actingAs($this->admin)
            ->put(route('my.invoices.update', $child), [
                'paid_in_full' => 'no',
                'values' => ['total' => (string) $total],
            ])
            ->assertRedirect(route('my.invoices.edit', $child));
    }

    public function test_a_summary_follows_its_child_when_that_childs_total_changes(): void
    {
        $a = $this->childInvoice('JOB-381-A');
        $b = $this->childInvoice('JOB-381-B');
        $summary = $this->summaryOf([$a, $b]);

        $originalTotal = (float) data_get($summary->values, 'total');
        $this->assertGreaterThan(0, $originalTotal);

        $childTotal = (float) data_get($a->fresh()->values, 'total');
        $this->setChildTotal($a, $childTotal + 250);

        $summary->refresh();

        $this->assertEqualsWithDelta(
            $originalTotal + 250,
            (float) data_get($summary->values, 'total'),
            0.01,
            'The summary must bill what its children now add up to.'
        );
    }

    public function test_a_summary_line_row_follows_its_child(): void
    {
        $a = $this->childInvoice('JOB-381-C');
        $b = $this->childInvoice('JOB-381-D');
        $summary = $this->summaryOf([$a, $b]);

        $this->setChildTotal($a, 1234.56);

        $summary->refresh();

        $row = collect(data_get($summary->values, 'summary_items'))
            ->firstWhere('invoice_id', $a->id);

        $this->assertNotNull($row, 'The child must still have a row on the cover sheet.');
        $this->assertEqualsWithDelta(1234.56, (float) $row['total'], 0.01);
    }

    public function test_billable_miles_are_resummed_too(): void
    {
        $a = $this->childInvoice('JOB-381-E');
        $b = $this->childInvoice('JOB-381-F');
        $summary = $this->summaryOf([$a, $b]);

        $before = (float) data_get($summary->values, 'billable_miles');

        $this->actingAs($this->admin)
            ->put(route('my.invoices.update', $a), [
                'paid_in_full' => 'no',
                'values' => ['billable_miles' => '500'],
            ]);

        $summary->refresh();

        $this->assertNotEqualsWithDelta(
            $before,
            (float) data_get($summary->values, 'billable_miles'),
            0.01
        );
        $this->assertEqualsWithDelta(
            500 + (float) data_get($b->fresh()->values, 'billable_miles'),
            (float) data_get($summary->values, 'billable_miles'),
            0.01
        );
    }

    public function test_a_hand_set_summary_total_is_not_silently_overwritten(): void
    {
        $a = $this->childInvoice('JOB-381-G');
        $b = $this->childInvoice('JOB-381-H');
        $summary = $this->summaryOf([$a, $b]);

        // The admin deliberately bills a round number rather than the sum.
        $this->actingAs($this->admin)
            ->put(route('my.invoices.update', $summary), [
                'paid_in_full' => 'no',
                'values' => ['total' => '2000'],
            ]);

        $summary->refresh();
        $this->assertTrue((bool) data_get($summary->values, SummaryInvoiceValues::OVERRIDE_KEY));

        $this->setChildTotal($a, 999.99);

        $summary->refresh();

        $this->assertEqualsWithDelta(
            2000.0,
            (float) data_get($summary->values, 'total'),
            0.01,
            'A total the admin set by hand must survive a child edit.'
        );
    }

    public function test_a_hand_set_summary_is_flagged_stale_when_its_children_drift(): void
    {
        $a = $this->childInvoice('JOB-381-I');
        $b = $this->childInvoice('JOB-381-J');
        $summary = $this->summaryOf([$a, $b]);

        $this->actingAs($this->admin)
            ->put(route('my.invoices.update', $summary), [
                'paid_in_full' => 'no',
                'values' => ['total' => '2000'],
            ]);

        $this->setChildTotal($a, 100);

        $summary->refresh();

        $expected = 100 + (float) data_get($b->fresh()->values, 'total');

        $this->assertEqualsWithDelta(
            $expected,
            (float) data_get($summary->values, SummaryInvoiceValues::STALE_KEY),
            0.01,
            'The stale marker must carry what the children DO sum to.'
        );
    }

    public function test_the_edit_page_offers_a_rebuild_when_the_summary_is_stale(): void
    {
        $a = $this->childInvoice('JOB-381-K');
        $b = $this->childInvoice('JOB-381-L');
        $summary = $this->summaryOf([$a, $b]);

        $this->actingAs($this->admin)->get(route('my.invoices.edit', $summary))
            ->assertOk()
            ->assertDontSee('This summary is out of date');

        $this->actingAs($this->admin)
            ->put(route('my.invoices.update', $summary), [
                'paid_in_full' => 'no',
                'values' => ['total' => '2000'],
            ]);

        $this->setChildTotal($a, 100);

        $this->actingAs($this->admin)->get(route('my.invoices.edit', $summary))
            ->assertOk()
            ->assertSee('This summary is out of date')
            ->assertSee(route('my.invoices.regenerate-summary', ['invoice' => $summary->id]));
    }

    public function test_rebuilding_replaces_the_hand_set_total_and_clears_the_flags(): void
    {
        $a = $this->childInvoice('JOB-381-M');
        $b = $this->childInvoice('JOB-381-N');
        $summary = $this->summaryOf([$a, $b]);

        $this->actingAs($this->admin)
            ->put(route('my.invoices.update', $summary), [
                'paid_in_full' => 'no',
                'values' => ['total' => '2000'],
            ]);

        $this->setChildTotal($a, 100);

        $this->actingAs($this->admin)
            ->post(route('my.invoices.regenerate-summary', $summary))
            ->assertRedirect(route('my.invoices.edit', $summary));

        $summary->refresh();

        $expected = 100 + (float) data_get($b->fresh()->values, 'total');

        $this->assertEqualsWithDelta($expected, (float) data_get($summary->values, 'total'), 0.01);
        $this->assertArrayNotHasKey(SummaryInvoiceValues::OVERRIDE_KEY, $summary->values);
        $this->assertArrayNotHasKey(SummaryInvoiceValues::STALE_KEY, $summary->values);
    }

    public function test_matching_the_child_sum_by_hand_releases_the_override(): void
    {
        $a = $this->childInvoice('JOB-381-O');
        $b = $this->childInvoice('JOB-381-P');
        $summary = $this->summaryOf([$a, $b]);

        $this->actingAs($this->admin)
            ->put(route('my.invoices.update', $summary), [
                'paid_in_full' => 'no',
                'values' => ['total' => '2000'],
            ]);

        $summary->refresh();
        $this->assertTrue((bool) data_get($summary->values, SummaryInvoiceValues::OVERRIDE_KEY));

        // Typing the real sum back in is not an override, it is agreement.
        $childSum = (float) data_get($a->fresh()->values, 'total')
            + (float) data_get($b->fresh()->values, 'total');

        $this->actingAs($this->admin)
            ->put(route('my.invoices.update', $summary), [
                'paid_in_full' => 'no',
                'values' => ['total' => (string) $childSum],
            ]);

        $summary->refresh();

        $this->assertArrayNotHasKey(SummaryInvoiceValues::OVERRIDE_KEY, $summary->values);

        // And it now tracks its children again.
        $this->setChildTotal($a, 100);
        $summary->refresh();

        $this->assertEqualsWithDelta(
            100 + (float) data_get($b->fresh()->values, 'total'),
            (float) data_get($summary->values, 'total'),
            0.01
        );
    }

    public function test_a_summary_that_lost_all_its_children_keeps_its_last_figures(): void
    {
        $a = $this->childInvoice('JOB-381-Q');
        $b = $this->childInvoice('JOB-381-R');
        $summary = $this->summaryOf([$a, $b]);

        $storedTotal = (float) data_get($summary->values, 'total');

        Invoice::whereIn('id', [$a->id, $b->id])->update(['parent_invoice_id' => null]);

        $this->assertFalse(SummaryInvoiceValues::refresh($summary->fresh()));

        $this->assertEqualsWithDelta(
            $storedTotal,
            (float) data_get($summary->fresh()->values, 'total'),
            0.01,
            'Zeroing a document that may already be in a customer hands is worse than leaving it.'
        );
    }

    public function test_a_single_invoice_is_never_treated_as_a_summary(): void
    {
        $a = $this->childInvoice('JOB-381-S');

        $this->assertFalse(SummaryInvoiceValues::refresh($a));
        $this->assertFalse(SummaryInvoiceValues::regenerate($a));

        $this->actingAs($this->admin)
            ->post(route('my.invoices.regenerate-summary', $a))
            ->assertRedirect(route('my.invoices.edit', $a));

        $this->assertArrayNotHasKey(SummaryInvoiceValues::OVERRIDE_KEY, $a->fresh()->values ?? []);
    }
}
