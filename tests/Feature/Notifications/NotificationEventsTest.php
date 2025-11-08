<?php

namespace Tests\Feature\Notifications;

use App\Events\InvoiceFlagged;
use App\Events\InvoiceReady;
use App\Events\JobAssigned;
use App\Mail\UserNotification;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Invoice;
use App\Models\InvoiceComment;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_assigned_event_dispatched_when_user_log_created(): void
    {
        Event::fake([JobAssigned::class]);

        $state = $this->createJobEnvironment();

        UserLog::create([
            'job_id' => $state['job']->id,
            'car_driver_id' => $state['driver']->id,
            'truck_driver_id' => $state['truckDriver']->id,
            'vehicle_id' => $state['vehicle']->id,
            'organization_id' => $state['organization']->id,
            'pretrip_check' => true,
            'truck_no' => 'TRK-1',
            'trailer_no' => 'TRL-1',
            'start_mileage' => 100,
            'end_mileage' => 250,
            'start_job_mileage' => 0,
            'end_job_mileage' => 150,
            'load_canceled' => false,
            'extra_charge' => 0,
            'is_deadhead' => false,
            'extra_load_stops_count' => 0,
            'wait_time_hours' => 0,
            'tolls' => 0,
            'gas' => 0,
            'hotel' => 0,
            'memo' => 'Test log',
            'maintenance_memo' => null,
            'started_at' => Carbon::now(),
            'ended_at' => Carbon::now()->addHours(4),
            'billable_miles' => 150,
        ]);

        Event::assertDispatched(JobAssigned::class, function (JobAssigned $event) use ($state) {
            return $event->job->is($state['job']) && $event->driver->is($state['driver']);
        });
    }

    public function test_job_assigned_listener_sends_mail(): void
    {
        Mail::fake();

        $state = $this->createJobEnvironment();

        event(new JobAssigned($state['job'], $state['driver']));

        Mail::assertSent(UserNotification::class, function (UserNotification $mail) use ($state) {
            return $mail->hasTo($state['driver']->notification_address)
                && str_contains($mail->subject, 'Job Assigned');
        });
    }

    public function test_invoice_ready_event_dispatched_when_invoice_created(): void
    {
        Event::fake([InvoiceReady::class]);

        $state = $this->createJobEnvironment();

        $manager = User::factory()->manager()->create([
            'organization_id' => $state['organization']->id,
            'notification_address' => 'manager@example.com',
        ]);

        $secondaryManager = User::factory()->manager()->create([
            'organization_id' => $state['organization']->id,
            'notification_address' => 'manager2@example.com',
        ]);

        $secondaryManager = User::factory()->manager()->create([
            'organization_id' => $state['organization']->id,
            'notification_address' => 'manager2@example.com',
        ]);

        $this->actingAs($manager);

        $response = $this->post(route('my.invoices.store'), [
            'invoice_this' => [$state['job']->id],
        ]);

        $response->assertStatus(302);

        Event::assertDispatched(InvoiceReady::class, function (InvoiceReady $event) use ($state) {
            return $event->invoice->pilot_car_job_id === $state['job']->id;
        });
    }

    public function test_invoice_ready_listener_sends_mail_to_managers_and_customer_users(): void
    {
        Mail::fake();

        $state = $this->createJobEnvironment();

        $manager = User::factory()->manager()->create([
            'organization_id' => $state['organization']->id,
            'notification_address' => 'manager@example.com',
        ]);

        $customerUser = User::factory()->create([
            'organization_id' => $state['organization']->id,
            'customer_id' => $state['customer']->id,
            'organization_role' => User::ROLE_CUSTOMER,
            'notification_address' => 'customer@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'organization_id' => $state['organization']->id,
            'customer_id' => $state['customer']->id,
            'pilot_car_job_id' => $state['job']->id,
            'values' => [
                'title' => 'INVOICE',
                'total' => 325.50,
            ],
        ]);

        event(new InvoiceReady($invoice));

        Mail::assertSent(UserNotification::class, function (UserNotification $mail) {
            return $mail->hasTo('manager@example.com')
                && str_contains($mail->subject, 'Invoice Ready');
        });

        Mail::assertSent(UserNotification::class, function (UserNotification $mail) {
            return $mail->hasTo('customer@example.com')
                && str_contains($mail->subject, 'Invoice Ready');
        });
    }

    public function test_invoice_flagged_event_dispatched_when_comment_flagged(): void
    {
        Event::fake([InvoiceFlagged::class]);

        $state = $this->createJobEnvironment();

        $author = User::factory()->manager()->create([
            'organization_id' => $state['organization']->id,
        ]);

        $invoice = Invoice::factory()->create([
            'organization_id' => $state['organization']->id,
            'customer_id' => $state['customer']->id,
            'pilot_car_job_id' => $state['job']->id,
        ]);

        $comment = InvoiceComment::create([
            'invoice_id' => $invoice->id,
            'user_id' => $author->id,
            'body' => 'Please review charges.',
            'is_flagged' => false,
        ]);

        $comment->flag();

        Event::assertDispatched(InvoiceFlagged::class, function (InvoiceFlagged $event) use ($comment) {
            return $event->comment->is($comment->fresh());
        });
    }

    public function test_invoice_flagged_listener_sends_mail(): void
    {
        Mail::fake();

        $state = $this->createJobEnvironment();

        $managerAuthor = User::factory()->manager()->create([
            'organization_id' => $state['organization']->id,
            'notification_address' => 'manager@example.com',
        ]);

        $managerRecipient = User::factory()->manager()->create([
            'organization_id' => $state['organization']->id,
            'notification_address' => 'manager2@example.com',
        ]);

        $customerUser = User::factory()->create([
            'organization_id' => $state['organization']->id,
            'customer_id' => $state['customer']->id,
            'organization_role' => User::ROLE_CUSTOMER,
            'notification_address' => 'customer@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'organization_id' => $state['organization']->id,
            'customer_id' => $state['customer']->id,
            'pilot_car_job_id' => $state['job']->id,
        ]);

        $comment = InvoiceComment::create([
            'invoice_id' => $invoice->id,
            'user_id' => $managerAuthor->id,
            'body' => 'Customer raised a concern about tolls.',
            'is_flagged' => true,
        ]);

        event(new InvoiceFlagged($comment));

        $mails = Mail::sent(UserNotification::class);

        $this->assertNotEmpty($mails, 'Expected at least one invoice flagged notification to be sent.');

        $recipients = $mails
            ->flatMap(fn (UserNotification $mail) => collect($mail->to)->pluck('address'))
            ->unique()
            ->values()
            ->all();

        $this->assertContains('manager2@example.com', $recipients, 'Expected manager notification to be sent.');
        $this->assertContains('customer@example.com', $recipients, 'Expected customer notification to be sent.');
    }

    private function createJobEnvironment(): array
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->for($organization)->create();

        $driver = User::factory()->standard()->create([
            'organization_id' => $organization->id,
            'notification_address' => 'driver@example.com',
        ]);

        $truckDriver = CustomerContact::create([
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'name' => 'Truck Driver',
            'phone' => '555-1234',
            'memo' => null,
            'email' => 'truck.driver@example.com',
        ]);

        $vehicle = Vehicle::create([
            'name' => 'Escort 1',
            'organization_id' => $organization->id,
            'odometer' => 1000,
            'odometer_updated_at' => Carbon::now(),
        ]);

        $job = PilotCarJob::create([
            'job_no' => 'JOB-'.uniqid(),
            'customer_id' => $customer->id,
            'scheduled_pickup_at' => Carbon::now()->toDateTimeString(),
            'scheduled_delivery_at' => Carbon::now()->addDay()->toDateTimeString(),
            'load_no' => 'LOAD-1',
            'pickup_address' => '123 Pickup St',
            'delivery_address' => '456 Delivery Ave',
            'check_no' => 'CHK-1',
            'invoice_paid' => false,
            'invoice_no' => 'INV-'.uniqid(),
            'rate_code' => 'per_mile_rate',
            'rate_value' => 2.50,
            'organization_id' => $organization->id,
        ]);

        return [
            'organization' => $organization,
            'customer' => $customer,
            'driver' => $driver,
            'truckDriver' => $truckDriver,
            'vehicle' => $vehicle,
            'job' => $job,
        ];
    }
}


