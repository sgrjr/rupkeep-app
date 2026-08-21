<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-368: a staff-only debug dump of the record the invoice math runs on.
 */
class JobDebugPayloadTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private PilotCarJob $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $this->job = PilotCarJob::create([
            'job_no' => 'JOB-DEBUG',
            'customer_id' => $customer->id,
            'organization_id' => $this->organization->id,
            'pickup_address' => '1 Demo St',
            'delivery_address' => '2 Demo Ave',
            'rate_code' => 'flat_rate',
            'rate_value' => '575.00',
        ]);

        UserLog::create([
            'job_id' => $this->job->id,
            'organization_id' => $this->organization->id,
            'approval_status' => 'confirmed',
            'start_mileage' => 123,
            'end_mileage' => 400,
            'start_job_mileage' => 123,
            'end_job_mileage' => 324,
            'tolls' => '10.00',
            'hotel' => '125.00',
        ]);
    }

    public function test_manager_gets_the_expense_fields_behind_the_invoice_total(): void
    {
        $manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);

        $payload = Livewire::actingAs($manager)
            ->test('show-pilot-car-job', ['job' => $this->job->id])
            ->instance()
            ->debugPayload();

        $this->assertSame('JOB-DEBUG', $payload['job']['job_no']);
        $this->assertSame('flat_rate', $payload['job']['rate_code']);

        $this->assertSame(10.0, (float) $payload['logs'][0]['tolls']);
        $this->assertSame(125.0, (float) $payload['logs'][0]['hotel']);

        // The whole point: the hotel that was disappearing into Pilot Car
        // Service is readable here, alongside the total it feeds.
        $this->assertSame(710.0, (float) $payload['computed_invoice_values']['total']);
        $this->assertSame(125.0, (float) $payload['computed_invoice_values']['hotel']);
    }

    public function test_it_includes_stored_invoice_snapshots_for_drift_comparison(): void
    {
        $manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
        $invoice = $this->job->createInvoice();

        $payload = Livewire::actingAs($manager)
            ->test('show-pilot-car-job', ['job' => $this->job->id])
            ->instance()
            ->debugPayload();

        $this->assertCount(1, $payload['existing_invoices']);
        $this->assertSame($invoice->id, $payload['existing_invoices'][0]['id']);
        $this->assertSame(710.0, (float) $payload['existing_invoices'][0]['stored_values']['total']);
    }

    public function test_a_driver_cannot_dump_the_payload(): void
    {
        $driver = User::factory()->standard()->create(['organization_id' => $this->organization->id]);

        Livewire::actingAs($driver)
            ->test('show-pilot-car-job', ['job' => $this->job->id])
            ->call('debugPayload')
            ->assertForbidden();
    }

    public function test_the_button_is_hidden_from_users_who_cannot_use_it(): void
    {
        $driver = User::factory()->standard()->create(['organization_id' => $this->organization->id]);

        Livewire::actingAs($driver)
            ->test('show-pilot-car-job', ['job' => $this->job->id])
            ->assertDontSee('Log job JSON to console');
    }

    public function test_the_button_is_shown_to_a_manager(): void
    {
        $manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);

        Livewire::actingAs($manager)
            ->test('show-pilot-car-job', ['job' => $this->job->id])
            ->assertSee('Log job JSON to console');
    }
}
