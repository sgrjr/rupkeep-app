<?php

namespace Tests\Feature;

use App\Support\LocalTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class LocalTimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.display_timezone', 'America/New_York');
    }

    public function test_converts_utc_string_to_eastern(): void
    {
        // 2026-07-13 16:00 UTC = 12:00 PM EDT
        $this->assertSame('12:00 PM EDT 7/13/2026', LocalTime::format('2026-07-13 16:00:00'));
    }

    public function test_converts_utc_carbon_to_eastern(): void
    {
        $utc = Carbon::parse('2026-01-13 17:30:00', 'UTC'); // winter => EST
        $this->assertSame('12:30 PM EST 1/13/2026', LocalTime::format($utc));
    }

    public function test_empty_and_null_return_default(): void
    {
        $this->assertSame('', LocalTime::format(null));
        $this->assertSame('', LocalTime::format(''));
        $this->assertSame('—', LocalTime::format(null, LocalTime::DEFAULT_FORMAT, '—'));
    }

    public function test_date_helpers(): void
    {
        $this->assertSame('7/13/2026', LocalTime::date('2026-07-13 16:00:00'));
        $this->assertSame('Jul 13, 2026', LocalTime::mediumDate('2026-07-13 16:00:00'));
    }

    public function test_unparseable_value_is_returned_as_is(): void
    {
        $this->assertSame('not a date', LocalTime::format('not a date'));
    }
}
