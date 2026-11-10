<?php

namespace Tests\Unit;

use App\Support\LocalTime;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * TASK-503 (drive 003). A payment recorded on 2026-11-10 showed as Nov 9:
 * the date-only value was read as midnight UTC and moved to Eastern time.
 */
class CalendarDateTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_bare_date_is_not_shifted_into_the_previous_day(): void
    {
        config(['app.display_timezone' => 'America/New_York']);

        $this->assertSame('Nov 10, 2026', LocalTime::calendarDate('2026-11-10'));
        // The old path is what moved it.
        $this->assertSame('Nov 9, 2026', LocalTime::format('2026-11-10', 'M j, Y'));
    }

    public function test_a_timestamp_still_converts_to_the_display_timezone(): void
    {
        config(['app.display_timezone' => 'America/New_York']);

        $this->assertSame('Nov 9, 2026', LocalTime::calendarDate('2026-11-10 02:00:00'));
        $this->assertSame('—', LocalTime::calendarDate(null, 'M j, Y', '—'));
    }

    public function test_today_is_the_eastern_day_late_in_the_evening(): void
    {
        config(['app.display_timezone' => 'America/New_York']);

        // 11:30 PM UTC on Nov 10 is 6:30 PM Eastern; 2:00 AM UTC on Nov 11 is still Nov 10 Eastern.
        Carbon::setTestNow(Carbon::parse('2026-11-11 02:00:00', 'UTC'));

        $this->assertSame('2026-11-10', LocalTime::todayDateString());
    }
}
