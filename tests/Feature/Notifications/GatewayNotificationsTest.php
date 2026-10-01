<?php

namespace Tests\Feature\Notifications;

use App\Events\InvoiceFlagged;
use App\Events\InvoiceReady;
use App\Events\JobAssigned;
use App\Events\LogCompleted;
use App\Listeners\SendInvoiceFlaggedNotification;
use App\Listeners\SendInvoiceReadyNotification;
use App\Listeners\SendJobAssignedNotification;
use App\Listeners\SendLogCompletedNotification;
use App\Mail\UserNotification;
use App\Mail\UserNotificationSms;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Invoice;
use App\Models\InvoiceComment;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use App\Models\Vehicle;
use App\Rules\NotificationAddress as NotificationAddressRule;
use App\Support\NotificationAddress;
use App\Support\SmsMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * TASK-468. Office notifications (invoice ready, invoice flagged, log
 * completed, maintenance digest) sent the HTML email to a carrier gateway
 * address; gateway addresses were stored as typed; dead carriers were still
 * offered; recipients with no address were skipped silently.
 */
class GatewayNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private const GATEWAY = '2075550101@vtext.com';

    private Organization $organization;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create(['name' => 'Northwind Escorts']);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function gatewayManager(): User
    {
        return User::factory()->manager()->create([
            'organization_id' => $this->organization->id,
            'email' => 'manager@example.test',
            'notification_address' => self::GATEWAY,
        ]);
    }

    private function emailManager(): User
    {
        return User::factory()->manager()->create([
            'organization_id' => $this->organization->id,
            'email' => 'office@example.test',
            'notification_address' => null,
        ]);
    }

    private function invoice(): Invoice
    {
        return Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'values' => ['title' => 'INVOICE', 'total' => 1234.5],
        ]);
    }

    private function assertOneShortText(string $to, string $contains): void
    {
        Mail::assertSent(UserNotificationSms::class, fn (UserNotificationSms $m) => $m->hasTo($to)
            && str_contains($m->body(), $contains)
            && mb_strlen($m->body()) <= SmsMessage::LIMIT);
        Mail::assertNotSent(UserNotification::class, fn (UserNotification $m) => $m->hasTo($to));
    }

    public function test_invoice_ready_texts_a_gateway_manager_and_emails_a_mailbox_manager(): void
    {
        $sms = $this->gatewayManager();
        $email = $this->emailManager();
        $invoice = $this->invoice();

        Mail::fake();

        (new SendInvoiceReadyNotification())->handle(new InvoiceReady($invoice));

        $this->assertOneShortText(self::GATEWAY, (string) $invoice->invoice_number);
        Mail::assertSent(UserNotification::class, fn (UserNotification $m) => $m->hasTo('office@example.test'));
    }

    public function test_invoice_flagged_texts_a_gateway_manager(): void
    {
        $this->gatewayManager();
        $invoice = $this->invoice();
        $customerUser = User::factory()->asCustomer($this->customer)->create();
        $comment = InvoiceComment::create([
            'invoice_id' => $invoice->id,
            'user_id' => $customerUser->id,
            'body' => 'The toll amount looks wrong.',
            'is_flagged' => true,
            'flagged_at' => now(),
        ]);

        Mail::fake();

        (new SendInvoiceFlaggedNotification())->handle(new InvoiceFlagged($comment));

        $this->assertOneShortText(self::GATEWAY, 'flagged');
    }

    public function test_log_completed_texts_a_gateway_manager(): void
    {
        $this->gatewayManager();
        $job = PilotCarJob::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'job_no' => 'JOB-468']);
        $driver = User::factory()->standard()->create(['organization_id' => $this->organization->id, 'name' => 'Dana Driver']);
        $log = UserLog::create([
            'job_id' => $job->id,
            'car_driver_id' => $driver->id,
            'organization_id' => $this->organization->id,
            'vehicle_position' => 'lead',
            'approval_status' => 'confirmed',
            'completed_at' => now(),
            'completed_by_id' => $driver->id,
        ]);

        Mail::fake();

        (new SendLogCompletedNotification())->handle(new LogCompleted($log, $driver));

        $this->assertOneShortText(self::GATEWAY, 'JOB-468');
    }

    public function test_the_maintenance_digest_texts_a_gateway_manager(): void
    {
        $this->gatewayManager();
        Vehicle::create([
            'organization_id' => $this->organization->id,
            'name' => 'Car 001',
            'next_oil_change_due_at' => now()->subDays(3)->toDateString(),
        ]);

        Mail::fake();

        $this->artisan('vehicles:send-maintenance-reminders')->assertSuccessful();

        $this->assertOneShortText(self::GATEWAY, 'maintenance due');
    }

    public function test_gateway_addresses_are_stored_normalised(): void
    {
        $user = User::factory()->standard()->create([
            'organization_id' => $this->organization->id,
            'notification_address' => ' (207) 555-0101@MMS.USCC.NET ',
        ]);

        $this->assertSame('2075550101@mms.uscc.net', $user->fresh()->notification_address);

        $contact = CustomerContact::create([
            'customer_id' => $this->customer->id,
            'organization_id' => $this->organization->id,
            'name' => 'Truck Driver',
            'notification_address' => '+1 207.555.0102@vtext.com',
        ]);

        $this->assertSame('2075550102@vtext.com', $contact->fresh()->notification_address);

        // A mailbox is left alone apart from trimming.
        $this->assertSame('Mary.Reynolds@example.test', NotificationAddress::normalize(' Mary.Reynolds@example.test '));
        $this->assertNull(NotificationAddress::normalize('   '));
    }

    public function test_the_rule_accepts_formatted_phones_and_mailboxes_and_refuses_junk(): void
    {
        $passes = fn ($value) => Validator::make(['a' => $value], ['a' => ['nullable', new NotificationAddressRule]])->passes();

        $this->assertTrue($passes('(207) 555-0101@mms.uscc.net'));
        $this->assertTrue($passes('2075550101@vtext.com'));
        $this->assertTrue($passes('mary@example.test'));
        $this->assertTrue($passes(''));
        $this->assertFalse($passes('555-0101@vtext.com'), 'seven digits is not a phone number');
        $this->assertFalse($passes('not an address'));
        $this->assertFalse($passes('mary@'));
    }

    public function test_the_user_form_refuses_a_bad_gateway_address(): void
    {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)->post(route('my.users.store'), [
            'name' => 'New Driver',
            'email' => 'new.driver@example.test',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
            'organization_role' => User::ROLE_EMPLOYEE_STANDARD,
            'notification_address' => '555-0101@vtext.com',
        ])->assertSessionHasErrors('notification_address');

        $this->assertNull(User::where('email', 'new.driver@example.test')->first());
    }

    public function test_dead_carrier_gateways_are_no_longer_offered(): void
    {
        $providers = config('sms_gateways.providers');

        $this->assertArrayNotHasKey('att', $providers);
        $this->assertArrayNotHasKey('tmobile', $providers);
        $this->assertArrayNotHasKey('sprint', $providers);
        $this->assertArrayHasKey('verizon', $providers);
        $this->assertArrayHasKey('uscc', $providers);
    }

    public function test_a_recipient_with_nowhere_to_send_is_logged_not_silently_skipped(): void
    {
        $job = PilotCarJob::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'scheduled_pickup_at' => now()->addDay()]);
        $driver = User::factory()->standard()->create(['organization_id' => $this->organization->id, 'notification_address' => null]);
        $driver->forceFill(['email' => ''])->saveQuietly();

        Mail::fake();
        Log::spy();

        (new SendJobAssignedNotification())->handle(new JobAssigned($job, $driver->fresh()));

        Mail::assertNothingSent();
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'nothing sent'))->once();
    }

    public function test_gateway_texts_do_not_go_through_the_api_path_by_default(): void
    {
        $this->assertSame('mail', config('mail.sms_gateway_transport'));
    }
}
