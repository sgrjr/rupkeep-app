<?php

namespace Tests\Feature;

use App\Mail\UserNotification;
use App\Models\Customer;
use App\Models\LoginCode;
use App\Models\Organization;
use App\Models\User;
use App\Services\LoginCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * TASK-319 — one-time email sign-in links.
 *
 * The request form at /login-code issues a single login_codes row carrying two
 * secrets: the typed `code` and the emailed `link_token`. They share one
 * `used_at`, so redeeming either retires both.
 *
 * The link is redeemed in two steps on purpose: GET /login-link/{token} only
 * renders a confirm button, and POST spends the token. See
 * test_get_does_not_spend_the_token for why.
 */
class LoginLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(): User
    {
        $organization = Organization::factory()->create();

        return User::factory()->standard()->forOrganization($organization)->create();
    }

    public function test_requested_email_carries_a_working_link_and_the_matching_code(): void
    {
        Mail::fake();

        $user = $this->staff();

        $this->post(route('login-code.store'), ['email' => $user->email])
            ->assertSessionHasNoErrors();

        $code = LoginCode::firstOrFail();

        Mail::assertSent(UserNotification::class, function (UserNotification $mail) use ($user, $code) {
            return $mail->hasTo($user->email)
                && $mail->viewData['url'] === route('login-link', $code->link_token)
                && $mail->viewData['code'] === $code->code;
        });
    }

    /**
     * The reason the flow has an interstitial at all. Corporate mail scanners
     * (Outlook Safe Links and friends) issue a GET against every URL in an
     * email. If that GET signed the user in, the scanner would spend the
     * single-use token and the human would arrive to a dead link.
     */
    public function test_get_does_not_spend_the_token(): void
    {
        $user = $this->staff();
        $code = app(LoginCodeService::class)->generate($user);

        // Three prefetches, as an aggressive scanner might.
        for ($i = 0; $i < 3; $i++) {
            $this->get(route('login-link', $code->link_token))->assertOk();
        }

        $this->assertGuest();
        $this->assertFalse($code->fresh()->isUsed());

        // The human then clicks through and still gets in.
        $this->post(route('login-link.confirm', $code->link_token))
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_confirm_page_names_the_account_being_signed_into(): void
    {
        $user = $this->staff();
        $code = app(LoginCodeService::class)->generate($user);

        $this->get(route('login-link', $code->link_token))
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee($user->email)
            ->assertSee(route('login-link.confirm', $code->link_token));
    }

    public function test_confirming_signs_the_user_in_and_burns_the_token(): void
    {
        $user = $this->staff();
        $code = app(LoginCodeService::class)->generate($user);

        $this->post(route('login-link.confirm', $code->link_token))
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($code->fresh()->isUsed());
    }

    public function test_a_second_confirm_is_rejected(): void
    {
        $user = $this->staff();
        $code = app(LoginCodeService::class)->generate($user);

        $this->post(route('login-link.confirm', $code->link_token));
        $this->post(route('logout'));

        $this->post(route('login-link.confirm', $code->link_token))
            ->assertRedirect(route('login-code.create'))
            ->assertSessionHas('warning');

        $this->assertGuest();
    }

    public function test_spent_link_shows_the_request_form_not_the_confirm_page(): void
    {
        $user = $this->staff();
        $code = app(LoginCodeService::class)->generate($user);

        $this->post(route('login-link.confirm', $code->link_token));
        $this->post(route('logout'));

        $this->get(route('login-link', $code->link_token))
            ->assertRedirect(route('login-code.create'))
            ->assertSessionHas('warning');
    }

    public function test_expired_link_is_rejected(): void
    {
        $user = $this->staff();

        $code = LoginCode::factory()->expired()->create(['user_id' => $user->id]);

        $this->get(route('login-link', $code->link_token))
            ->assertRedirect(route('login-code.create'));

        $this->post(route('login-link.confirm', $code->link_token))
            ->assertRedirect(route('login-code.create'));

        $this->assertGuest();
    }

    public function test_unknown_token_is_rejected(): void
    {
        $this->get(route('login-link', str_repeat('z', 64)))
            ->assertRedirect(route('login-code.create'));

        $this->post(route('login-link.confirm', str_repeat('z', 64)))
            ->assertRedirect(route('login-code.create'));

        $this->assertGuest();
    }

    /**
     * The link and the code are two doors into one row — walking through
     * either one locks the other.
     */
    public function test_typing_the_code_invalidates_the_link(): void
    {
        $user = $this->staff();
        $code = app(LoginCodeService::class)->generate($user);

        $this->post(route('login-code.verify'), ['code' => $code->code]);
        $this->assertAuthenticatedAs($user);
        $this->post(route('logout'));

        $this->get(route('login-link', $code->link_token))
            ->assertRedirect(route('login-code.create'));

        $this->assertGuest();
    }

    public function test_confirming_the_link_invalidates_the_code(): void
    {
        $user = $this->staff();
        $code = app(LoginCodeService::class)->generate($user);

        $this->post(route('login-link.confirm', $code->link_token));
        $this->post(route('logout'));

        $this->from(route('login-code.verify-form'))
            ->post(route('login-code.verify'), ['code' => $code->code])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_manager_lands_on_the_jobs_index(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->forOrganization($organization)->create();

        $code = app(LoginCodeService::class)->generate($manager);

        $this->post(route('login-link.confirm', $code->link_token))
            ->assertRedirect(route('my.jobs.index'));

        $this->assertAuthenticatedAs($manager);
    }

    public function test_customer_lands_on_the_portal_invoices(): void
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->for($organization)->create();
        $customerUser = User::factory()->asCustomer($customer)->create();

        $code = app(LoginCodeService::class)->generate($customerUser);

        $this->post(route('login-link.confirm', $code->link_token))
            ->assertRedirect(route('customer.invoices.index'));

        $this->assertAuthenticatedAs($customerUser);
    }

    /**
     * SendUserNotification prefers a configured SMS-gateway address over email.
     * A sign-in URL sent through a carrier gateway arrives truncated, so this
     * mail must go to the real inbox regardless.
     */
    public function test_link_is_emailed_even_when_an_sms_gateway_is_configured(): void
    {
        Mail::fake();

        $organization = Organization::factory()->create();
        $user = User::factory()->standard()->forOrganization($organization)->create([
            'email' => 'driver@example.com',
            'notification_address' => '2075551234@vtext.com',
        ]);

        $this->post(route('login-code.store'), ['email' => $user->email]);

        Mail::assertSent(UserNotification::class, fn (UserNotification $mail) => $mail->hasTo('driver@example.com'));
    }

    /**
     * Confirmed with the customer: three requests per email per hour.
     */
    public function test_fourth_request_within_an_hour_is_throttled(): void
    {
        Mail::fake();

        $user = $this->staff();

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('login-code.store'), ['email' => $user->email])
                ->assertSessionHasNoErrors();
        }

        $this->post(route('login-code.store'), ['email' => $user->email])
            ->assertStatus(429);

        Mail::assertSentCount(3);
    }
}
