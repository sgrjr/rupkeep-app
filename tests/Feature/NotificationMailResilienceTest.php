<?php

namespace Tests\Feature;

use App\Events\JobAssigned;
use App\Listeners\SendJobAssignedNotification;
use App\Mail\UserNotification;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationMailResilienceTest extends TestCase
{
    use RefreshDatabase;

    private function futureJobWithDriver(): array
    {
        $org = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $org->id]);
        $driver = User::factory()->standard()->create([
            'organization_id' => $org->id,
            'email' => 'driver@example.com',
        ]);
        $job = PilotCarJob::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'scheduled_pickup_at' => Carbon::now()->addDay(),
        ]);

        return [$job, $driver];
    }

    public function test_job_assigned_email_is_sent_on_the_happy_path(): void
    {
        Notification::fake(); // isolate the push channel
        Mail::fake();

        [$job, $driver] = $this->futureJobWithDriver();

        (new SendJobAssignedNotification())->handle(new JobAssigned($job, $driver));

        Mail::assertSent(UserNotification::class, fn ($mail) => $mail->hasTo('driver@example.com'));
    }

    public function test_brevo_mailer_resolves_to_a_valid_smtp_transport(): void
    {
        // Regression for the "Unsupported mail transport []" failure: the brevo
        // mailer now has a real (smtp) transport, so MAIL_MAILER=brevo is valid.
        config([
            'mail.default' => 'brevo',
            'mail.mailers.brevo.host' => 'smtp-relay.brevo.com',
            'mail.mailers.brevo.username' => 'user',
            'mail.mailers.brevo.password' => 'pass',
        ]);
        Mail::forgetMailers();

        $transport = Mail::mailer('brevo')->getSymfonyTransport();

        $this->assertStringContainsString('smtp', (string) $transport);
        // The Brevo SDK path (SendUserNotification) still finds its API sub-keys.
        $this->assertArrayHasKey('key', config('mail.mailers.brevo'));
        $this->assertArrayHasKey('sender', config('mail.mailers.brevo'));
    }

    public function test_mail_credentials_are_neutralized_in_the_test_environment(): void
    {
        // Guards the phpunit.xml lockdown: the suite must never inherit real
        // MAIL_* / BREVO_API_KEY values from .env, or a test that switches
        // mailer could send live email (see TASK-343). If someone removes the
        // neutralizing <env> entries from phpunit.xml, this fails.
        $this->assertNull(config('mail.mailers.brevo.username'));
        $this->assertNull(config('mail.mailers.brevo.password'));
        $this->assertNull(config('mail.mailers.brevo.key'));
        $this->assertNull(config('mail.mailers.smtp.username'));
        $this->assertSame('127.0.0.1', config('mail.mailers.brevo.host'));
        $this->assertSame('array', config('mail.default'));
    }

    public function test_a_broken_mail_transport_does_not_dead_letter_the_job(): void
    {
        Notification::fake();

        // Reproduce the production misconfig (a mailer with no valid transport →
        // "Unsupported mail transport", TASK-338) DETERMINISTICALLY. We must not
        // point at the real `brevo` mailer: it inherits live MAIL_* credentials
        // from .env, so once those are valid the send actually connects to
        // smtp-relay.brevo.com and succeeds — the transport is no longer "broken",
        // the warning never fires, and the suite fires a real email over the wire.
        // An explicitly unsupported transport throws at resolution time, offline.
        config([
            'mail.default' => 'broken',
            'mail.mailers.broken' => ['transport' => 'unsupported-transport'],
        ]);
        Mail::forgetMailers();
        Log::spy();

        [$job, $driver] = $this->futureJobWithDriver();

        // Must not throw — otherwise the queued job dead-letters into failed_jobs.
        (new SendJobAssignedNotification())->handle(new JobAssigned($job, $driver));

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message) => str_contains($message, 'Notification email failed'))
            ->atLeast()->once();
    }
}
