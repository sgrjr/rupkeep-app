<?php

namespace Tests\Feature;

use App\Livewire\OrganizationShow;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JobInvoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Delete all Invoices" on the Reset Organization panel.
 *
 * Invoices hang off the organization rather than off their job, so the existing
 * "Delete all Jobs" leaves every invoice standing. Casco Bay emptied its jobs
 * deliberately and was left with 1,020 invoices pointing at rows that no longer
 * existed, with no way to clear them (TASK-389).
 */
class OrganizationResetInvoicesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->organization = Organization::factory()->create();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function admin(?Organization $organization = null): User
    {
        return User::factory()->admin()->create([
            'organization_id' => ($organization ?? $this->organization)->id,
        ]);
    }

    private function invoice(array $attributes = []): Invoice
    {
        return Invoice::create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'values' => ['total' => 100],
        ], $attributes));
    }

    public function test_an_admin_can_empty_their_own_organizations_invoices(): void
    {
        $this->invoice();
        $this->invoice();

        Livewire::actingAs($this->admin())
            ->test(OrganizationShow::class, ['organization' => $this->organization->id])
            ->call('deleteInvoices');

        $this->assertSame(0, Invoice::withTrashed()->where('organization_id', $this->organization->id)->count());
    }

    /**
     * The reason the action exists: an invoice whose job is already gone is
     * exactly the row that needs clearing, so it must not be skipped.
     */
    public function test_invoices_whose_jobs_are_already_gone_are_cleared(): void
    {
        $job = PilotCarJob::create([
            'job_no' => 'JOB-1',
            'customer_id' => $this->customer->id,
            'organization_id' => $this->organization->id,
            'load_no' => 'LOAD-1',
            'pickup_address' => 'Gorham, ME',
            'delivery_address' => 'Boston, MA',
            'rate_code' => 'flat_rate',
            'rate_value' => '575.00',
        ]);

        $this->invoice(['pilot_car_job_id' => $job->id]);

        $job->forceDelete();

        Livewire::actingAs($this->admin())
            ->test(OrganizationShow::class, ['organization' => $this->organization->id])
            ->call('deleteInvoices');

        $this->assertSame(0, Invoice::withTrashed()->where('organization_id', $this->organization->id)->count());
    }

    public function test_archived_invoices_go_too(): void
    {
        $invoice = $this->invoice();
        $invoice->delete();

        $this->assertSame(1, Invoice::onlyTrashed()->where('organization_id', $this->organization->id)->count());

        Livewire::actingAs($this->admin())
            ->test(OrganizationShow::class, ['organization' => $this->organization->id])
            ->call('deleteInvoices');

        $this->assertSame(0, Invoice::withTrashed()->where('organization_id', $this->organization->id)->count());
    }

    /**
     * Summaries carry children and pivot rows. Leaving the pivot behind would
     * swap one kind of dangling reference for another.
     */
    public function test_summaries_children_and_their_pivot_rows_are_cleared(): void
    {
        $summary = $this->invoice(['invoice_type' => 'summary']);
        $child = $this->invoice(['parent_invoice_id' => $summary->id]);

        JobInvoice::create([
            'invoice_id' => $summary->id,
            'pilot_car_job_id' => PilotCarJob::create([
                'job_no' => 'JOB-2',
                'customer_id' => $this->customer->id,
                'organization_id' => $this->organization->id,
                'load_no' => 'LOAD-2',
                'pickup_address' => 'Gorham, ME',
                'delivery_address' => 'Boston, MA',
                'rate_code' => 'flat_rate',
                'rate_value' => '575.00',
            ])->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(OrganizationShow::class, ['organization' => $this->organization->id])
            ->call('deleteInvoices');

        $this->assertSame(0, Invoice::withTrashed()->where('organization_id', $this->organization->id)->count());
        $this->assertSame(0, JobInvoice::whereIn('invoice_id', [$summary->id, $child->id])->count());
    }

    /**
     * Jobs cache their invoice state in their own columns. Left set, a job would
     * claim it is invoiced and paid with nothing behind it -- the same dangling
     * reference this action exists to clear.
     */
    public function test_jobs_stop_claiming_they_are_invoiced(): void
    {
        $job = PilotCarJob::create([
            'job_no' => 'JOB-3',
            'customer_id' => $this->customer->id,
            'organization_id' => $this->organization->id,
            'load_no' => 'LOAD-3',
            'pickup_address' => 'Gorham, ME',
            'delivery_address' => 'Boston, MA',
            'rate_code' => 'flat_rate',
            'rate_value' => '575.00',
            'invoice_no' => 'INV-3',
            'invoice_paid' => 1,
        ]);

        $this->invoice(['pilot_car_job_id' => $job->id]);

        Livewire::actingAs($this->admin())
            ->test(OrganizationShow::class, ['organization' => $this->organization->id])
            ->call('deleteInvoices');

        $job->refresh();

        $this->assertNull($job->invoice_no);
        $this->assertEquals(0, $job->invoice_paid);
    }

    public function test_another_organizations_invoices_are_untouched(): void
    {
        $mine = $this->invoice();

        $otherOrg = Organization::factory()->create();
        $otherCustomer = Customer::factory()->create(['organization_id' => $otherOrg->id]);
        $theirs = Invoice::create([
            'organization_id' => $otherOrg->id,
            'customer_id' => $otherCustomer->id,
            'values' => ['total' => 900],
        ]);

        Livewire::actingAs($this->admin())
            ->test(OrganizationShow::class, ['organization' => $this->organization->id])
            ->call('deleteInvoices');

        $this->assertNull(Invoice::find($mine->id));
        $this->assertNotNull(Invoice::find($theirs->id));
    }

    public function test_a_super_user_can_empty_any_organization(): void
    {
        $this->invoice();

        $otherOrg = Organization::factory()->create();
        $super = User::factory()->create(['organization_id' => $otherOrg->id, 'is_super' => true]);

        Livewire::actingAs($super)
            ->test(OrganizationShow::class, ['organization' => $this->organization->id])
            ->call('deleteInvoices');

        $this->assertSame(0, Invoice::withTrashed()->where('organization_id', $this->organization->id)->count());
    }

    /**
     * An outsider never gets as far as the button: mount() authorizes 'view' on
     * the organization, so the page itself is refused. The in-method check is
     * the second line, exercised by the manager test below -- a manager CAN view
     * their own organization, so they mount fine and are stopped by the guard.
     */
    public function test_an_admin_of_another_organization_cannot_even_open_the_page(): void
    {
        $this->invoice();

        $otherOrg = Organization::factory()->create();
        $outsider = $this->admin($otherOrg);

        Livewire::actingAs($outsider)
            ->test(OrganizationShow::class, ['organization' => $this->organization->id])
            ->assertForbidden();

        $this->assertSame(1, Invoice::where('organization_id', $this->organization->id)->count());
    }

    public function test_a_manager_is_refused(): void
    {
        $this->invoice();

        $manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);

        Livewire::actingAs($manager)
            ->test(OrganizationShow::class, ['organization' => $this->organization->id])
            ->call('deleteInvoices')
            ->assertForbidden();

        $this->assertSame(1, Invoice::where('organization_id', $this->organization->id)->count());
    }
}
