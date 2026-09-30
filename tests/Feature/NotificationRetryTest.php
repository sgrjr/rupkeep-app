<?php

namespace Tests\Feature;

use App\Actions\SendUserNotification;
use App\Events\JobAssigned;
use App\Events\JobWasCanceled;
use App\Jobs\SendUserMessage;
use App\Listeners\NotifyAssignedDriversOfJobCancellation;
use App\Listeners\NotifyAssignedDriversOfJobUncancellation;
use App\Listeners\NotifyDriversOfJobStatusChange;
use App\Listeners\SendInvoiceFlaggedNotification;
use App\Listeners\SendInvoiceReadyNotification;
use App\Listeners\SendJobAssignedNotification;
use App\Listeners\SendLogCompletedNotification;
use App\Mail\UserNotification;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * TASK-465. A failed send was caught, written to the log mailer and reported
 * as success, so the queue never retried: a Brevo blip lost the driver's
 * assignment text for good. Now a queued listener throws while it has
 * attempts left and falls back to the log mailer only on the last one; the
 * listeners carry tries/backoff/afterCommit; and the listeners that loop
 * over drivers hand each driver their own queued job.
 */
class NotificationRetryTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
    }

    private function breakTheMailer(): void
    {
        // An explicitly unsupported transport throws at resolution time,
        // offline, without touching the real mailers' credentials.
        config([
            'mail.default' => 'broken',
            'mail.mailers.broken' => ['transport' => 'unsupported-transport'],
        ]);
        Mail::forgetMailers();
    }

    private function jobWithDriver(?string $notificationAddress = null): array
    {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $driver = User::factory()->standard()->create([
            'organization_id' => $this->organization->id,
            'notification_address' => $notificationAddress,
        ]);
        $job = PilotCarJob::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'scheduled_pickup_at' => Carbon::now()->addDay(),
        ]);

        return [$job, $driver];
    }

    /** A queue job handle as the worker would attach it. */
    private function queueJob(int $attempt, int $maxTries = 3): QueueJob
    {
        $job = Mockery::mock(QueueJob::class);
        $job->shouldReceive('attempts')->andReturn($attempt);
        $job->shouldReceive('maxTries')->andReturn($maxTries);

        return $job;
    }

    // -----------------------------------------------------------------
    // The trait: throw while the queue can retry, fall back on the last go
    // -----------------------------------------------------------------

    public function test_a_failed_send_on_an_early_attempt_is_thrown_back_to_the_queue(): void
    {
        Notification::fake();
        $this->breakTheMailer();
        Log::spy();
        [$job, $driver] = $this->jobWithDriver();

        $listener = new SendJobAssignedNotification();
        $listener->job = $this->queueJob(attempt: 1);

        try {
            $listener->handle(new JobAssigned($job, $driver));
            $this->fail('the first attempt must throw so the worker releases the job');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('unsupported', strtolower($e->getMessage()));
        }

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message) => str_contains($message, 'the queue will retry'))
            ->once();
        Log::shouldNotHaveReceived('warning', [Mockery::pattern('/log-mailer fallback/'), Mockery::any()]);
    }

    public function test_the_final_attempt_falls_back_to_the_log_mailer_and_does_not_throw(): void
    {
        Notification::fake();
        $this->breakTheMailer();
        Log::spy();
        [$job, $driver] = $this->jobWithDriver();

        $listener = new SendJobAssignedNotification();
        $listener->job = $this->queueJob(attempt: 3);

        $listener->handle(new JobAssigned($job, $driver));

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message) => str_contains($message, 'log-mailer fallback'))
            ->once();
    }

    public function test_the_sync_driver_never_rethrows(): void
    {
        // On the sync queue the throw would land on whoever fired the event,
        // in the middle of their request.
        Notification::fake();
        $this->breakTheMailer();
        Log::spy();
        [$job, $driver] = $this->jobWithDriver();

        $listener = new SendJobAssignedNotification();
        $listener->job = Mockery::mock(SyncJob::class)->makePartial();

        $listener->handle(new JobAssigned($job, $driver));

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message) => str_contains($message, 'log-mailer fallback'))
            ->once();
    }

    public function test_every_notification_listener_retries_with_backoff_after_commit(): void
    {
        foreach ([
            SendJobAssignedNotification::class,
            SendInvoiceReadyNotification::class,
            SendInvoiceFlaggedNotification::class,
            SendLogCompletedNotification::class,
            NotifyAssignedDriversOfJobCancellation::class,
            NotifyAssignedDriversOfJobUncancellation::class,
            NotifyDriversOfJobStatusChange::class,
        ] as $class) {
            $listener = new $class();

            $this->assertSame(3, $listener->tries, "$class tries");
            $this->assertSame([30, 120], $listener->backoff, "$class backoff");
            $this->assertTrue($listener->afterCommit, "$class afterCommit");
            $this->assertTrue($listener->deleteWhenMissingModels, "$class deleteWhenMissingModels");
            $this->assertTrue(method_exists($listener, 'failed'), "$class failed()");
        }
    }

    // -----------------------------------------------------------------
    // One queued job per recipient
    // -----------------------------------------------------------------

    public function test_cancellation_queues_one_message_per_driver(): void
    {
        Queue::fake();
        [$job, $first] = $this->jobWithDriver();
        $second = User::factory()->standard()->create(['organization_id' => $this->organization->id]);

        foreach ([$first, $second] as $driver) {
            UserLog::create([
                'job_id' => $job->id,
                'organization_id' => $this->organization->id,
                'car_driver_id' => $driver->id,
                'approval_status' => 'confirmed',
            ]);
        }
        $job->update(['canceled_at' => now(), 'canceled_reason' => 'Weather']);

        (new NotifyAssignedDriversOfJobCancellation())->handle(new JobWasCanceled($job->fresh(), 'Weather', 'weather'));

        Queue::assertPushed(SendUserMessage::class, 2);
        Queue::assertPushed(SendUserMessage::class, fn (SendUserMessage $m) => $m->user->is($first) && str_contains($m->message, 'canceled'));
        Queue::assertPushed(SendUserMessage::class, fn (SendUserMessage $m) => $m->user->is($second));
    }

    public function test_the_per_recipient_job_retries_then_falls_back(): void
    {
        $this->breakTheMailer();
        Log::spy();
        [, $driver] = $this->jobWithDriver();

        $message = new SendUserMessage($driver, 'Job canceled.', 'Job Canceled');
        $this->assertSame(3, $message->tries);
        $this->assertSame([30, 120], $message->backoff);
        $this->assertTrue($message->afterCommit);

        // Attempt 1 of 3: hand it back to the worker.
        $message->setJob($this->queueJob(attempt: 1));
        try {
            $message->handle();
            $this->fail('an early attempt must throw');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('unsupported', strtolower($e->getMessage()));
        }

        // Attempt 3 of 3: log it and stop.
        $message->setJob($this->queueJob(attempt: 3));
        $message->handle();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($m) => str_contains($m, 'Attempting final fallback to log mailer'))
            ->once();
    }

    public function test_the_per_recipient_job_sends_on_the_happy_path(): void
    {
        Mail::fake();
        [, $driver] = $this->jobWithDriver();

        (new SendUserMessage($driver, 'Job canceled.', 'Job Canceled'))->handle();

        Mail::assertSent(UserNotification::class, fn ($mail) => $mail->hasTo($driver->email));
    }

    public function test_the_synchronous_entry_point_still_never_throws(): void
    {
        // Controllers and Livewire actions call to(); they have nobody to
        // retry for them and must not 500.
        $this->breakTheMailer();
        Log::spy();
        [, $driver] = $this->jobWithDriver();

        SendUserNotification::to($driver, 'Welcome.', 'Welcome');

        Log::shouldHaveReceived('error')
            ->withArgs(fn ($m) => str_contains($m, 'Failed to send via Laravel Mail'))
            ->once();
    }
}
