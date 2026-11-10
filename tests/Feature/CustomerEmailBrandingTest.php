<?php

namespace Tests\Feature;

use App\Actions\InviteCustomerUser;
use App\Mail\UserNotification;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * TASK-506 (drive 003). A customer is the organization's customer, but the
 * portal welcome arrived as unstyled plain text titled "Welcome to the Rupkeep
 * Customer Portal" from "PilotCarCMS", and the sign-in email said "Sign in to
 * Rupkeep".
 */
class CustomerEmailBrandingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.name' => 'PilotCarCMS']);

        $this->organization = Organization::factory()->create(['name' => 'Casco Bay Pilot Car']);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'JLS Pilot Car']);
    }

    public function test_the_portal_welcome_is_branded_html_from_the_organization(): void
    {
        Mail::fake();

        app(InviteCustomerUser::class)->invite($this->customer, 'richard@example.com', 'Richard');

        Mail::assertSent(UserNotification::class, function (UserNotification $mail) {
            $envelope = $mail->envelope();
            $html = $mail->render();

            return $mail->hasTo('richard@example.com')
                && $envelope->from->name === 'Casco Bay Pilot Car'
                && str_contains($envelope->subject, 'Casco Bay Pilot Car')
                && ! str_contains($envelope->subject, 'Rupkeep')
                && str_contains($html, '<table')
                && str_contains($html, 'Casco Bay Pilot Car')
                && str_contains($html, route('login-code.create'))
                && ! str_contains($html, 'Rupkeep')
                && ! str_contains($html, 'PilotCarCMS');
        });
    }

    public function test_the_sign_in_email_names_the_organization(): void
    {
        Mail::fake();

        User::factory()->asCustomer($this->customer)->create(['email' => 'richard@example.com']);

        $this->post(route('login-code.store'), ['email' => 'richard@example.com']);

        Mail::assertSent(UserNotification::class, function (UserNotification $mail) {
            $envelope = $mail->envelope();
            $html = $mail->render();

            return $envelope->from->name === 'Casco Bay Pilot Car'
                && $envelope->subject === 'Your Casco Bay Pilot Car sign-in link'
                && str_contains($html, 'Sign in to Casco Bay Pilot Car')
                && ! str_contains($html, 'Rupkeep');
        });
    }

    public function test_other_notices_are_sent_in_the_organization_name(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'email' => 'driver@example.com',
            'notification_address' => null,
        ]);

        \App\Actions\SendUserNotification::sendSmtpOrSms(false, $user->email, 'hello', 'Subject', $user->organization->name);

        Mail::assertSent(UserNotification::class, fn (UserNotification $mail) => $mail->envelope()->from->name === 'Casco Bay Pilot Car');
    }
}
