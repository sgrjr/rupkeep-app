<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_invite_a_customer_and_a_portal_account_is_created(): void
    {
        Mail::fake();

        $org = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $org->id]);
        $customer = Customer::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($manager)
            ->post(route('my.customers.invite', $customer), [
                'email' => 'Client@Example.com',
                'name' => 'Client Contact',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $user = User::where('email', 'client@example.com')->first();
        $this->assertNotNull($user, 'A portal user should be created (email normalized to lowercase).');
        $this->assertSame(User::ROLE_CUSTOMER, $user->organization_role);
        $this->assertSame($customer->id, $user->customer_id);
        $this->assertSame($org->id, $user->organization_id);
        $this->assertSame('Client Contact', $user->name);
    }

    public function test_reinviting_the_same_customer_is_idempotent(): void
    {
        Mail::fake();

        $org = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $org->id]);
        $customer = Customer::factory()->create(['organization_id' => $org->id]);

        $payload = ['email' => 'client@example.com', 'name' => 'Client'];

        $this->actingAs($manager)->post(route('my.customers.invite', $customer), $payload);
        $this->actingAs($manager)->post(route('my.customers.invite', $customer), $payload);

        $this->assertSame(1, User::where('email', 'client@example.com')->count(),
            'Re-inviting the same email must not create a duplicate portal account.');
    }

    public function test_cannot_invite_an_email_belonging_to_a_staff_account(): void
    {
        Mail::fake();

        $org = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $org->id]);
        $staff = User::factory()->standard()->create([
            'organization_id' => $org->id,
            'email' => 'staff@example.com',
        ]);
        $customer = Customer::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($manager)
            ->from(route('my.customers.show', $customer))
            ->post(route('my.customers.invite', $customer), ['email' => 'staff@example.com'])
            ->assertSessionHasErrors('email');

        // The staff account must be left untouched (not demoted to a customer).
        $this->assertSame(User::ROLE_EMPLOYEE_STANDARD, $staff->fresh()->organization_role);
        $this->assertNull($staff->fresh()->customer_id);
    }

    public function test_standard_employee_cannot_invite(): void
    {
        $org = Organization::factory()->create();
        $standard = User::factory()->standard()->create(['organization_id' => $org->id]);
        $customer = Customer::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($standard)
            ->post(route('my.customers.invite', $customer), ['email' => 'client@example.com'])
            ->assertForbidden();

        $this->assertSame(0, User::where('email', 'client@example.com')->count());
    }
}
