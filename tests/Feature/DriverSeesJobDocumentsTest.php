<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-363: a permit uploaded against the job was invisible to the driver.
 * Their log page listed only $log->attachments (their own receipt uploads),
 * never $log->job->attachments, so the document they needed on the road was
 * only reachable from the office-side job page.
 */
class DriverSeesJobDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private PilotCarJob $job;
    private User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $this->job = PilotCarJob::create([
            'job_no' => 'JOB-PERMIT',
            'customer_id' => $customer->id,
            'organization_id' => $this->organization->id,
            'pickup_address' => '1 Demo St',
            'delivery_address' => '2 Demo Ave',
            'rate_code' => 'lead_chase_per_mile',
            'rate_value' => '2.00',
        ]);

        $this->driver = User::factory()->standard()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    private function log(): UserLog
    {
        return UserLog::create([
            'job_id' => $this->job->id,
            'organization_id' => $this->organization->id,
            'car_driver_id' => $this->driver->id,
            'approval_status' => 'confirmed',
        ]);
    }

    private function jobAttachment(string $name = 'oversize-permit.pdf'): Attachment
    {
        return Attachment::create([
            'attachable_id' => $this->job->id,
            'attachable_type' => PilotCarJob::class,
            'location' => 'jobs/attachments_' . $this->job->id . '/' . $name,
            'file_name' => $name,
            'organization_id' => $this->organization->id,
            'is_public' => false,
        ]);
    }

    public function test_driver_sees_a_permit_attached_to_the_job(): void
    {
        $this->jobAttachment();

        Livewire::actingAs($this->driver)
            ->test('edit-user-log', ['log' => $this->log()])
            ->assertSee('oversize-permit.pdf')
            ->assertSee('Job Documents');
    }

    public function test_the_permit_links_to_the_download_route(): void
    {
        $attachment = $this->jobAttachment();

        Livewire::actingAs($this->driver)
            ->test('edit-user-log', ['log' => $this->log()])
            ->assertSee(route('attachments.download', ['attachment' => $attachment->id]), false);
    }

    public function test_job_documents_are_listed_separately_from_the_drivers_own_uploads(): void
    {
        $this->jobAttachment();

        $log = $this->log();

        Attachment::create([
            'attachable_id' => $log->id,
            'attachable_type' => UserLog::class,
            'location' => 'jobs/attachments_' . $this->job->id . '/gas-receipt.jpg',
            'file_name' => 'gas-receipt.jpg',
            'organization_id' => $this->organization->id,
            'is_public' => false,
        ]);

        Livewire::actingAs($this->driver)
            ->test('edit-user-log', ['log' => $log])
            ->assertSee('oversize-permit.pdf')
            ->assertSee('gas-receipt.jpg');
    }

    public function test_the_empty_state_no_longer_claims_there_is_nothing_when_the_job_has_documents(): void
    {
        $this->jobAttachment();

        Livewire::actingAs($this->driver)
            ->test('edit-user-log', ['log' => $this->log()])
            ->assertDontSee('No attachments yet. Upload photos of permits, route sheets, or receipts above.');
    }

    public function test_driver_also_sees_the_permit_on_the_job_page(): void
    {
        $this->jobAttachment();
        $this->log();

        Livewire::actingAs($this->driver)
            ->test('show-pilot-car-job', ['job' => $this->job->id])
            ->assertSee('oversize-permit.pdf');
    }

    public function test_a_driver_can_download_a_job_permit(): void
    {
        $attachment = $this->jobAttachment();

        // The policy already allowed any same-org user to download; the file was
        // simply never surfaced to them. Pin that the permission still holds.
        $this->assertTrue($this->driver->can('download', $attachment));
    }
}
