<?php

namespace Tests\Unit\Support;

use App\Support\SmsMessage;
use PHPUnit\Framework\TestCase;

class SmsMessageTest extends TestCase
{
    public function test_short_message_is_returned_intact(): void
    {
        $message = SmsMessage::make()
            ->fixed('Job ')
            ->flexible('A-1')
            ->fixed(' assigned. ')
            ->url('https://example.com/x')
            ->build();

        $this->assertSame('Job A-1 assigned. https://example.com/x', $message);
    }

    public function test_flexible_content_is_truncated_with_an_ascii_ellipsis(): void
    {
        $message = SmsMessage::make(40)
            ->fixed('Pickup: ')
            ->flexible(str_repeat('A', 200))
            ->build();

        $this->assertLessThanOrEqual(40, mb_strlen($message));
        $this->assertStringStartsWith('Pickup: AAA', $message);
        $this->assertStringEndsWith('...', $message);
        // ASCII ellipsis keeps the payload GSM-7, never the "…" code point.
        $this->assertStringNotContainsString('…', $message);
    }

    public function test_url_is_never_truncated_even_when_budget_is_tight(): void
    {
        $url = 'https://www.pilotcar.io/my/jobs/999999';

        $message = SmsMessage::make()
            ->fixed('Job ')
            ->flexible(str_repeat('LONGCUSTOMER ', 40))
            ->fixed(' pickup ')
            ->flexible(str_repeat('123 Very Long Street Name ', 40))
            ->fixed('. For updates: ')
            ->url($url)
            ->build();

        $this->assertLessThanOrEqual(SmsMessage::LIMIT, mb_strlen($message));
        $this->assertStringContainsString($url, $message);
        $this->assertStringEndsWith($url, $message);
    }

    public function test_total_never_exceeds_limit_with_pathological_input(): void
    {
        $message = SmsMessage::make()
            ->fixed('Job ')
            ->flexible(str_repeat('X', 5000))
            ->fixed(' — ')
            ->flexible(str_repeat('Y', 5000))
            ->fixed('. Details: ')
            ->url('https://www.pilotcar.io/my/jobs/123456')
            ->build();

        $this->assertLessThanOrEqual(SmsMessage::LIMIT, mb_strlen($message));
    }

    public function test_remaining_budget_flows_from_short_to_later_flexible_segments(): void
    {
        // The first flexible field is short, so the second one should receive the
        // budget it does not use rather than being starved to an equal split.
        $message = SmsMessage::make(40)
            ->flexible('AB')
            ->fixed('|')
            ->flexible(str_repeat('Z', 200))
            ->build();

        $this->assertLessThanOrEqual(40, mb_strlen($message));
        $this->assertStringStartsWith('AB|ZZZ', $message);
        // The long field got far more than a naive half-of-40 split.
        $zCount = substr_count($message, 'Z');
        $this->assertGreaterThan(30, $zCount);
    }

    public function test_null_url_and_empty_flexible_are_handled(): void
    {
        $message = SmsMessage::make()
            ->fixed('Status: ')
            ->flexible(null)
            ->fixed('done')
            ->url(null)
            ->build();

        $this->assertSame('Status: done', $message);
    }

    public function test_flexible_falls_back_when_value_is_blank(): void
    {
        $message = SmsMessage::make()
            ->fixed('Pickup: ')
            ->flexible('   ', 'TBD')
            ->build();

        $this->assertSame('Pickup: TBD', $message);
    }
}
