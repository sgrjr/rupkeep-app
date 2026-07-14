<?php

namespace Tests\Feature;

use App\Livewire\UserProfile;
use App\Mail\UserNotification;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use ReflectionProperty;
use Tests\TestCase;

class TestNotificationTypeTest extends TestCase
{
    use RefreshDatabase;

    /** UserNotification::$message is private; read it for assertions. */
    private function messageOf(UserNotification $mail): string
    {
        $ref = new ReflectionProperty(UserNotification::class, 'message');
        $ref->setAccessible(true);

        return (string) $ref->getValue($mail);
    }

    /** A profile with an email recipient (no SMS gateway). Acts as itself
     *  (the update policy permits self-update). */
    private function makeProfile(): User
    {
        $org = Organization::factory()->create(['name' => 'Casco Bay Pilot Car']);

        $profile = User::factory()->forOrganization($org)->create([
            'notification_address' => null,
        ]);

        $this->actingAs($profile);

        return $profile;
    }

    public function test_default_sends_standard_test_notification(): void
    {
        Mail::fake();
        $profile = $this->makeProfile();

        Livewire::test(UserProfile::class, ['user' => $profile->id])
            ->call('testNotification')
            ->assertSet('notificationTestStatus', 'success');

        Mail::assertSent(UserNotification::class, function (UserNotification $mail) {
            return $mail->subject === 'test'
                && str_contains($this->messageOf($mail), 'This is a test notification from Casco Bay Pilot Car');
        });
    }

    public function test_job_assigned_type_sends_fake_job_message(): void
    {
        Mail::fake();
        $profile = $this->makeProfile();

        Livewire::test(UserProfile::class, ['user' => $profile->id])
            ->set('notificationTestType', 'job_assigned')
            ->call('testNotification')
            ->assertSet('notificationTestStatus', 'success');

        Mail::assertSent(UserNotification::class, function (UserNotification $mail) {
            $body = $this->messageOf($mail);

            return $mail->subject === 'New Job 7 (TEST)'
                && str_contains($body, 'New job assignment [Lead car]')
                && str_contains($body, 'Job NO. TEST-1001')
                && str_contains($body, '/my/jobs/999999')
                && str_contains($body, 'FAKE');
        });
    }

    public function test_unknown_type_falls_back_to_standard(): void
    {
        Mail::fake();
        $profile = $this->makeProfile();

        Livewire::test(UserProfile::class, ['user' => $profile->id])
            ->set('notificationTestType', 'something_bogus')
            ->call('testNotification')
            ->assertSet('notificationTestStatus', 'success');

        Mail::assertSent(UserNotification::class, function (UserNotification $mail) {
            return $mail->subject === 'test'
                && str_contains($this->messageOf($mail), 'This is a test notification from');
        });
    }
}
