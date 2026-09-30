<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-472. Total Revenue counted CSV-imported invoices only, so nothing
 * invoiced in the app after go-live ever moved it; "Unpaid Invoices"
 * counted summaries and children while "Outstanding" did not; "Users"
 * counted customer-portal accounts as staff; and every render loaded every
 * job and every invoice into memory and wrote a diagnostic log line.
 */
class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $manager;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function job(array $attributes = []): PilotCarJob
    {
        return PilotCarJob::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ], $attributes));
    }

    private function invoice(float $total, array $attributes = []): Invoice
    {
        $factory = Invoice::factory();

        return $factory->create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'pilot_car_job_id' => $this->job()->id,
            'values' => ['total' => $total, 'import_source' => 'calculated'],
        ], $attributes));
    }

    private function stats(): object
    {
        return Livewire::actingAs($this->manager)->test(Dashboard::class)->viewData('managerStats');
    }

    public function test_revenue_and_outstanding_read_the_same_single_invoices_whatever_their_source(): void
    {
        $this->invoice(100, ['paid_in_full' => true, 'values' => ['total' => 100, 'import_source' => 'csv']]);
        $this->invoice(250, ['paid_in_full' => false]);                   // generated in the app: used to be ignored
        $this->invoice(75, ['paid_in_full' => false, 'values' => ['total' => 75]]); // no source recorded at all
        $this->invoice(999, ['status' => Invoice::STATUS_VOID, 'paid_in_full' => false]); // void: not a bill

        // A summary and its child carry the same money twice.
        $summary = $this->invoice(400, ['invoice_type' => 'summary', 'paid_in_full' => false]);
        $this->invoice(400, ['parent_invoice_id' => $summary->id, 'paid_in_full' => false]);

        $stats = $this->stats();

        $this->assertSame(425.0, $stats->total_revenue, '100 + 250 + 75: every non-void single invoice, whatever the source');
        $this->assertSame(325.0, $stats->unpaid_amount, '250 + 75');
        $this->assertSame(2, $stats->unpaid_invoices, 'the same two invoices the outstanding amount is made of');
        $this->assertSame(5, $stats->total_invoices, 'everything that is not void');
    }

    public function test_job_counts_follow_the_status_rule(): void
    {
        $this->job();                                                     // active
        $this->job(['canceled_at' => now()]);                             // cancelled
        $this->job(['job_no' => null]);                                   // active, missing number
        $billed = $this->job();
        Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'pilot_car_job_id' => $billed->id,
            'values' => ['total' => 50],
        ]);
        $voided = $this->job();
        Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'pilot_car_job_id' => $voided->id,
            'status' => Invoice::STATUS_VOID,
            'values' => ['total' => 50],
        ]);

        $stats = $this->stats();

        $this->assertSame(5, $stats->total_jobs);
        $this->assertSame(1, $stats->cancelled_jobs);
        $this->assertSame(1, $stats->completed_jobs, 'a voided invoice does not complete a job');
        $this->assertSame(3, $stats->active_jobs);
        $this->assertSame(1, $stats->missing_job_no);
    }

    public function test_the_users_card_counts_staff_not_portal_accounts(): void
    {
        User::factory()->standard()->create(['organization_id' => $this->organization->id]);
        User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        User::factory()->asCustomer($this->customer)->create();
        User::factory()->asCustomer($this->customer)->create();

        $cards = Livewire::actingAs($this->manager)->test(Dashboard::class)->viewData('cards');
        $users = collect($cards)->firstWhere('title', 'Users');

        // The organization factory also creates its owner (an admin).
        $this->assertSame(4, $users->count, 'the owner, the manager, the driver and the admin; not the two portal accounts');
    }

    public function test_the_render_neither_logs_diagnostics_nor_loads_every_invoice(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->invoice(10 + $i);
        }
        Log::spy();

        DB::enableQueryLog();
        Livewire::actingAs($this->manager)->test(Dashboard::class);
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        Log::shouldNotHaveReceived('info', [\Mockery::pattern('/Revenue calculation diagnostics/'), \Mockery::any()]);

        $loadsAllInvoices = $queries->contains(fn ($sql) => preg_match('/^select \* from "invoices" where "organization_id" = \? and "status" != \?$/i', $sql) === 1);
        $this->assertFalse($loadsAllInvoices, 'the totals are summed in the database');
        $this->assertTrue($queries->contains(fn ($sql) => stripos($sql, 'sum(cast(') !== false), 'a SUM over the JSON total');
    }

    public function test_the_customer_list_counts_jobs_without_loading_them(): void
    {
        $this->job();
        $this->job();
        Customer::factory()->create(['organization_id' => $this->organization->id]);

        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        foreach (['customers.index', 'my.customers.index'] as $route) {
            $response = $this->actingAs($admin)->get(route($route))->assertOk();

            $customers = $response->viewData('allCustomers');
            $this->assertSame(1.0, (float) $response->viewData('averageJobsPerCustomer'), "$route: 2 jobs over 2 customers");
            $this->assertFalse($customers->first()->relationLoaded('jobs'), "$route: jobs are counted, not loaded");
            $this->assertSame(2, $customers->firstWhere('id', $this->customer->id)->jobs_count);
        }
    }
}
