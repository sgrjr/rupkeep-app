<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\CreatesMultiTenantFixtures;
use Tests\TestCase;

/**
 * TASK-437 regression. Several controllers did `new Model($request->except('_method'))`
 * with no validation, so a form could set organization_id, customer_id,
 * account_credit or deleted_at directly. Every action now validates an
 * allow-list, takes organization_id from the actor and customer_id from the
 * route.
 */
class MassAssignmentBoundaryTest extends TestCase
{
    use RefreshDatabase, CreatesMultiTenantFixtures;

    private $orgA;
    private $orgB;
    private User $adminA;
    private Customer $customerA;
    private Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->orgA = $this->createOrganization('A');
        $this->orgB = $this->createOrganization('B');
        $this->adminA = $this->createUserForOrganization($this->orgA, User::ROLE_ADMIN);
        $this->customerA = $this->createCustomerForOrganization($this->orgA);
        $this->customerB = $this->createCustomerForOrganization($this->orgB);
    }

    public function test_new_user_lands_in_the_actors_organization_and_only_binds_to_its_own_customers(): void
    {
        $this->actingAs($this->adminA)
            ->post(route('my.users.store'), [
                'name' => 'Portal Person',
                'email' => 'portal@example.test',
                'password' => 'secret1',
                'organization_role' => User::ROLE_CUSTOMER,
                'organization_id' => $this->orgB->id,
                'customer_id' => $this->customerB->id,
                'is_super' => 1,
            ])
            ->assertSessionHasErrors(['customer_id']);

        $this->assertDatabaseMissing('users', ['email' => 'portal@example.test']);

        $this->actingAs($this->adminA)
            ->post(route('my.users.store'), [
                'name' => 'Portal Person',
                'email' => 'portal@example.test',
                'password' => 'secret1',
                'organization_role' => User::ROLE_CUSTOMER,
                'organization_id' => $this->orgB->id,
                'customer_id' => $this->customerA->id,
            ])
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'portal@example.test')->firstOrFail();
        $this->assertSame($this->orgA->id, $user->organization_id);
        $this->assertSame($this->customerA->id, $user->customer_id);
        $this->assertFalse($user->isSuper());
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('secret1', $user->password));

        // Duplicate email and unknown role are validation errors, not rows.
        $this->actingAs($this->adminA)
            ->post(route('my.users.store'), [
                'name' => 'Again',
                'email' => 'portal@example.test',
                'password' => 'secret1',
                'organization_role' => 'overlord',
            ])
            ->assertSessionHasErrors(['email', 'organization_role']);
    }

    public function test_customer_forms_cannot_move_a_customer_between_organizations(): void
    {
        $this->actingAs($this->adminA)
            ->post(route('customers.store'), ['name' => 'New Co', 'organization_id' => $this->orgB->id])
            ->assertRedirect();

        $this->assertSame($this->orgA->id, Customer::where('name', 'New Co')->firstOrFail()->organization_id);

        $this->actingAs($this->adminA)
            ->put(route('customers.update', $this->customerA), [
                'name' => 'Renamed',
                'organization_id' => $this->orgB->id,
                'account_credit' => 250,
            ])
            ->assertRedirect();

        $fresh = $this->customerA->fresh();
        $this->assertSame('Renamed', $fresh->name);
        $this->assertSame($this->orgA->id, $fresh->organization_id);
        $this->assertEquals(250, (float) $fresh->account_credit);

        $this->actingAs($this->adminA)
            ->put(route('my.customers.update', $this->customerA), ['name' => '', 'organization_id' => $this->orgB->id])
            ->assertSessionHasErrors(['name']);
    }

    public function test_contact_belongs_to_the_customer_in_the_url_not_the_body(): void
    {
        $this->actingAs($this->adminA)
            ->post(route('customers.contacts.store', $this->customerA), [
                'name' => 'Planted',
                'customer_id' => $this->customerB->id,
                'organization_id' => $this->orgB->id,
                'is_main_contact' => 1,
            ])
            ->assertRedirect();

        $contact = CustomerContact::where('name', 'Planted')->firstOrFail();
        $this->assertSame($this->customerA->id, $contact->customer_id);
        $this->assertSame($this->orgA->id, $contact->organization_id);

        // And never on another organization's customer at all.
        $this->actingAs($this->adminA)
            ->post(route('customers.contacts.store', $this->customerB), ['name' => 'Intruder'])
            ->assertForbidden();

        $this->assertDatabaseMissing('customer_contacts', ['name' => 'Intruder']);
    }

    public function test_drivers_cannot_edit_contacts_and_only_admins_delete_them(): void
    {
        $driver = $this->createUserForOrganization($this->orgA, User::ROLE_EMPLOYEE_STANDARD);
        $manager = $this->createUserForOrganization($this->orgA, User::ROLE_EMPLOYEE_MANAGER);
        $contact = $this->createCustomerContact($this->customerA, ['name' => 'Main']);
        $url = route('customers.contacts.update', ['customer' => $this->customerA->id, 'contact' => $contact->id]);

        $this->actingAs($driver)->put($url, ['name' => 'Changed'])->assertForbidden();
        $this->assertSame('Main', $contact->fresh()->name);

        $this->actingAs($manager)->put($url, ['delete' => 'on'])->assertForbidden();
        $this->assertNotNull(CustomerContact::find($contact->id));

        $this->actingAs($manager)->put($url, ['name' => 'Changed'])->assertRedirect();
        $this->assertSame('Changed', $contact->fresh()->name);

        $this->actingAs($this->adminA)->put($url, ['delete' => 'on'])->assertRedirect();
        $this->assertNull(CustomerContact::find($contact->id));
    }

    public function test_job_forms_cannot_set_organization_deletion_or_foreign_references(): void
    {
        $manager = $this->createUserForOrganization($this->orgA, User::ROLE_EMPLOYEE_MANAGER);
        $job = $this->createJobForOrganization($this->orgA, $this->customerA, ['load_no' => 'BEFORE']);

        $this->actingAs($manager)
            ->put(route('my.jobs.update', $job), [
                'load_no' => 'AFTER',
                'organization_id' => $this->orgB->id,
                'deleted_at' => now()->toDateTimeString(),
                'invoice_paid' => 1,
            ])
            ->assertRedirect();

        $fresh = $job->fresh();
        $this->assertSame('AFTER', $fresh->load_no);
        $this->assertSame($this->orgA->id, $fresh->organization_id);
        $this->assertNull($fresh->deleted_at);
        $this->assertFalse((bool) $fresh->invoice_paid);

        $this->actingAs($manager)
            ->put(route('my.jobs.update', $job), ['customer_id' => $this->customerB->id])
            ->assertSessionHasErrors(['customer_id']);

        $this->actingAs($manager)->put(route('my.jobs.update', 999999), ['load_no' => 'X'])->assertNotFound();

        $this->actingAs($manager)
            ->post(route('my.jobs.store'), ['job_no' => 'NEW-1', 'customer_id' => $this->customerA->id, 'organization_id' => $this->orgB->id])
            ->assertRedirect();

        $this->assertDatabaseHas('pilot_car_jobs', ['job_no' => 'NEW-1', 'organization_id' => $this->orgA->id]);
    }
}
