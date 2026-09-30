<?php

namespace Tests\Feature;

use App\Console\Commands\QueueHealth;
use App\Mail\UserNotification;
use App\Models\User;
use App\Services\QueueHealthCheck;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * TASK-469. queue:health reported HEALTHY with no worker running and a
 * ten-minute-old job waiting; nobody was told about failed jobs; and /up
 * proved only that the framework booted.
 */
class QueueHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The suite runs the sync driver; the checks are about the database queue.
        config(['queue.default' => 'database']);
        QueueHealthCheck::$workerProbe = fn () => 1;
    }

    protected function tearDown(): void
    {
        QueueHealthCheck::$workerProbe = null;
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pendingJob(int $minutesOld): void
    {
        $at = now()->subMinutes($minutesOld)->getTimestamp();

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'available_at' => $at,
            'created_at' => $at,
        ]);
    }

    private function failedJob(string $exception = "RuntimeException: SMTP refused\n#0 trace"): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => $exception,
            'failed_at' => now(),
        ]);
    }

    private function superUser(string $email = 'stephen@example.test'): User
    {
        return User::factory()->superUser()->create(['email' => $email]);
    }

    // -----------------------------------------------------------------
    // The judgement
    // -----------------------------------------------------------------

    public function test_an_idle_queue_with_a_worker_is_healthy(): void
    {
        $report = app(QueueHealthCheck::class)->run();

        $this->assertTrue($report['healthy']);
        $this->assertSame([], $report['problems']);
        $this->assertSame(1, $report['facts']['workers']);
    }

    public function test_no_worker_process_is_unhealthy(): void
    {
        QueueHealthCheck::$workerProbe = fn () => 0;

        $report = app(QueueHealthCheck::class)->run();

        $this->assertFalse($report['healthy']);
        $this->assertStringContainsString('No queue worker process is running', $report['problems'][0]);

        // A host that cannot tell (no `ps`) is not a problem.
        QueueHealthCheck::$workerProbe = fn () => null;
        $this->assertTrue(app(QueueHealthCheck::class)->run()['healthy']);
    }

    public function test_a_pending_job_older_than_five_minutes_is_unhealthy(): void
    {
        $this->pendingJob(minutesOld: 2);
        $this->assertTrue(app(QueueHealthCheck::class)->run()['healthy'], 'a fresh job is normal');

        $this->pendingJob(minutesOld: 12);
        $report = app(QueueHealthCheck::class)->run();

        $this->assertFalse($report['healthy']);
        $this->assertSame(12, $report['facts']['oldest_pending_minutes']);
        $this->assertStringContainsString('waited 12 minutes', $report['problems'][0]);
    }

    public function test_failed_jobs_are_unhealthy_and_summarised(): void
    {
        $this->failedJob();

        $report = app(QueueHealthCheck::class)->run();

        $this->assertFalse($report['healthy']);
        $this->assertSame(1, $report['facts']['failed']);
        $this->assertSame('RuntimeException: SMTP refused', $report['facts']['recent_failed'][0]->summary);
    }

    public function test_the_command_exits_non_zero_when_unhealthy(): void
    {
        $this->artisan('queue:health')->assertSuccessful();

        QueueHealthCheck::$workerProbe = fn () => 0;
        $this->artisan('queue:health')->assertFailed();
    }

    // -----------------------------------------------------------------
    // Telling someone
    // -----------------------------------------------------------------

    public function test_notify_mails_the_super_users_once_and_again_when_it_recovers(): void
    {
        Mail::fake();
        $this->superUser();
        User::factory()->admin()->create(['email' => 'mary@example.test']); // not super: not alerted
        QueueHealthCheck::$workerProbe = fn () => 0;

        $this->artisan('queue:health --notify')->assertFailed();
        Mail::assertSent(UserNotification::class, fn ($mail) => $mail->hasTo('stephen@example.test') && str_contains($mail->subject, 'needs attention'));
        Mail::assertNotSent(UserNotification::class, fn ($mail) => $mail->hasTo('mary@example.test'));

        // Same problem five minutes later: no second email.
        $this->artisan('queue:health --notify')->assertFailed();
        Mail::assertSent(UserNotification::class, 1);

        // Six hours on, still broken: remind.
        Carbon::setTestNow(now()->addHours(7));
        $this->artisan('queue:health --notify')->assertFailed();
        Mail::assertSent(UserNotification::class, 2);

        // Fixed: one "recovered" mail, then silence.
        QueueHealthCheck::$workerProbe = fn () => 1;
        $this->artisan('queue:health --notify')->assertSuccessful();
        Mail::assertSent(UserNotification::class, fn ($mail) => str_contains($mail->subject, 'recovered'));
        Mail::assertSent(UserNotification::class, 3);

        $this->artisan('queue:health --notify')->assertSuccessful();
        Mail::assertSent(UserNotification::class, 3);
        $this->assertNull(Cache::get(QueueHealth::ALERT_CACHE_KEY));
    }

    public function test_a_healthy_queue_sends_nothing(): void
    {
        Mail::fake();
        $this->superUser();

        $this->artisan('queue:health --notify')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_the_alert_is_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($event) => $event->command)->implode(' ');

        $this->assertStringContainsString('queue:health', $commands);
        $this->assertStringContainsString('--notify', $commands);
    }

    // -----------------------------------------------------------------
    // /up
    // -----------------------------------------------------------------

    public function test_up_answers_200_when_the_database_and_queue_are_fine(): void
    {
        $this->pendingJob(minutesOld: 1);

        $this->get('/up')->assertOk();
    }

    public function test_up_answers_503_when_the_queue_is_not_being_drained(): void
    {
        $this->pendingJob(minutesOld: 30);

        $this->get('/up')->assertStatus(503);
    }

    public function test_up_does_not_fail_on_failed_jobs_alone(): void
    {
        // A failed job is a problem for the alert, not an outage for the monitor.
        $this->failedJob();

        $this->get('/up')->assertOk();
    }
}
