<?php

namespace Tests\Feature;

use App\Livewire\ShowPilotCarJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-447, second half. invoiceValues() iterated every log on the job, so a
 * denied assignment's miles, wait and expenses were billed to the customer
 * and it counted as a car; a job with no log at all still produced an
 * invoice; and nothing told the user a log was still pending or unfinished.
 */
class InvoiceLogEligibilityTest extends TestCase
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

    private function job(string $no = 'JOB-447', array $attributes = []): PilotCarJob
    {
        return PilotCarJob::create(array_merge([
            'job_no' => $no,
            'customer_id' => $this->customer->id,
            'organization_id' => $this->organization->id,
            'pickup_address' => 'Gorham, ME',
            'delivery_address' => 'Boston, MA',
            'rate_code' => 'per_mile_rate_2_00',
            'rate_value' => '2.00',
        ], $attributes));
    }

    private function log(PilotCarJob $job, string $approval, array $attributes = []): UserLog
    {
        return UserLog::create(array_merge([
            'job_id' => $job->id,
            'organization_id' => $this->organization->id,
            'approval_status' => $approval,
            'billable_miles' => 100,
            'wait_time_hours' => 1,
            'started_at' => now()->subDay(),
            'ended_at' => now(),
            'completed_at' => now(),
        ], $attributes));
    }

    public function test_a_denied_log_is_not_billed_and_is_not_a_car(): void
    {
        $job = $this->job();
        $this->log($job, 'confirmed', ['billable_miles' => 100, 'wait_time_hours' => 1]);
        $this->log($job, 'denied', ['billable_miles' => 50, 'wait_time_hours' => 2]);

        $values = $job->fresh()->invoiceValues()['values'];

        $this->assertSame(1, $values['cars_count'], 'a denied assignment is not a car on the job');
        $this->assertEquals(100.0, (float) $values['billable_miles']);
        $this->assertEquals(1.0, (float) $values['wait_time_hours']);

        $this->assertSame(
            ['billable' => 1, 'denied' => 1, 'pending' => 0, 'incomplete' => 0],
            $job->fresh()->invoiceReadiness()
        );
    }

    public function test_a_job_with_no_billable_log_gets_no_invoice_and_no_button(): void
    {
        $job = $this->job();

        Livewire::actingAs($this->manager)
            ->test(ShowPilotCarJob::class, ['job' => $job->id])
            ->assertDontSee('Create Draft Invoice')
            ->assertSee('No driver log yet')
            ->call('generateInvoice')
            ->assertSee('has no log to bill');

        $this->assertSame(0, Invoice::count());

        // All denied is the same as none.
        $this->log($job, 'denied');

        Livewire::actingAs($this->manager)
            ->test(ShowPilotCarJob::class, ['job' => $job->id])
            ->assertDontSee('Create Draft Invoice')
            ->assertSee('Every log on this job was denied');
    }

    public function test_a_canceled_job_is_invoiced_without_a_log(): void
    {
        // The cancellation charge needs no driver log.
        $job = $this->job('JOB-447-CANCEL', ['canceled_at' => now(), 'scheduled_pickup_at' => now()->addHours(2)]);

        Livewire::actingAs($this->manager)
            ->test(ShowPilotCarJob::class, ['job' => $job->id])
            ->assertSee('Create Draft Invoice')
            ->call('generateInvoice');

        $this->assertSame(1, Invoice::where('pilot_car_job_id', $job->id)->count());
    }

    public function test_pending_and_unfinished_logs_bill_but_only_after_a_warning(): void
    {
        $job = $this->job();
        $this->log($job, 'pending');
        $this->log($job, 'confirmed', ['completed_at' => null]);

        $this->assertSame(
            ['billable' => 2, 'denied' => 0, 'pending' => 1, 'incomplete' => 1],
            $job->fresh()->invoiceReadiness()
        );

        $component = Livewire::actingAs($this->manager)
            ->test(ShowPilotCarJob::class, ['job' => $job->id])
            ->assertSee('Create Draft Invoice')
            ->assertSee('1 log is still pending')
            ->assertSee('1 log has not been marked complete');

        $this->assertMatchesRegularExpression('/wire:confirm="[^"]*Build the invoice anyway\?/', $component->html(), 'the button asks first');

        // Confirmed in the browser, the invoice is built from both.
        $component->call('generateInvoice');

        $invoice = Invoice::where('pilot_car_job_id', $job->id)->firstOrFail();
        $this->assertSame(2, $invoice->values['cars_count']);
    }

    public function test_a_job_whose_logs_are_all_confirmed_and_complete_asks_nothing(): void
    {
        $job = $this->job();
        $this->log($job, 'confirmed');

        $html = Livewire::actingAs($this->manager)
            ->test(ShowPilotCarJob::class, ['job' => $job->id])
            ->assertSee('Create Draft Invoice')
            ->html();

        $this->assertStringNotContainsString('wire:confirm', $html);
    }
}
