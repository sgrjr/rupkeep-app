<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LoginCode;
use App\Models\Organization;
use App\Models\User;
use App\Services\LoginCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class LoginCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_request_login_code(): void
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->for($organization)->create();
        $customerUser = User::factory()->asCustomer($customer)->create([
            'email' => 'customer@example.com',
        ]);

        $response = $this->post(route('login-code.store'), [
            'email' => $customerUser->email,
        ]);

        $response->assertSessionHas('status');
        $this->assertCount(1, $customerUser->loginCodes);
        $this->assertNull($customerUser->loginCodes->first()->used_at);
    }

    public function test_non_customer_cannot_request_login_code(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->manager()->forOrganization($organization)->create();

        $response = $this->from(route('login-code.create'))->post(route('login-code.store'), [
            'email' => $user->email,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertCount(0, $user->loginCodes);
    }

    public function test_login_code_can_be_consumed(): void
    {
        Config::set('login-codes.expires_after_minutes', 60);

        $organization = Organization::factory()->create();
        $customer = Customer::factory()->for($organization)->create();
        $customerUser = User::factory()->asCustomer($customer)->create();

        $service = app(LoginCodeService::class);
        $code = $service->generate($customerUser);

        $response = $this->post(route('login-code.verify'), [
            'code' => $code->code,
        ]);

        $response->assertRedirect();
        $this->assertTrue(Auth::check());
        $this->assertTrue(Auth::user()->is($customerUser));
        $this->assertTrue($code->fresh()->isUsed());
    }

    public function test_expired_login_code_fails(): void
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->for($organization)->create();
        $customerUser = User::factory()->asCustomer($customer)->create();

        $code = LoginCode::factory()->expired()->create([
            'user_id' => $customerUser->id,
        ]);

        $response = $this->from(route('login-code.verify-form'))->post(route('login-code.verify'), [
            'code' => $code->code,
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertFalse(Auth::check());
    }

    public function test_used_login_code_fails(): void
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->for($organization)->create();
        $customerUser = User::factory()->asCustomer($customer)->create();

        $code = LoginCode::factory()->used()->create([
            'user_id' => $customerUser->id,
        ]);

        $response = $this->from(route('login-code.verify-form'))->post(route('login-code.verify'), [
            'code' => $code->code,
        ]);

        $response->assertSessionHasErrors('code');
    }
}

