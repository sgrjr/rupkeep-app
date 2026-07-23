<?php

namespace Tests\Feature\Notifications;

use App\Events\JobStatusChanged;
use App\Livewire\CancelJob;
use App\Mail\UserNotification;
use App\Mail\UserNotificationSms;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use App\Models\Vehicle;
use App\Notifications\JobUpdate;
use App\Support\SmsMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class JobStatusChangeNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const GATEWAY_ADDRESS = '2075551234@mms.uscc.net';

    public function test_active_to_completed_transition_dispatches_event(): void
    {
        Event::fake([JobStatusChanged::class]);

        $state = $this->createJobEnvironment();

        $this->actingAs($state['manager']);

        $response = $this->post(route('my.invoices.store'), [
            'invoice_this' => [$state['job']->id],
        ]);

        $response->assertStatus(302);

        Event::assertDispatched(JobStatusChanged::class, function (JobStatusChanged $event) use ($state) {
            return $event->job->is($state['job'])
                && $event->from === 'ACTIVE'
                && $event->to === 'COMPLETED';
        });
    }

    public function test_non_status_save_does_not_dispatch_event(): void
    {
        Event::fake([JobStatusChanged::class]);

        $state = $this->createJobEnvironment();

        $state['job']->update(['memo' => 'Just editing a note, not the status']);

        Event::assertNotDispatched(JobStatusChanged::class);
    }

    public function test_fire_if_changed_only_dispatches_when_status_differs(): void
    {
        Event::fake([JobStatusChanged::class]);

        $state = $this->createJobEnvironment();
        $job = $state['job'];

        // Same status in and out -> nothing.
        JobStatusChanged::fireIfChanged($job, $job->status);
        Event::assertNotDispatched(JobStatusChanged::class);

        // Give the job an invoice so its derived status becomes COMPLETED.
        Invoice::factory()->create([
            'organization_id' => $state['organization']->id,
            'customer_id' => $state['customer']->id,
            'pilot_car_job_id' => $job->id,
            'values' => ['title' => 'INVOICE', 'total' => 100],
        ]);

        JobStatusChanged::fireIfChanged($job, 'ACTIVE');

        Event::assertDispatched(JobStatusChanged::class, function (JobStatusChanged $event) use ($job) {
            return $event->job->is($job) && $event->from === 'ACTIVE' && $event->to === 'COMPLETED';
        });
    }

    public function test_cancellation_dispatches_status_changed_event(): void
    {
        Event::fake([JobStatusChanged::class, \App\Events\JobWasCanceled::class]);

        $state = $this->createJobEnvironment();

        $this->actingAs($state['manager']);

        Livewire::test(CancelJob::class, ['job' => $state['job']])
            ->set('cancellationReason', 'Customer canceled the load')
            ->set('cancellationType', 'cancel_without_billing')
            ->call('cancel');

        Event::assertDispatched(JobStatusChanged::class, function (JobStatusChanged $event) use ($state) {
            return $event->job->is($state['job'])
                && $event->from === 'ACTIVE'
                && str_starts_with($event->to, 'CANCELLED');
        });
    }

    public function test_listener_notifies_sms_driver_within_160_chars(): void
    {
        $state = $this->createJobEnvironment();
        $driver = $this->assignDriver($state, self::GATEWAY_ADDRESS);

        // Fake only after the assignment (log creation) side effects have run.
        Mail::fake();
        Notification::fake();

        event(new JobStatusChanged($state['job'], 'ACTIVE', 'COMPLETED'));

        $url = route('my.jobs.show', ['job' => $state['job']->id]);

        Mail::assertSent(UserNotificationSms::class, function (UserNotificationSms $mail) use ($url) {
            return $mail->hasTo(self::GATEWAY_ADDRESS)
                && mb_strlen($mail->body()) <= SmsMessage::LIMIT
                && str_contains($mail->body(), $url);
        });

        Notification::assertSentTo($driver, JobUpdate::class);
    }

    public function test_listener_sends_full_email_to_mailbox_driver(): void
    {
        $state = $this->createJobEnvironment();
        // No gateway address -> a real mailbox, so it gets the full email.
        $driver = $this->assignDriver($state, null, 'mailbox-driver@example.com');

        Mail::fake();

        event(new JobStatusChanged($state['job'], 'ACTIVE', 'COMPLETED'));

        Mail::assertSent(UserNotification::class, fn (UserNotification $mail) => $mail->hasTo('mailbox-driver@example.com'));
        Mail::assertNotSent(UserNotificationSms::class);
    }

    public function test_listener_skips_cancellation_transitions(): void
    {
        $state = $this->createJobEnvironment();
        $this->assignDriver($state, self::GATEWAY_ADDRESS);

        Mail::fake();
        Notification::fake();

        // Both directions of a cancelled transition are owned by the dedicated
        // cancel / uncancel listeners, so this one must stay silent.
        event(new JobStatusChanged($state['job'], 'ACTIVE', 'CANCELLED_NO_GO'));
        event(new JobStatusChanged($state['job'], 'CANCELLED_NO_GO', 'ACTIVE'));

        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    /**
     * Attach a driver to the job via a UserLog and return the driver.
     */
    private function assignDriver(array $state, ?string $notificationAddress, string $email = 'driver@example.com'): User
    {
        $driver = User::factory()->standard()->create([
            'organization_id' => $state['organization']->id,
            'email' => $email,
            'notification_address' => $notificationAddress,
        ]);

        UserLog::create([
            'job_id' => $state['job']->id,
            'car_driver_id' => $driver->id,
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

        return $driver;
    }

    private function createJobEnvironment(): array
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->for($organization)->create();

        $manager = User::factory()->manager()->create([
            'organization_id' => $organization->id,
            'notification_address' => 'manager@example.com',
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
            'manager' => $manager,
            'truckDriver' => $truckDriver,
            'vehicle' => $vehicle,
            'job' => $job,
        ];
    }
}
