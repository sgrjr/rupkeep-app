<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * TASK-505 (drive 003). Staff could not see who had a portal login for a
 * customer, could only add one through a contact, and could not take one
 * away; a login stayed tied to its first customer, so its email could never
 * be used for another.
 */
class CustomerPortalLoginsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $manager;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();

        $this->organization = Organization::factory()->create();
        $this->manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'JLS Pilot Car']);
    }

    private function login(Customer $customer, string $email): User
    {
        return User::factory()->asCustomer($customer)->create(['email' => $email, 'name' => 'Login '.$email]);
    }

    public function test_the_customer_page_lists_only_this_customers_logins(): void
    {
        $mine = $this->login($this->customer, 'mine@example.com');
        $other = $this->login(Customer::factory()->create(['organization_id' => $this->organization->id]), 'other@example.com');

        $this->actingAs($this->manager)
            ->get(route('my.customers.show', $this->customer))
            ->assertOk()
            ->assertSee('Portal logins')
            ->assertSee('mine@example.com')
            ->assertDontSee('other@example.com')
            ->assertSee(route('my.customers.logins.destroy', ['customer' => $this->customer->id, 'user' => $mine->id]), false);
    }

    public function test_a_login_can_be_added_without_a_contact(): void
    {
        $this->actingAs($this->manager)
            ->from(route('my.customers.show', $this->customer))
            ->post(route('my.customers.invite', $this->customer), ['name' => 'Richard', 'email' => 'richard@example.com'])
            ->assertRedirect(route('my.customers.show', $this->customer));

        $user = User::where('email', 'richard@example.com')->sole();
        $this->assertSame($this->customer->id, $user->customer_id);
        $this->assertTrue($user->isCustomer());
    }

    public function test_removing_access_stops_sign_in_and_the_email_can_move_to_another_customer(): void
    {
        $first = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Casco Bay Transportation']);
        $richard = $this->login($first, 'richard@example.com');

        // Tied to the first customer: refused, with the way out named.
        $this->actingAs($this->manager)
            ->from(route('my.customers.show', $this->customer))
            ->post(route('my.customers.invite', $this->customer), ['email' => 'richard@example.com'])
            ->assertSessionHasErrors('email');

        $this->actingAs($this->manager)
            ->delete(route('my.customers.logins.destroy', ['customer' => $first->id, 'user' => $richard->id]), ['confirmed' => 1])
            ->assertRedirect(route('my.customers.show', $first));

        $this->assertSoftDeleted($richard);

        // Now it moves.
        $this->actingAs($this->manager)
            ->from(route('my.customers.show', $this->customer))
            ->post(route('my.customers.invite', $this->customer), ['email' => 'richard@example.com', 'name' => 'Richard'])
            ->assertSessionHasNoErrors();

        $richard->refresh();
        $this->assertFalse($richard->trashed());
        $this->assertSame($this->customer->id, $richard->customer_id);
        $this->assertSame(1, User::withTrashed()->where('email', 'richard@example.com')->count());
    }

    public function test_a_removed_staff_account_is_not_revived_as_a_customer_login(): void
    {
        $former = User::factory()->standard()->create(['organization_id' => $this->organization->id, 'email' => 'former@example.com']);
        $former->delete();

        $this->actingAs($this->manager)
            ->from(route('my.customers.show', $this->customer))
            ->post(route('my.customers.invite', $this->customer), ['email' => 'former@example.com'])
            ->assertSessionHasErrors('email');

        $this->assertTrue($former->fresh()->trashed());
        $this->assertNull($former->fresh()->customer_id);
    }

    public function test_remove_access_refuses_staff_other_customers_and_other_organizations(): void
    {
        $staff = User::factory()->standard()->create(['organization_id' => $this->organization->id]);
        $otherCustomersLogin = $this->login(Customer::factory()->create(['organization_id' => $this->organization->id]), 'x@example.com');

        $foreignOrg = Organization::factory()->create();
        $foreignCustomer = Customer::factory()->create(['organization_id' => $foreignOrg->id]);
        $foreignLogin = $this->login($foreignCustomer, 'y@example.com');

        $this->actingAs($this->manager);

        $this->delete(route('my.customers.logins.destroy', ['customer' => $this->customer->id, 'user' => $staff->id]))->assertNotFound();
        $this->delete(route('my.customers.logins.destroy', ['customer' => $this->customer->id, 'user' => $otherCustomersLogin->id]))->assertNotFound();
        $this->delete(route('my.customers.logins.destroy', ['customer' => $foreignCustomer->id, 'user' => $foreignLogin->id]))->assertNotFound();

        $this->assertFalse($staff->fresh()->trashed());
        $this->assertFalse($otherCustomersLogin->fresh()->trashed());
        $this->assertFalse($foreignLogin->fresh()->trashed());
    }

    public function test_a_standard_employee_cannot_remove_access(): void
    {
        $standard = User::factory()->standard()->create(['organization_id' => $this->organization->id]);
        $login = $this->login($this->customer, 'mine@example.com');

        $this->actingAs($standard)
            ->delete(route('my.customers.logins.destroy', ['customer' => $this->customer->id, 'user' => $login->id]))
            ->assertForbidden();

        $this->assertFalse($login->fresh()->trashed());
    }
}
