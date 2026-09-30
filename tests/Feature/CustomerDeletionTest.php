<?php

namespace Tests\Feature;

use App\Livewire\LogExtraCharges;
use App\Models\Attachment;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Invoice;
use App\Models\LogExtraCharge;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use App\Services\CustomerArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-461. Deleting a customer hard-deleted every job and log it owned on
 * one unconfirmed click. Customers stay hard-deleted by decision; what
 * changes is that the delete asks first, writes an archive JSON of
 * everything first, refuses if that archive cannot be written, and offers
 * the file for download. Users, contacts and log extra charges ask first too.
 */
class CustomerDeletionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $admin;
    private Customer $customer;
    private PilotCarJob $job;
    private UserLog $log;
    private CustomerContact $contact;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(CustomerArchive::DISK);

        $this->organization = Organization::factory()->create();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Granite State Haulers']);

        $this->contact = CustomerContact::create([
            'customer_id' => $this->customer->id,
            'organization_id' => $this->organization->id,
            'name' => 'Pat Dispatcher',
            'phone' => '207-555-0100',
        ]);

        $this->job = PilotCarJob::create([
            'job_no' => 'JOB-461',
            'customer_id' => $this->customer->id,
            'organization_id' => $this->organization->id,
            'pickup_address' => 'Gorham, ME',
            'delivery_address' => 'Boston, MA',
            'rate_code' => 'flat_rate',
            'rate_value' => '500.00',
        ]);

        $driver = User::factory()->standard()->create(['organization_id' => $this->organization->id]);
        $this->log = UserLog::create([
            'job_id' => $this->job->id,
            'organization_id' => $this->organization->id,
            'car_driver_id' => $driver->id,
            'approval_status' => 'confirmed',
            'start_mileage' => 100,
            'end_mileage' => 250,
            'memo' => 'Rainy run up Route 1.',
        ]);

        LogExtraCharge::create([
            'user_log_id' => $this->log->id,
            'organization_id' => $this->organization->id,
            'description' => 'Overnight permit',
            'amount' => 45.00,
            'sort_order' => 1,
        ]);

        Attachment::create([
            'attachable_id' => $this->log->id,
            'attachable_type' => $this->log->getMorphClass(),
            'location' => 'logs/receipt-461.jpg',
            'file_name' => 'receipt-461.jpg',
            'is_public' => false,
            'organization_id' => $this->organization->id,
        ]);

        $this->invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'pilot_car_job_id' => $this->job->id,
        ]);
    }

    private function archives(): array
    {
        return Storage::disk(CustomerArchive::DISK)->files(CustomerArchive::DIRECTORY);
    }

    // -----------------------------------------------------------------
    // The delete itself
    // -----------------------------------------------------------------

    public function test_deleting_a_customer_writes_the_archive_then_removes_everything_it_owned(): void
    {
        $response = $this->actingAs($this->admin)
            ->delete(route('my.customers.destroy', ['customer' => $this->customer->id]), ['confirmed' => 1])
            ->assertRedirect(route('customers.index'));

        // The archive, first.
        $files = $this->archives();
        $this->assertCount(1, $files, 'exactly one archive file is written');
        $this->assertMatchesRegularExpression('#^archives/customers/' . $this->customer->id . '-\d{8}-\d{6}\.json$#', $files[0]);

        $archive = json_decode(Storage::disk(CustomerArchive::DISK)->get($files[0]), true);
        $this->assertSame(CustomerArchive::FORMAT, $archive['format']);
        $this->assertSame($this->organization->id, $archive['organization_id']);
        $this->assertSame($this->admin->email, $archive['archived_by']['email']);
        $this->assertSame('Granite State Haulers', $archive['customer']['name']);
        $this->assertSame('Pat Dispatcher', $archive['contacts'][0]['name']);
        $this->assertSame('JOB-461', $archive['jobs'][0]['job_no']);
        $this->assertSame('Rainy run up Route 1.', $archive['logs'][0]['memo']);
        $this->assertSame('Overnight permit', $archive['logs'][0]['extra_charges'][0]['description']);
        $this->assertSame('receipt-461.jpg', $archive['attachments']['logs'][0]['file_name']);
        $this->assertSame($this->invoice->id, $archive['invoices'][0]['id']);
        $this->assertSame(['contacts' => 1, 'jobs' => 1, 'logs' => 1, 'invoices' => 1], $archive['counts']);

        // Then the delete, all of it.
        $this->assertDatabaseMissing('customers', ['id' => $this->customer->id]);
        $this->assertDatabaseMissing('customer_contacts', ['id' => $this->contact->id]);
        $this->assertDatabaseMissing('pilot_car_jobs', ['id' => $this->job->id]);
        $this->assertDatabaseMissing('user_logs', ['id' => $this->log->id]);
        $this->assertDatabaseMissing('log_extra_charges', ['user_log_id' => $this->log->id]);
        $this->assertDatabaseMissing('attachments', ['attachable_id' => $this->log->id, 'attachable_type' => $this->log->getMorphClass()]);

        // Invoices have no FK to customers and are left as they were.
        $this->assertDatabaseHas('invoices', ['id' => $this->invoice->id]);

        // The list offers the file.
        $file = basename($files[0]);
        $this->actingAs($this->admin)->get(route('customers.index'))
            ->assertSee('Granite State Haulers has been deleted')
            ->assertSee('Download archive')
            ->assertSee(route('my.customers.archives.download', ['file' => $file]), false);
    }

    public function test_the_archive_can_be_downloaded_by_the_organizations_admin_only(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('my.customers.destroy', ['customer' => $this->customer->id]), ['confirmed' => 1]);
        $file = basename($this->archives()[0]);

        $response = $this->actingAs($this->admin)
            ->get(route('my.customers.archives.download', ['file' => $file]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=customer-archive-' . $file);

        $this->assertStringContainsString('Granite State Haulers', $response->streamedContent());

        // Same organization is not the same role: a manager may not.
        $manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($manager)->get(route('my.customers.archives.download', ['file' => $file]))->assertForbidden();

        // And an admin of another company may not, even knowing the file name.
        $outsider = User::factory()->admin()->create(['organization_id' => Organization::factory()->create()->id]);
        $this->actingAs($outsider)->get(route('my.customers.archives.download', ['file' => $file]))->assertForbidden();

        // Only this class's own file names are served.
        $this->actingAs($this->admin)->get(route('my.customers.archives.download', ['file' => '..%2F..%2F.env']))->assertNotFound();
        $this->actingAs($this->admin)->get(route('my.customers.archives.download', ['file' => '999-20260101-000000.json']))->assertNotFound();
    }

    public function test_an_unconfirmed_delete_deletes_nothing(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('my.customers.destroy', ['customer' => $this->customer->id]))
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', ['id' => $this->customer->id]);
        $this->assertDatabaseHas('pilot_car_jobs', ['id' => $this->job->id]);
        $this->assertSame([], $this->archives());

        $this->actingAs($this->admin)->get(route('customers.index'))->assertSee('needs confirmation. Nothing was deleted.');
    }

    public function test_a_failed_archive_write_aborts_the_delete(): void
    {
        $disk = Storage::disk(CustomerArchive::DISK);
        $broken = \Mockery::mock($disk)->makePartial();
        $broken->shouldReceive('put')->once()->andReturn(false);
        Storage::set(CustomerArchive::DISK, $broken);

        $this->actingAs($this->admin)
            ->delete(route('my.customers.destroy', ['customer' => $this->customer->id]), ['confirmed' => 1])
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', ['id' => $this->customer->id]);
        $this->assertDatabaseHas('user_logs', ['id' => $this->log->id]);

        $this->actingAs($this->admin)->get(route('customers.index'))->assertSee('could not be written, so nothing was deleted');
    }

    public function test_a_manager_cannot_delete_a_customer_even_with_confirmation(): void
    {
        $manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($manager)
            ->delete(route('my.customers.destroy', ['customer' => $this->customer->id]), ['confirmed' => 1])
            ->assertForbidden();

        $this->assertDatabaseHas('customers', ['id' => $this->customer->id]);
        $this->assertSame([], $this->archives());
    }

    public function test_the_resource_controller_deletes_the_same_way(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('customers.destroy', ['customer' => $this->customer->id]), ['confirmed' => 1])
            ->assertRedirect(route('customers.index'));

        $this->assertCount(1, $this->archives());
        $this->assertDatabaseMissing('customers', ['id' => $this->customer->id]);
    }

    // -----------------------------------------------------------------
    // Asking first
    // -----------------------------------------------------------------

    public function test_the_customer_list_asks_before_deleting(): void
    {
        $this->actingAs($this->admin)->get(route('customers.index'))
            ->assertSee('Yes, archive and delete')
            ->assertSee('There is no undo; an archive file is saved on the server and offered for download.')
            ->assertSee('name="confirmed" value="1"', false)
            // The old plain form posted on one click.
            ->assertDontSee('<form action="' . route('my.customers.destroy', ['customer' => $this->customer->id]) . '" method="post" class="inline">', false);
    }

    public function test_the_user_list_asks_before_deleting(): void
    {
        $this->actingAs($this->admin)->get(route('my.users.index'))
            ->assertSee('Yes, delete')
            ->assertSee('data-test="delete-confirm"', false);
    }

    public function test_contacts_get_a_real_delete_button_with_confirmation(): void
    {
        $this->actingAs($this->admin)->get(route('customers.edit', ['customer' => $this->customer->id]))
            ->assertSee('Delete contact')
            ->assertSee('Removes Pat Dispatcher from this customer.')
            ->assertDontSee('nc_delete' . $this->contact->id, false);

        // Save no longer deletes, whatever the request carries.
        $this->actingAs($this->admin)->put(
            route('customers.contacts.update', ['customer' => $this->customer->id, 'contact' => $this->contact->id]),
            ['name' => 'Pat Dispatcher', 'delete' => 'on']
        );
        $this->assertDatabaseHas('customer_contacts', ['id' => $this->contact->id]);

        // The delete route does, once confirmed.
        $this->actingAs($this->admin)->delete(
            route('customers.contacts.destroy', ['customer' => $this->customer->id, 'contact' => $this->contact->id])
        );
        // Unconfirmed: nothing happens.
        $this->assertDatabaseHas('customer_contacts', ['id' => $this->contact->id]);

        $this->actingAs($this->admin)->delete(
            route('customers.contacts.destroy', ['customer' => $this->customer->id, 'contact' => $this->contact->id]),
            ['confirmed' => 1]
        );
        $this->assertDatabaseMissing('customer_contacts', ['id' => $this->contact->id]);
    }

    public function test_removing_an_extra_charge_asks_first(): void
    {
        $html = Livewire::actingAs($this->admin)
            ->test(LogExtraCharges::class, ['log' => $this->log])
            ->assertSee('Remove this charge?')
            ->assertSee('Overnight permit')
            ->html();

        // TASK-419: the amount box had no text colour, so typed figures were invisible on some themes.
        $this->assertMatchesRegularExpression('/id="charge-amount-\d+"[^>]*class="[^"]*text-slate-900[^"]*"/s', $html);
    }
}
