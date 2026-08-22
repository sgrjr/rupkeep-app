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
 * There was no staff invoice list. Every other /my/invoices route is
 * {invoice}-scoped, so an invoice could only be opened by already knowing its
 * id -- in practice by arriving from its job. Customers have had
 * /portal/invoices all along; the people who issue the invoices had nothing.
 *
 * That held up until an invoice had no job to arrive from. Casco Bay's
 * dashboard reports 1,023 invoices and $419k against two jobs, and not one of
 * those invoices could be opened.
 */
class InvoiceIndexTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private Customer $customer;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->organization = Organization::factory()->create();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
    }

    private function invoice(array $values, ?PilotCarJob $job = null, array $attributes = []): Invoice
    {
        return Invoice::create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'pilot_car_job_id' => $job?->id,
            'values' => $values,
        ], $attributes));
    }

    private function job(string $jobNo): PilotCarJob
    {
        return PilotCarJob::create([
            'job_no' => $jobNo,
            'customer_id' => $this->customer->id,
            'organization_id' => $this->organization->id,
            'load_no' => 'LOAD-'.$jobNo,
            'pickup_address' => 'Gorham, ME',
            'delivery_address' => 'Boston, MA',
            'rate_code' => 'flat_rate',
            'rate_value' => '575.00',
        ]);
    }

    /**
     * The whole reason this screen exists. An invoice whose job row is gone must
     * still be listed and still be openable -- it is a bill somebody owes.
     */
    public function test_an_invoice_whose_job_was_deleted_is_still_listed(): void
    {
        $job = $this->job('JOB-GONE');
        $invoice = $this->invoice(['total' => 1200, 'job_no' => 'JOB-GONE'], $job);

        $job->forceDelete();

        $this->actingAs($this->manager)
            ->get(route('my.invoices.index'))
            ->assertOk()
            ->assertSee((string) $invoice->fresh()->invoice_number)
            ->assertSee(route('my.invoices.edit', ['invoice' => $invoice->id]));
    }

    public function test_orphans_can_be_isolated(): void
    {
        $job = $this->job('JOB-KEPT');
        $attached = $this->invoice(['total' => 100, 'job_no' => 'JOB-KEPT'], $job);

        $orphan = $this->invoice(['total' => 200, 'job_no' => 'JOB-LOST']);

        $response = $this->actingAs($this->manager)
            ->get(route('my.invoices.index', ['orphaned' => '1']))
            ->assertOk();

        $response->assertSee((string) $orphan->fresh()->invoice_number);
        $response->assertDontSee((string) $attached->fresh()->invoice_number);
    }

    /**
     * The figure is what the filter selects, not what fits on the page --
     * otherwise it cannot be reconciled against the dashboard.
     */
    public function test_the_total_covers_the_whole_filter_not_the_page(): void
    {
        foreach (range(1, 30) as $i) {
            $this->invoice(['total' => 100]);
        }

        $response = $this->actingAs($this->manager)->get(route('my.invoices.index'));

        $response->assertOk();
        $this->assertSame(30, $response->viewData('listedCount'));
        $this->assertEqualsWithDelta(3000.0, $response->viewData('listedTotal'), 0.01);

        // Page size is 25, so the total above is not simply the sum of the rows
        // rendered.
        $this->assertCount(25, $response->viewData('invoices')->items());
    }

    public function test_unpaid_can_be_filtered(): void
    {
        $paid = $this->invoice(['total' => 100], null, ['paid_in_full' => true]);
        $unpaid = $this->invoice(['total' => 250], null, ['paid_in_full' => false]);

        $response = $this->actingAs($this->manager)
            ->get(route('my.invoices.index', ['paid' => 'no']))
            ->assertOk();

        $response->assertSee((string) $unpaid->fresh()->invoice_number);
        $response->assertDontSee((string) $paid->fresh()->invoice_number);
    }

    /**
     * Searching has to reach the job number recorded ON the invoice, because for
     * the invoices this screen exists to rescue there is no job row to join to.
     */
    public function test_search_finds_an_orphan_by_its_recorded_job_number(): void
    {
        $wanted = $this->invoice(['total' => 500, 'job_no' => 'JOB-NEEDLE']);
        $other = $this->invoice(['total' => 500, 'job_no' => 'JOB-HAYSTACK']);

        $response = $this->actingAs($this->manager)
            ->get(route('my.invoices.index', ['q' => 'JOB-NEEDLE']))
            ->assertOk();

        $response->assertSee((string) $wanted->fresh()->invoice_number);
        $response->assertDontSee((string) $other->fresh()->invoice_number);
    }

    public function test_search_finds_by_customer_name(): void
    {
        $other = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Rosebudz LLC',
        ]);

        $mine = $this->invoice(['total' => 100]);
        $theirs = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $other->id,
            'values' => ['total' => 900],
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('my.invoices.index', ['q' => 'Rosebudz']))
            ->assertOk();

        $response->assertSee((string) $theirs->fresh()->invoice_number);
        $response->assertDontSee((string) $mine->fresh()->invoice_number);
    }

    public function test_another_organizations_invoices_are_not_listed(): void
    {
        $mine = $this->invoice(['total' => 100]);

        $otherOrg = Organization::factory()->create();
        $otherCustomer = Customer::factory()->create(['organization_id' => $otherOrg->id]);
        $theirs = Invoice::create([
            'organization_id' => $otherOrg->id,
            'customer_id' => $otherCustomer->id,
            'values' => ['total' => 5000],
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('my.invoices.index'))
            ->assertOk();

        $response->assertSee((string) $mine->fresh()->invoice_number);
        $response->assertDontSee((string) $theirs->fresh()->invoice_number);
    }

    public function test_a_driver_cannot_open_the_invoice_list(): void
    {
        $driver = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($driver)
            ->get(route('my.invoices.index'))
            ->assertForbidden();
    }
}
