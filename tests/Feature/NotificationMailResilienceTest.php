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

    public function test_a_broken_mail_transport_does_not_dead_letter_the_job(): void
    {
        Notification::fake();

        // Reproduce the production misconfig: MAIL_MAILER=brevo, which has no
        // valid transport → "Unsupported mail transport []" (TASK-338).
        config(['mail.default' => 'brevo']);
        Log::spy();

        [$job, $driver] = $this->futureJobWithDriver();

        // Must not throw — otherwise the queued job dead-letters into failed_jobs.
        (new SendJobAssignedNotification())->handle(new JobAssigned($job, $driver));

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message) => str_contains($message, 'Notification email failed'))
            ->atLeast()->once();
    }
}
