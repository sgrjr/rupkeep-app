<?php

namespace Tests\Feature;

use App\Events\JobAssigned;
use App\Events\JobStatusChanged;
use App\Events\JobWasCanceled;
use App\Listeners\NotifyAssignedDriversOfJobCancellation;
use App\Listeners\NotifyDriversOfJobStatusChange;
use App\Listeners\SendJobAssignedNotification;
use App\Mail\UserNotification;
use App\Mail\UserNotificationSms;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use App\Support\JobSms;
use App\Support\LocalTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * TASK-464. Every driver text and email formatted the raw UTC timestamp, so
 * a 7:00 AM Eastern job texted as "11:00 AM". The screens already used
 * LocalTime; the notifications did not. The "skip past jobs" guard also
 * compared against the server's UTC day.
 */
class NotificationTimezoneTest extends TestCase
{
    use RefreshDatabase;

    /** 7:00 AM Eastern Daylight Time on 30 September 2026, as stored. */
    private const PICKUP_UTC = '2026-09-30 11:00:00';

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.display_timezone' => 'America/New_York']);
        $this->organization = Organization::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function job(string $pickupUtc = self::PICKUP_UTC): PilotCarJob
    {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        return PilotCarJob::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'job_no' => 'JOB-464',
            'scheduled_pickup_at' => $pickupUtc,
        ]);
    }

    private function smsDriver(): User
    {
        return User::factory()->standard()->create([
            'organization_id' => $this->organization->id,
            'notification_address' => '2075550100@vtext.com',
        ]);
    }

    private function emailDriver(): User
    {
        return User::factory()->standard()->create([
            'organization_id' => $this->organization->id,
            'email' => 'driver@example.com',
            'notification_address' => null,
        ]);
    }

    public function test_the_helper_renders_the_stored_utc_value_in_eastern_time(): void
    {
        $this->assertSame('Wed, Sep 30, 2026 7:00 AM EDT', LocalTime::dayDateTime(self::PICKUP_UTC));
        $this->assertSame('Not scheduled', LocalTime::dayDateTime(null, 'Not scheduled'));
        $this->assertSame('2026-09-30 07:00', LocalTime::parse(self::PICKUP_UTC)->format('Y-m-d H:i'));
        $this->assertNull(LocalTime::parse('not a date'));
    }

    public function test_the_assignment_text_carries_the_eastern_time(): void
    {
        $sms = JobSms::assigned($this->job(), 'https://pilotcar.io/logs/1', true);

        $this->assertStringContainsString('@9/30 7:00 AM', $sms);
        $this->assertStringNotContainsString('11:00', $sms);
    }

    public function test_the_assignment_notification_texts_and_emails_the_eastern_time(): void
    {
        Carbon::setTestNow('2026-09-29 12:00:00');
        Notification::fake();
        Mail::fake();

        $job = $this->job();

        (new SendJobAssignedNotification())->handle(new JobAssigned($job, $this->smsDriver()));
        Mail::assertSent(UserNotificationSms::class, function (UserNotificationSms $mail) {
            $body = $mail->body();

            return str_contains($body, '9/30 7:00 AM') && ! str_contains($body, '11:00');
        });

        (new SendJobAssignedNotification())->handle(new JobAssigned($job, $this->emailDriver()));
        Mail::assertSent(UserNotification::class, function (UserNotification $mail) {
            $html = $mail->render();

            return str_contains($html, 'Wed, Sep 30, 2026 7:00 AM EDT') && ! str_contains($html, '11:00 AM');
        });
    }

    public function test_cancellation_and_status_emails_carry_the_eastern_time(): void
    {
        Notification::fake();
        Mail::fake();

        $job = $this->job();
        $driver = $this->emailDriver();
        UserLog::create([
            'job_id' => $job->id,
            'organization_id' => $this->organization->id,
            'car_driver_id' => $driver->id,
            'approval_status' => 'confirmed',
        ]);
        $job->update(['canceled_at' => now(), 'canceled_reason' => 'Customer rescheduled']);

        (new NotifyAssignedDriversOfJobCancellation())->handle(new JobWasCanceled($job->fresh(), 'Customer rescheduled', 'customer'));
        Mail::assertSent(UserNotification::class, fn (UserNotification $mail) => str_contains($mail->render(), 'Wed, Sep 30, 2026 7:00 AM EDT'));

        Mail::fake();
        $job->update(['canceled_at' => null, 'canceled_reason' => null]);
        (new NotifyDriversOfJobStatusChange())->handle(new JobStatusChanged($job->fresh(), 'scheduled', 'in_progress'));
        Mail::assertSent(UserNotification::class, fn (UserNotification $mail) => str_contains($mail->render(), 'Wed, Sep 30, 2026 7:00 AM EDT'));
    }

    /**
     * At 10 PM Eastern on 30 September it is already 1 October in UTC. A job
     * at 7 PM Eastern the same evening is today's job, and the driver being
     * assigned to it now must hear about it; the UTC comparison filed it under
     * yesterday and said nothing.
     */
    public function test_today_is_the_eastern_day_when_deciding_whether_a_job_is_past(): void
    {
        Carbon::setTestNow('2026-10-01 02:00:00'); // 10:00 PM EDT, 30 September
        Notification::fake();
        Mail::fake();

        $driver = $this->emailDriver();
        $job = $this->job('2026-09-30 23:00:00'); // 7:00 PM EDT, 30 September

        (new SendJobAssignedNotification())->handle(new JobAssigned($job, $driver));

        Mail::assertSent(UserNotification::class);

        // And a job that really was yesterday, Eastern, is still skipped.
        Mail::fake();
        $yesterday = $this->job('2026-09-30 02:00:00'); // 10:00 PM EDT, 29 September
        (new SendJobAssignedNotification())->handle(new JobAssigned($yesterday, $driver));

        Mail::assertNothingSent();
    }
}
