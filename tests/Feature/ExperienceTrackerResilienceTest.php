<?php

namespace Tests\Feature;

use App\Models\UserEvent;
use App\Services\ExperienceTrackerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The 2026-09-30 production outage: the log file was unwritable, so
 * reporting an exception -> tracker insert failed (context too long) ->
 * tracker logged that -> log threw -> every request was a 500. The tracker
 * runs inside the exception reporter and must never throw, whatever the
 * state of the log or the database.
 */
class ExperienceTrackerResilienceTest extends TestCase
{
    use RefreshDatabase;

    private function breakTheLog(): void
    {
        config()->set('logging.default', 'single');
        config()->set('logging.channels.single.path', '/nonexistent-dir-'.uniqid().'/laravel.log');
    }

    public function test_tracking_survives_an_unwritable_log_and_a_failing_insert(): void
    {
        $this->breakTheLog();
        Schema::drop('user_events');

        $event = ExperienceTrackerService::trackError(new \RuntimeException('boom'));

        $this->assertInstanceOf(UserEvent::class, $event);
        $this->assertFalse($event->exists);
    }

    public function test_oversized_context_is_capped_to_fit_the_column(): void
    {
        $huge = str_repeat('x', 200000);

        $event = ExperienceTrackerService::trackError(new \RuntimeException($huge));

        $this->assertTrue($event->exists);
        $this->assertLessThanOrEqual(60000, strlen(json_encode($event->fresh()->context)));

        $capped = ExperienceTrackerService::capContext(['message' => $huge, 'trace' => $huge, 'extra' => $huge]);
        $this->assertLessThanOrEqual(60000, strlen(json_encode($capped)));
        $this->assertLessThanOrEqual(4000, mb_strlen($capped['message']));
    }
}
