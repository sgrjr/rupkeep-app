<?php

namespace Tests\Feature;

use App\Livewire\PrimaryNavigationMenu;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fixtures\CreatesMultiTenantFixtures;
use Tests\TestCase;

/**
 * TASK-434 regression. Customer-portal accounts carry the company's
 * organization_id, and every `view` policy checked the organization only, so
 * a customer could open any job, log, customer, vehicle and private file in
 * the company and list all of its invoices (Poc4Test proved it). Staff
 * routes now carry the `staff` middleware, the policies require a staff
 * role, and attachments distinguish public from private.
 */
class CustomerRoleBoundaryTest extends TestCase
{
    use RefreshDatabase, CreatesMultiTenantFixtures;

    private $org;
    private User $customerUser;
    private User $driver;
    private $ownCustomer;
    private $otherCustomer;
    private $ownJob;
    private $otherJob;
    private $log;
    private $vehicle;
    private $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        \Illuminate\Support\Facades\Storage::fake(Attachment::DISK);

        $this->org = $this->createOrganization('A');
        $this->ownCustomer = $this->createCustomerForOrganization($this->org);
        $this->otherCustomer = $this->createCustomerForOrganization($this->org);
        $this->customerUser = $this->createUserForOrganization($this->org, User::ROLE_CUSTOMER);
        $this->customerUser->forceFill(['customer_id' => $this->ownCustomer->id])->save();
        $this->driver = $this->createUserForOrganization($this->org, User::ROLE_EMPLOYEE_STANDARD);
        $this->ownJob = $this->createJobForOrganization($this->org, $this->ownCustomer);
        $this->otherJob = $this->createJobForOrganization($this->org, $this->otherCustomer);
        $this->vehicle = $this->createVehicleForOrganization($this->org);
        $this->contact = $this->createCustomerContact($this->ownCustomer);
        $this->log = $this->createLogForOrganization($this->org, $this->ownJob, $this->driver, $this->vehicle, $this->contact);
    }

    /** @return array<string, string> route name => url */
    private function staffUrls(): array
    {
        return [
            'my.jobs.show' => route('my.jobs.show', $this->ownJob),
            'jobs.show' => route('jobs.show', $this->ownJob),
            'my.jobs.edit' => route('my.jobs.edit', $this->ownJob),
            'my.jobs.create' => route('my.jobs.create'),
            'logs.edit' => route('logs.edit', $this->log),
            'my.customers.show' => route('my.customers.show', $this->ownCustomer),
            'my.customers.edit' => route('my.customers.edit', $this->ownCustomer),
            'my.customers.create' => route('my.customers.create'),
            'customers.contacts.index' => route('customers.contacts.index', $this->ownCustomer),
            'my.vehicles.show' => route('my.vehicles.show', $this->vehicle),
            'my.vehicles.edit' => route('my.vehicles.edit', $this->vehicle),
            'my.vehicles.create' => route('my.vehicles.create'),
            'my.users.create' => route('my.users.create'),
            'my.users.show' => route('my.users.show', $this->driver),
            'my.invoices.index' => route('my.invoices.index'),
            'organizations.show' => route('organizations.show', $this->org),
        ];
    }

    public function test_customer_is_bounced_from_every_staff_page(): void
    {
        foreach ($this->staffUrls() as $name => $url) {
            $response = $this->actingAs($this->customerUser)->get($url);

            $this->assertContains($response->getStatusCode(), [302, 403], "$name let a customer in");
            if ($response->getStatusCode() === 302) {
                $response->assertRedirect(route('customer.invoices.index'));
            }
        }
    }

    public function test_customer_policies_refuse_even_without_the_route(): void
    {
        $user = $this->customerUser;

        $this->assertFalse($user->can('view', $this->ownJob));
        $this->assertFalse($user->can('view', $this->log));
        $this->assertFalse($user->can('view', $this->ownCustomer));
        $this->assertFalse($user->can('view', $this->vehicle));
        $this->assertFalse($user->can('viewAny', \App\Models\Invoice::class));
    }

    public function test_driver_still_reaches_their_work(): void
    {
        $this->assertTrue($this->driver->can('view', $this->ownJob));
        $this->assertTrue($this->driver->can('view', $this->log));
        $this->assertTrue($this->driver->can('view', $this->ownCustomer));
        $this->assertTrue($this->driver->can('view', $this->vehicle));

        $this->actingAs($this->driver)->get(route('my.jobs.show', $this->ownJob))->assertOk();
        $this->actingAs($this->driver)->get(route('logs.edit', $this->log))->assertOk();
    }

    private function attachment(object $attachable, bool $public): Attachment
    {
        // A relative path on the private disk, as every row is now (TASK-454).
        $path = 'jobs/attachments_' . $attachable->id . '/' . uniqid('att') . '.pdf';
        Attachment::disk()->put($path, 'contents');

        return Attachment::create([
            'attachable_id' => $attachable->id,
            'attachable_type' => get_class($attachable),
            'location' => $path,
            'file_name' => 'permit.pdf',
            'organization_id' => $this->org->id,
            'is_public' => $public,
        ]);
    }

    public function test_customer_downloads_only_public_files_on_their_own_jobs(): void
    {
        $ownPublic = $this->attachment($this->ownJob, true);
        $ownPrivate = $this->attachment($this->ownJob, false);
        $ownLogPublic = $this->attachment($this->log, true);
        $othersPublic = $this->attachment($this->otherJob, true);

        $as = fn () => $this->actingAs($this->customerUser);

        $as()->get(route('attachments.download', $ownPublic))->assertOk();
        $as()->get(route('attachments.download', $ownLogPublic))->assertOk();
        $as()->get(route('attachments.download', $ownPrivate))->assertForbidden();
        $as()->get(route('attachments.download', $othersPublic))->assertForbidden();

        // Never delete.
        $as()->delete(route('attachments.destroy', $ownPublic));
        $this->assertNotNull(Attachment::find($ownPublic->id));
    }

    public function test_driver_downloads_org_files_and_deletes_only_their_own_log_attachments(): void
    {
        $private = $this->attachment($this->otherJob, false);
        $onOwnLog = $this->attachment($this->log, false);
        $otherDriver = $this->createUserForOrganization($this->org, User::ROLE_EMPLOYEE_STANDARD);
        $otherLog = $this->createLogForOrganization($this->org, $this->otherJob, $otherDriver, $this->vehicle, $this->createCustomerContact($this->otherCustomer));
        $onOtherLog = $this->attachment($otherLog, false);

        $this->actingAs($this->driver)->get(route('attachments.download', $private))->assertOk();

        $this->actingAs($this->driver)->delete(route('attachments.destroy', $private));
        $this->assertNotNull(Attachment::find($private->id), 'a job attachment is not a driver\'s to delete');

        $this->actingAs($this->driver)->delete(route('attachments.destroy', $onOtherLog));
        $this->assertNotNull(Attachment::find($onOtherLog->id), 'another driver\'s log attachment is not theirs to delete');

        $this->actingAs($this->driver)->delete(route('attachments.destroy', $onOwnLog));
        $this->assertNull(Attachment::find($onOwnLog->id));
    }

    public function test_navigation_loads_the_organization_list_only_for_super_users(): void
    {
        $super = User::factory()->superUser()->create(['organization_id' => $this->org->id]);
        $this->createOrganization('B');

        $this->assertCount(0, Livewire::actingAs($this->customerUser)->test(PrimaryNavigationMenu::class)->get('organizations'));
        $this->assertCount(0, Livewire::actingAs($this->driver)->test(PrimaryNavigationMenu::class)->get('organizations'));
        $this->assertCount(2, Livewire::actingAs($super)->test(PrimaryNavigationMenu::class)->get('organizations'));
    }
}
