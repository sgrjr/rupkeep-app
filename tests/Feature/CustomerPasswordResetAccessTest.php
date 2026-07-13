<?php

namespace Tests\Feature;

use App\Livewire\UpdatePasswordForm;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerPasswordResetAccessTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        $org = Organization::factory()->create();
        $cust = Customer::factory()->create(['organization_id' => $org->id]);

        return User::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $cust->id,
            'organization_role' => User::ROLE_CUSTOMER,
            'password' => Hash::make('unknown-to-customer'),
        ]);
    }

    public function test_login_code_page_offers_a_set_or_reset_password_link(): void
    {
        // The login-code page is where code-based customers sign in; it must give
        // them a discoverable path to set/reset a password. See TASK-320.
        $this->get(route('login-code.create'))
            ->assertOk()
            ->assertSee(route('password.request'), false)
            ->assertSee('Set or reset one by email');
    }

    public function test_guest_can_reach_the_password_reset_request_page(): void
    {
        // The reset flow is guest-only (by Fortify design); a customer who has
        // signed out can reach it to set a password without a current one.
        $this->get(route('password.request'))->assertOk();
    }

    public function test_self_password_form_explains_the_forgotten_password_path(): void
    {
        $customer = $this->customer();

        // On the authenticated form we cannot link straight to the guest-only
        // reset route, so we guide the user to sign out and use it.
        Livewire::actingAs($customer)
            ->test(UpdatePasswordForm::class, ['profile' => $customer])
            ->assertSee('Sign out')
            ->assertSee('Forgot your password?');
    }

    public function test_admin_updating_another_user_does_not_see_the_forgotten_password_note(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        $target = User::factory()->create(['organization_id' => $org->id]);

        Livewire::actingAs($admin)
            ->test(UpdatePasswordForm::class, ['profile' => $target])
            ->assertDontSee('Sign out');
    }
}
