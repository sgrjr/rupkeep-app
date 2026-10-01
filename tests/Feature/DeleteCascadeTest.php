<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Invoice;
use App\Models\InvoiceComment;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\Task;
use App\Models\User;
use App\Models\UserLog;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * TASK-471. user_logs.car_driver_id / vehicle_id / truck_driver_id and
 * invoice_comments.user_id were ON DELETE CASCADE, so force-deleting a
 * departed driver, a retired vehicle or a customer contact silently removed
 * the logs (and so the billing history) they appeared on. invoices had no
 * constraint to jobs at all. user:merge moved two of a dozen references and
 * asked nothing.
 */
class DeleteCascadeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Customer $customer;

    private PilotCarJob $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->job = PilotCarJob::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id]);
    }

    private function log(array $attributes): UserLog
    {
        return UserLog::create(array_merge([
            'job_id' => $this->job->id,
            'organization_id' => $this->organization->id,
            'vehicle_position' => 'lead',
            'approval_status' => 'confirmed',
            'start_mileage' => 100,
            'end_mileage' => 250,
            'tolls' => '12.50',
        ], $attributes));
    }

    public function test_force_deleting_a_driver_keeps_their_logs(): void
    {
        $driver = User::factory()->standard()->create(['organization_id' => $this->organization->id]);
        $log = $this->log(['car_driver_id' => $driver->id, 'completed_by_id' => $driver->id]);

        $driver->forceDelete();

        $fresh = $log->fresh();
        $this->assertNotNull($fresh, 'the log survives the driver');
        $this->assertNull($fresh->car_driver_id);
        $this->assertNull($fresh->completed_by_id);
        $this->assertEquals(12.5, (float) $fresh->tolls, 'the money on it is intact');
    }

    public function test_force_deleting_a_vehicle_or_a_contact_keeps_the_logs(): void
    {
        $vehicle = Vehicle::create(['name' => 'Car 9', 'organization_id' => $this->organization->id, 'odometer' => 1000, 'odometer_updated_at' => now()]);
        $contact = CustomerContact::create(['customer_id' => $this->customer->id, 'organization_id' => $this->organization->id, 'name' => 'Truck Driver']);
        $log = $this->log(['vehicle_id' => $vehicle->id, 'truck_driver_id' => $contact->id]);

        $vehicle->forceDelete();
        $contact->delete();

        $fresh = $log->fresh();
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->vehicle_id);
        $this->assertNull($fresh->truck_driver_id);
    }

    public function test_force_deleting_a_user_keeps_the_invoice_comments_they_wrote(): void
    {
        $invoice = Invoice::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id]);
        $author = User::factory()->asCustomer($this->customer)->create();
        $comment = InvoiceComment::create(['invoice_id' => $invoice->id, 'user_id' => $author->id, 'body' => 'Paid by check 4411.']);

        $author->forceDelete();

        $this->assertNotNull($comment->fresh());
        $this->assertNull($comment->fresh()->user_id);
    }

    public function test_force_deleting_a_job_keeps_its_invoices(): void
    {
        $invoice = $this->job->createInvoice();

        $this->job->forceDelete();

        $fresh = $invoice->fresh();
        $this->assertNotNull($fresh, 'the invoice outlives the job');
        $this->assertNull($fresh->pilot_car_job_id);
    }

    public function test_an_invoice_cannot_point_at_a_job_that_does_not_exist(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('invoices')->insert([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'pilot_car_job_id' => 999999,
            'values' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_user_merge_moves_every_reference_and_dry_run_moves_none(): void
    {
        $keep = User::factory()->standard()->create(['organization_id' => $this->organization->id]);
        $old = User::factory()->standard()->create(['organization_id' => $this->organization->id]);
        $manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);

        $log = $this->log(['car_driver_id' => $old->id, 'approved_by_id' => $old->id]);
        $vehicle = Vehicle::create(['name' => 'Car 1', 'organization_id' => $this->organization->id, 'user_id' => $old->id, 'odometer' => 1, 'odometer_updated_at' => now()]);
        $this->job->update(['default_driver_id' => $old->id]);
        $task = Task::create(['code' => 'TASK-900', 'title' => 'From the old account', 'type' => 'bug', 'priority' => 'low', 'status' => 'open', 'submitter_user_id' => $old->id, 'assignee_user_id' => $old->id]);
        $old->updatePushSubscription('https://push.example.test/old', 'k', 't');

        $this->artisan('user:merge', ['keep' => $keep->id, 'old' => $old->id, '--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame($old->id, $log->fresh()->car_driver_id, 'a dry run moves nothing');
        $this->assertNull($old->fresh()->deleted_at);

        $this->artisan('user:merge', ['keep' => $keep->id, 'old' => $old->id, '--force' => true])->assertSuccessful();

        $this->assertSame($keep->id, $log->fresh()->car_driver_id);
        $this->assertSame($keep->id, $log->fresh()->approved_by_id);
        $this->assertSame($keep->id, $vehicle->fresh()->user_id);
        $this->assertSame($keep->id, $this->job->fresh()->default_driver_id);
        $this->assertSame($keep->id, $task->fresh()->submitter_user_id);
        $this->assertSame($keep->id, $task->fresh()->assignee_user_id);
        $this->assertSame(0, $old->pushSubscriptions()->count(), 'the old device registration is dropped, not moved');
        $this->assertNotNull(User::withTrashed()->find($old->id)->deleted_at, 'the old account is soft-deleted, not destroyed');
        $this->assertNotNull($manager->fresh());
    }

    public function test_user_merge_refuses_accounts_from_different_organizations(): void
    {
        $keep = User::factory()->standard()->create(['organization_id' => $this->organization->id]);
        $old = User::factory()->standard()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->artisan('user:merge', ['keep' => $keep->id, 'old' => $old->id, '--force' => true])
            ->expectsOutputToContain('different organizations')
            ->assertFailed();

        $this->assertNull($old->fresh()->deleted_at);
    }
}
