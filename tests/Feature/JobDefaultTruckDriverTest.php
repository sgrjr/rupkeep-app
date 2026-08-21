<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-362: the Default Assignments truck-driver dropdown only ever populated
 * from an already-selected customer's contacts, and there was no way to add a
 * truck driver inline — unlike the company field right above it, which does
 * let you type a new one. Creating a job for a brand-new customer therefore
 * left the truck driver permanently unselectable.
 */
class JobDefaultTruckDriverTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->manager = User::factory()->manager()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    private function customer(string $name = 'Acme Hauling'): Customer
    {
        return Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => $name,
        ]);
    }

    private function jobFields(): array
    {
        return [
            'form.job_no' => 'JOB-TD-1',
            'form.load_no' => 'LOAD-1',
            'form.pickup_address' => '1 Demo St',
            'form.delivery_address' => '2 Demo Ave',
            'form.rate_code' => 'lead_chase_per_mile',
        ];
    }

    public function test_selecting_a_customer_populates_their_truck_drivers(): void
    {
        $customer = $this->customer();
        CustomerContact::create([
            'customer_id' => $customer->id,
            'organization_id' => $this->organization->id,
            'name' => 'Hank Fielding',
            'phone' => '555-0101',
        ]);

        $component = Livewire::actingAs($this->manager)
            ->test('create-pilot-car-job')
            ->set('form.customer_id', $customer->id);

        $names = collect($component->get('truckDrivers'))->pluck('name');

        $this->assertTrue($names->contains(fn ($n) => str_contains($n, 'Hank Fielding')));
    }

    public function test_a_new_truck_driver_is_attached_to_the_selected_customer(): void
    {
        $customer = $this->customer();

        Livewire::actingAs($this->manager)
            ->test('create-pilot-car-job')
            ->set($this->jobFields())
            ->set('form.customer_id', $customer->id)
            ->set('form.new_truck_driver_name', 'Dana Rooks')
            ->set('form.new_truck_driver_phone', '555-0199')
            ->call('createJob');

        $contact = CustomerContact::where('name', 'Dana Rooks')->first();

        $this->assertNotNull($contact, 'the inline truck driver should have been created');
        $this->assertSame($customer->id, $contact->customer_id);
        $this->assertSame('555-0199', $contact->phone);

        $job = PilotCarJob::where('job_no', 'JOB-TD-1')->first();
        $this->assertSame($contact->id, $job->default_truck_driver_id);
    }

    public function test_a_new_truck_driver_attaches_to_a_customer_created_in_the_same_submission(): void
    {
        Livewire::actingAs($this->manager)
            ->test('create-pilot-car-job')
            ->set($this->jobFields())
            ->set('form.new_customer_name', 'Brand New Freight')
            ->set('form.new_truck_driver_name', 'Dana Rooks')
            ->call('createJob');

        $customer = Customer::where('name', 'Brand New Freight')->first();
        $contact = CustomerContact::where('name', 'Dana Rooks')->first();

        $this->assertNotNull($customer);
        $this->assertNotNull($contact, 'the driver must attach to the company created in the same submission');
        $this->assertSame($customer->id, $contact->customer_id);

        $job = PilotCarJob::where('job_no', 'JOB-TD-1')->first();
        $this->assertSame($customer->id, $job->customer_id);
        $this->assertSame($contact->id, $job->default_truck_driver_id);
    }

    public function test_an_existing_contact_is_reused_rather_than_duplicated(): void
    {
        $customer = $this->customer();
        $existing = CustomerContact::create([
            'customer_id' => $customer->id,
            'organization_id' => $this->organization->id,
            'name' => 'Hank Fielding',
            'phone' => '555-0101',
        ]);

        Livewire::actingAs($this->manager)
            ->test('create-pilot-car-job')
            ->set($this->jobFields())
            ->set('form.customer_id', $customer->id)
            ->set('form.new_truck_driver_name', 'Hank Fielding')
            ->set('form.new_truck_driver_phone', '555-0101')
            ->call('createJob');

        $this->assertSame(1, CustomerContact::where('name', 'Hank Fielding')->count());

        $job = PilotCarJob::where('job_no', 'JOB-TD-1')->first();
        $this->assertSame($existing->id, $job->default_truck_driver_id);
    }

    public function test_an_explicitly_chosen_contact_wins_over_a_blank_new_name(): void
    {
        $customer = $this->customer();
        $contact = CustomerContact::create([
            'customer_id' => $customer->id,
            'organization_id' => $this->organization->id,
            'name' => 'Hank Fielding',
            'phone' => '555-0101',
        ]);

        Livewire::actingAs($this->manager)
            ->test('create-pilot-car-job')
            ->set($this->jobFields())
            ->set('form.customer_id', $customer->id)
            ->set('form.default_truck_driver_id', $contact->id)
            ->call('createJob');

        $job = PilotCarJob::where('job_no', 'JOB-TD-1')->first();
        $this->assertSame($contact->id, $job->default_truck_driver_id);
        $this->assertSame(1, CustomerContact::count());
    }

    public function test_the_edit_form_can_also_add_a_truck_driver_inline(): void
    {
        $customer = $this->customer();

        $job = PilotCarJob::create([
            'job_no' => 'JOB-TD-EDIT',
            'customer_id' => $customer->id,
            'organization_id' => $this->organization->id,
            'load_no' => 'LOAD-9',
            'pickup_address' => '1 Demo St',
            'delivery_address' => '2 Demo Ave',
            'rate_code' => 'lead_chase_per_mile',
            'rate_value' => '2.00',
        ]);

        Livewire::actingAs($this->manager)
            ->test('edit-pilot-car-job', ['job' => $job->id])
            ->set('form.new_truck_driver_name', 'Dana Rooks')
            ->set('form.new_truck_driver_phone', '555-0199')
            ->call('saveJob');

        $contact = CustomerContact::where('name', 'Dana Rooks')->first();

        $this->assertNotNull($contact);
        $this->assertSame($customer->id, $contact->customer_id);
        $this->assertSame($contact->id, $job->fresh()->default_truck_driver_id);
    }

    public function test_the_new_truck_driver_name_is_not_persisted_onto_the_job(): void
    {
        $customer = $this->customer();

        Livewire::actingAs($this->manager)
            ->test('create-pilot-car-job')
            ->set($this->jobFields())
            ->set('form.customer_id', $customer->id)
            ->set('form.new_truck_driver_name', 'Dana Rooks')
            ->call('createJob');

        $job = PilotCarJob::where('job_no', 'JOB-TD-1')->first();

        $this->assertNotNull($job);
        $this->assertArrayNotHasKey('new_truck_driver_name', $job->getAttributes());
    }
}
