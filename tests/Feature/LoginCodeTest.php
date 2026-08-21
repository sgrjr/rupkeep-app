<?php

namespace Tests\Feature;

use App\Mail\UserNotification;
use App\Models\Customer;
use App\Models\LoginCode;
use App\Models\Organization;
use App\Models\User;
use App\Services\LoginCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LoginCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_request_login_code(): void
    {
        Mail::fake();

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

    /**
     * TASK-319 — the request form used to reject anyone who was not a customer.
     * Passwordless sign-in is now open to every role, so a manager asking for a
     * link gets one.
     */
    public function test_staff_can_request_a_sign_in_link(): void
    {
        Mail::fake();

        $organization = Organization::factory()->create();
        $user = User::factory()->manager()->forOrganization($organization)->create();

        $response = $this->post(route('login-code.store'), [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status');
        $response->assertSessionHasNoErrors();
        $this->assertCount(1, $user->fresh()->loginCodes);

        Mail::assertSent(UserNotification::class, fn (UserNotification $mail) => $mail->hasTo($user->email));
    }

    /**
     * TASK-319 — an unknown email gets the same response as a known one. The
     * endpoint is public, so a distinguishable error would let anyone probe
     * for which staff addresses hold accounts.
     */
    public function test_unknown_email_gets_the_same_neutral_response(): void
    {
        Mail::fake();

        $organization = Organization::factory()->create();
        $known = User::factory()->manager()->forOrganization($organization)->create();

        $knownResponse = $this->post(route('login-code.store'), ['email' => $known->email]);
        $unknownResponse = $this->post(route('login-code.store'), ['email' => 'nobody@example.com']);

        $unknownResponse->assertSessionHasNoErrors();
        $this->assertSame(
            $knownResponse->getSession()->get('status'),
            $unknownResponse->getSession()->get('status')
        );

        $this->assertSame(1, LoginCode::count());
        Mail::assertSentCount(1);
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

    /**
     * TASK-319 — the verify form used to allow only customers and admins.
     */
    public function test_staff_can_consume_a_login_code(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->standard()->forOrganization($organization)->create();

        $code = app(LoginCodeService::class)->generate($user);

        $this->post(route('login-code.verify'), ['code' => $code->code])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
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

    /**
     * TASK-319 - a 2-hour window must read as "2 hours". Carbon's
     * diffForHumans() truncates, so a code minted 120 minutes ago-minus-a-
     * second reported "1 hour" - understating the window on every single
     * send, in both the email and the confirm page.
     */
    public function test_expiry_wording_rounds_up_rather_than_truncating(): void
    {
        Config::set('login-codes.expires_after_minutes', 120);

        $organization = Organization::factory()->create();
        $user = User::factory()->standard()->forOrganization($organization)->create();

        $code = app(LoginCodeService::class)->generate($user);

        $this->assertSame('2 hours', $code->expiresInWords());

        $this->get(route('login-link', $code->link_token))
            ->assertOk()
            ->assertSee('This link expires in 2 hours.');
    }

    /**
     * TASK-319 — the code is typed off a screen, so it must be exactly the
     * configured length and drawn from an unambiguous alphabet. The previous
     * implementation regex-stripped a mixed-case random string, which silently
     * produced codes shorter than configured.
     */
    public function test_generated_code_is_the_configured_length_and_unambiguous(): void
    {
        Config::set('login-codes.code_length', 8);

        $organization = Organization::factory()->create();
        $user = User::factory()->standard()->forOrganization($organization)->create();

        $service = app(LoginCodeService::class);

        for ($i = 0; $i < 25; $i++) {
            $code = $service->generate($user)->code;

            $this->assertSame(8, strlen($code), "Generated code [{$code}] was not 8 characters.");
            $this->assertMatchesRegularExpression('/^[ABCDEFGHJKLMNPQRTUVWXYZ2346789]+$/', $code);
        }
    }
}
