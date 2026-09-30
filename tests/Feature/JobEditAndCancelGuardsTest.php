<?php

namespace Tests\Feature;

use App\Events\JobWasCanceled;
use App\Livewire\CancelJob;
use App\Livewire\CreatePilotCarJob;
use App\Livewire\EditPilotCarJob;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-458. The edit form had no hook on the customer field, so after
 * changing the customer the truck-driver dropdown kept listing the previous
 * customer's contacts (without phones, and empty when the job had none) and
 * the job could be saved with one of them. Neither form checked the job
 * number against the organization's other jobs although the importer does.
 * CancelJob::cancel() had no state check at all: a second cancel rewrote the
 * reason and date and texted the drivers again, and a cancel after invoicing
 * left the invoice billing the old rate.
 */
class JobEditAndCancelGuardsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $manager;

    private Customer $customerA;

    private Customer $customerB;

    private CustomerContact $driverA;

    private CustomerContact $driverB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->organization = Organization::factory()->create();
        $this->manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
        $this->customerA = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Alpha Freight']);
        $this->customerB = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Bravo Haulage']);
        $this->driverA = CustomerContact::create(['customer_id' => $this->customerA->id, 'organization_id' => $this->organization->id, 'name' => 'Al Driver', 'phone' => '555-0101']);
        $this->driverB = CustomerContact::create(['customer_id' => $this->customerB->id, 'organization_id' => $this->organization->id, 'name' => 'Bea Driver', 'phone' => '555-0202']);
    }

    private function job(array $attributes = []): PilotCarJob
    {
        return PilotCarJob::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customerA->id,
            'default_truck_driver_id' => $this->driverA->id,
            'job_no' => 'JOB-458',
        ], $attributes));
    }

    private function edit(PilotCarJob $job)
    {
        return Livewire::actingAs($this->manager)->test(EditPilotCarJob::class, ['job' => $job->id]);
    }

    public function test_changing_the_customer_reloads_the_truck_drivers_and_clears_the_old_pick(): void
    {
        $page = $this->edit($this->job());

        $this->assertSame('Al Driver (555-0101)', $page->get('truckDrivers')[1]['name'], 'phones show on the edit form too');
        $this->assertSame($this->driverA->id, $page->get('form.default_truck_driver_id'));

        $page->set('form.customer_id', $this->customerB->id);

        $names = array_column($page->get('truckDrivers'), 'name');
        $this->assertContains('Bea Driver (555-0202)', $names);
        $this->assertNotContains('Al Driver (555-0101)', $names);
        $this->assertNull($page->get('form.default_truck_driver_id'));
    }

    public function test_a_job_cannot_be_saved_with_another_customers_contact(): void
    {
        $job = $this->job();

        $this->edit($job)
            ->set('form.customer_id', $this->customerB->id)
            ->set('form.default_truck_driver_id', $this->driverA->id)
            ->call('saveJob')
            ->assertHasErrors(['form.default_truck_driver_id']);

        $job->refresh();
        $this->assertSame($this->customerA->id, $job->customer_id, 'nothing was written');

        $this->edit($job)
            ->set('form.customer_id', $this->customerB->id)
            ->set('form.default_truck_driver_id', $this->driverB->id)
            ->call('saveJob')
            ->assertHasNoErrors();

        $job->refresh();
        $this->assertSame($this->customerB->id, $job->customer_id);
        $this->assertSame($this->driverB->id, $job->default_truck_driver_id);
    }

    public function test_the_dropdown_still_has_its_none_row_when_the_job_has_no_customer(): void
    {
        $job = $this->job();
        $job->setRelation('customer', null);

        // A job with no customer cannot be persisted (the FK is NOT NULL), so
        // ask the form for the list the same way mount() does.
        $page = $this->edit($job);
        $page->set('form.customer_id', null);

        $options = $page->get('truckDrivers');
        $this->assertCount(1, $options);
        $this->assertNull($options[0]['value']);
    }

    public function test_a_duplicate_job_number_is_refused_on_edit_but_the_jobs_own_number_is_fine(): void
    {
        $job = $this->job(['job_no' => 'JOB-458']);
        $this->job(['job_no' => 'JOB-459']);

        $this->edit($job)
            ->set('form.job_no', ' job-459 ')
            ->call('saveJob')
            ->assertHasErrors(['form.job_no']);

        $this->assertSame('JOB-458', $job->fresh()->job_no);

        $this->edit($job)
            ->set('form.job_no', 'JOB-458')
            ->set('form.memo', 'Still the same number')
            ->call('saveJob')
            ->assertHasNoErrors();

        // Another organization's number is not a collision.
        $other = Organization::factory()->create();
        PilotCarJob::factory()->create(['organization_id' => $other->id, 'customer_id' => Customer::factory()->create(['organization_id' => $other->id])->id, 'job_no' => 'JOB-777']);

        $this->edit($job)->set('form.job_no', 'JOB-777')->call('saveJob')->assertHasNoErrors();
        $this->assertSame('JOB-777', $job->fresh()->job_no);
    }

    public function test_a_duplicate_job_number_is_refused_on_create(): void
    {
        $this->job(['job_no' => 'JOB-458']);

        Livewire::actingAs($this->manager)->test(CreatePilotCarJob::class)
            ->set('form.job_no', 'JOB-458')
            ->set('form.customer_id', $this->customerA->id)
            ->set('form.load_no', 'LOAD-1')
            ->set('form.pickup_address', '1 Pickup St')
            ->set('form.delivery_address', '2 Delivery Ave')
            ->set('form.rate_code', 'per_mile_rate_2_00')
            ->call('createJob')
            ->assertHasErrors(['form.job_no']);

        $this->assertSame(1, PilotCarJob::where('job_no', 'JOB-458')->count());
    }

    public function test_the_create_form_refuses_another_customers_contact_too(): void
    {
        Livewire::actingAs($this->manager)->test(CreatePilotCarJob::class)
            ->set('form.job_no', 'JOB-NEW')
            ->set('form.customer_id', $this->customerB->id)
            ->set('form.load_no', 'LOAD-1')
            ->set('form.pickup_address', '1 Pickup St')
            ->set('form.delivery_address', '2 Delivery Ave')
            ->set('form.rate_code', 'per_mile_rate_2_00')
            ->set('form.default_truck_driver_id', $this->driverA->id)
            ->call('createJob')
            ->assertHasErrors(['form.default_truck_driver_id']);

        $this->assertSame(0, PilotCarJob::where('job_no', 'JOB-NEW')->count());
    }

    public function test_a_second_cancel_changes_nothing_and_tells_nobody(): void
    {
        Event::fake([JobWasCanceled::class]);

        $job = $this->job(['scheduled_pickup_at' => now()->addDays(3)]);

        Livewire::actingAs($this->manager)->test(CancelJob::class, ['job' => $job])
            ->set('cancellationReason', 'Customer canceled the load')
            ->set('cancellationType', 'cancel_without_billing')
            ->call('cancel')
            ->assertHasNoErrors();

        $job->refresh();
        $firstCanceledAt = $job->canceled_at;
        $this->assertNotNull($firstCanceledAt);
        Event::assertDispatchedTimes(JobWasCanceled::class, 1);

        $this->travel(1)->hours();

        Livewire::actingAs($this->manager)->test(CancelJob::class, ['job' => $job->fresh()])
            ->set('cancellationReason', 'Second thoughts')
            ->set('cancellationType', 'show_no_go')
            ->call('cancel')
            ->assertHasErrors(['cancellationReason']);

        $job->refresh();
        $this->assertEquals($firstCanceledAt, $job->canceled_at);
        $this->assertStringContainsString('Customer canceled the load', $job->canceled_reason);
        $this->assertStringNotContainsString('Second thoughts', $job->canceled_reason);
        $this->assertSame('cancel_without_billing', $job->rate_code);
        Event::assertDispatchedTimes(JobWasCanceled::class, 1);
    }

    public function test_a_job_on_a_live_invoice_cannot_be_cancelled_until_the_invoice_is_voided(): void
    {
        Event::fake([JobWasCanceled::class]);

        $job = $this->job();
        $invoice = $job->createInvoice();

        Livewire::actingAs($this->manager)->test(CancelJob::class, ['job' => $job])
            ->call('openModal')
            ->set('cancellationReason', 'Customer canceled the load')
            ->set('cancellationType', 'cancel_without_billing')
            ->call('cancel')
            ->assertHasErrors(['cancellationReason'])
            ->assertSee('#' . $invoice->invoice_number);

        $this->assertNull($job->fresh()->canceled_at);
        Event::assertNotDispatched(JobWasCanceled::class);

        $invoice->void($this->manager->id);

        Livewire::actingAs($this->manager)->test(CancelJob::class, ['job' => $job->fresh()])
            ->set('cancellationReason', 'Customer canceled the load')
            ->set('cancellationType', 'cancel_without_billing')
            ->call('cancel')
            ->assertHasNoErrors();

        $this->assertNotNull($job->fresh()->canceled_at);
    }
}
