<?php

namespace Tests\Feature\Notifications;

use App\Events\JobAssigned;
use App\Events\JobUnassigned;
use App\Livewire\ShowPilotCarJob;
use App\Mail\UserNotification;
use App\Mail\UserNotificationSms;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use App\Models\Vehicle;
use App\Support\SmsMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-459. Assigning a driver from the job page sent two messages: a
 * hand-built one from ShowPilotCarJob::assignJob and the JobAssigned
 * listener's. Moving a log to another driver told the new driver and never
 * the old one, who kept a job they thought was theirs.
 */
class AssignmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const GATEWAY_A = '2075550001@vtext.com';

    private const GATEWAY_B = '2075550002@vtext.com';

    private Organization $organization;

    private User $manager;

    private PilotCarJob $job;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->organization = Organization::factory()->create();
        $this->manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $contact = CustomerContact::create(['customer_id' => $customer->id, 'organization_id' => $this->organization->id, 'name' => 'Truck Driver', 'phone' => '555-1234']);
        $this->vehicle = Vehicle::create(['name' => 'Escort 1', 'organization_id' => $this->organization->id, 'odometer' => 1000, 'odometer_updated_at' => now()]);

        $this->job = PilotCarJob::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'default_truck_driver_id' => $contact->id,
            'job_no' => 'JOB-459',
            'scheduled_pickup_at' => now()->addDays(2),
        ]);
    }

    private function smsDriver(string $gateway, string $email): User
    {
        return User::factory()->standard()->create([
            'organization_id' => $this->organization->id,
            'email' => $email,
            'notification_address' => $gateway,
        ]);
    }

    private function emailDriver(string $email): User
    {
        return User::factory()->standard()->create([
            'organization_id' => $this->organization->id,
            'email' => $email,
            'notification_address' => null,
        ]);
    }

    public function test_assigning_a_driver_from_the_job_page_texts_them_exactly_once(): void
    {
        $driver = $this->smsDriver(self::GATEWAY_A, 'a@example.test');

        Mail::fake();

        Livewire::actingAs($this->manager)
            ->test(ShowPilotCarJob::class, ['job' => $this->job->id])
            ->set('assignment.car_driver_id', $driver->id)
            ->set('assignment.vehicle_id', $this->vehicle->id)
            ->set('assignment.vehicle_position', 'lead')
            ->call('assignJob')
            ->assertHasNoErrors();

        $this->assertSame(1, UserLog::where('job_id', $this->job->id)->count());

        Mail::assertSent(UserNotificationSms::class, 1);
        Mail::assertSent(UserNotificationSms::class, fn (UserNotificationSms $mail) => $mail->hasTo(self::GATEWAY_A)
            && str_contains($mail->body(), 'JOB-459')
            && str_contains($mail->body(), 'Confirm'));
        Mail::assertNotSent(UserNotification::class);
    }

    public function test_moving_the_log_to_another_driver_tells_the_previous_one(): void
    {
        $previous = $this->smsDriver(self::GATEWAY_A, 'a@example.test');
        $next = $this->smsDriver(self::GATEWAY_B, 'b@example.test');

        $log = UserLog::create([
            'job_id' => $this->job->id,
            'car_driver_id' => $previous->id,
            'vehicle_id' => $this->vehicle->id,
            'organization_id' => $this->organization->id,
            'vehicle_position' => 'lead',
            'approval_status' => 'pending',
        ]);

        Mail::fake();
        Event::fake([JobAssigned::class, JobUnassigned::class]);

        $log->update(['car_driver_id' => $next->id]);

        Event::assertDispatched(JobUnassigned::class, fn (JobUnassigned $e) => $e->previousDriver->is($previous) && $e->job->is($this->job) && $e->log?->is($log));
        Event::assertDispatched(JobAssigned::class, fn (JobAssigned $e) => $e->driver->is($next));
        Event::assertNotDispatched(JobAssigned::class, fn (JobAssigned $e) => $e->driver->is($previous));
    }

    public function test_the_previous_driver_gets_a_text_that_fits_one_sms(): void
    {
        $previous = $this->smsDriver(self::GATEWAY_A, 'a@example.test');
        $next = $this->smsDriver(self::GATEWAY_B, 'b@example.test');

        $log = UserLog::create([
            'job_id' => $this->job->id,
            'car_driver_id' => $previous->id,
            'vehicle_id' => $this->vehicle->id,
            'organization_id' => $this->organization->id,
            'vehicle_position' => 'lead',
            'approval_status' => 'pending',
        ]);

        Mail::fake();

        $log->update(['car_driver_id' => $next->id]);

        Mail::assertSent(UserNotificationSms::class, fn (UserNotificationSms $mail) => $mail->hasTo(self::GATEWAY_A)
            && str_contains($mail->body(), 'JOB-459')
            && str_contains($mail->body(), 'no longer assigned')
            && mb_strlen($mail->body()) <= SmsMessage::LIMIT);

        Mail::assertSent(UserNotificationSms::class, fn (UserNotificationSms $mail) => $mail->hasTo(self::GATEWAY_B)
            && str_contains($mail->body(), 'assigned')
            && ! str_contains($mail->body(), 'no longer'));
    }

    public function test_an_email_driver_gets_the_removal_by_email(): void
    {
        $previous = $this->emailDriver('previous@example.test');
        $next = $this->emailDriver('next@example.test');

        $log = UserLog::create([
            'job_id' => $this->job->id,
            'car_driver_id' => $previous->id,
            'vehicle_id' => $this->vehicle->id,
            'organization_id' => $this->organization->id,
            'vehicle_position' => 'lead',
            'approval_status' => 'pending',
        ]);

        Mail::fake();

        $log->update(['car_driver_id' => $next->id]);

        Mail::assertSent(UserNotification::class, fn (UserNotification $mail) => $mail->hasTo('previous@example.test')
            && str_contains($mail->subject, 'Removed from Job JOB-459'));
        Mail::assertSent(UserNotification::class, fn (UserNotification $mail) => $mail->hasTo('next@example.test')
            && str_contains($mail->subject, 'Job Assigned'));
    }

    public function test_editing_anything_else_on_the_log_tells_nobody(): void
    {
        $driver = $this->smsDriver(self::GATEWAY_A, 'a@example.test');

        $log = UserLog::create([
            'job_id' => $this->job->id,
            'car_driver_id' => $driver->id,
            'vehicle_id' => $this->vehicle->id,
            'organization_id' => $this->organization->id,
            'vehicle_position' => 'lead',
            'approval_status' => 'pending',
        ]);

        Mail::fake();

        $log->update(['memo' => 'Bring the wide-load signs', 'vehicle_position' => 'chase']);

        Mail::assertNothingSent();
    }

    public function test_correcting_who_drove_a_past_job_texts_nobody(): void
    {
        $previous = $this->smsDriver(self::GATEWAY_A, 'a@example.test');
        $next = $this->smsDriver(self::GATEWAY_B, 'b@example.test');
        $this->job->update(['scheduled_pickup_at' => now()->subDays(10)]);

        $log = UserLog::create([
            'job_id' => $this->job->id,
            'car_driver_id' => $previous->id,
            'vehicle_id' => $this->vehicle->id,
            'organization_id' => $this->organization->id,
            'vehicle_position' => 'lead',
            'approval_status' => 'confirmed',
        ]);

        Mail::fake();

        $log->update(['car_driver_id' => $next->id]);

        Mail::assertNothingSent();
    }
}
