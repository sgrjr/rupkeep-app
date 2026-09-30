<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\LoginCodeController;
use App\Models\Organization;
use App\Models\User;
use App\Services\LoginCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * TASK-441 regression. The passwordless flow forwarded any ?redirect= after
 * a genuine sign-in, redeemed a code on the code alone, keyed the request
 * limiter on email+IP, and threw on a code whose account had been deleted.
 */
class LoginCodeHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->standard()->forOrganization(Organization::factory()->create())->create();
    }

    public function test_only_same_site_paths_are_accepted_as_redirects(): void
    {
        $this->assertSame('/my/jobs/5', LoginCodeController::safeRedirect('/my/jobs/5'));
        $this->assertNull(LoginCodeController::safeRedirect('https://elsewhere.example/phish'));
        $this->assertNull(LoginCodeController::safeRedirect('//elsewhere.example/phish'));
        $this->assertNull(LoginCodeController::safeRedirect('javascript:alert(1)'));
        $this->assertNull(LoginCodeController::safeRedirect('my/jobs'));
        $this->assertNull(LoginCodeController::safeRedirect(''));
        $this->assertNull(LoginCodeController::safeRedirect(null));
    }

    public function test_an_off_site_redirect_is_ignored_after_sign_in(): void
    {
        $user = $this->staff();
        $code = app(LoginCodeService::class)->generate($user);

        $this->get(route('login-code.verify-form', ['redirect' => 'https://elsewhere.example/phish']));

        $this->post(route('login-code.verify'), [
            'code' => $code->code,
            'email' => $user->email,
            'redirect' => 'https://elsewhere.example/phish',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_same_site_redirect_is_honoured(): void
    {
        $user = $this->staff();
        $code = app(LoginCodeService::class)->generate($user);

        $this->post(route('login-code.verify'), [
            'code' => $code->code,
            'email' => $user->email,
            'redirect' => '/my/profile',
        ])->assertRedirect('/my/profile');
    }

    public function test_a_code_is_bound_to_the_email_it_was_issued_to(): void
    {
        $user = $this->staff();
        $other = $this->staff();
        $code = app(LoginCodeService::class)->generate($user);

        $this->from(route('login-code.verify-form'))
            ->post(route('login-code.verify'), ['code' => $code->code, 'email' => $other->email])
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->from(route('login-code.verify-form'))
            ->post(route('login-code.verify'), ['code' => $code->code])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // Still live: the wrong-email attempts did not burn it.
        $this->post(route('login-code.verify'), ['code' => $code->code, 'email' => strtoupper($user->email)])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_request_limit_is_per_address_regardless_of_ip(): void
    {
        Mail::fake();
        $user = $this->staff();

        foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->post(route('login-code.store'), ['email' => $user->email])
                ->assertSessionHasNoErrors();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.4'])
            ->post(route('login-code.store'), ['email' => $user->email])
            ->assertStatus(429);

        Mail::assertSentCount(3);
    }

    public function test_a_code_for_a_deleted_account_is_simply_invalid(): void
    {
        $user = $this->staff();
        $code = app(LoginCodeService::class)->generate($user);
        $email = $user->email;
        $user->delete();

        $this->from(route('login-code.verify-form'))
            ->post(route('login-code.verify'), ['code' => $code->code, 'email' => $email])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }
}
