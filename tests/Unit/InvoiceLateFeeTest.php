<?php

namespace Tests\Unit;

use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * TASK-410. Carbon 3 returns a float from diffInDays() where Carbon 2 returned
 * an int, so the fractional time of day leaked through calculateLateFees() and
 * reached the screen as "60.000032710208 days overdue".
 *
 * The method's docblock always promised an int; these pin that promise so a
 * future Carbon upgrade cannot quietly break it again.
 */
class InvoiceLateFeeTest extends TestCase
{
    private function invoiceDated(Carbon $created, float $total = 1000.0): Invoice
    {
        $invoice = new Invoice();
        $invoice->created_at = $created;
        $invoice->values = ['total' => $total];
        $invoice->paid_in_full = false;

        return $invoice;
    }

    public function test_days_overdue_is_a_whole_number(): void
    {
        // A time of day is what produced the fraction, so make sure there is one.
        Carbon::setTestNow(Carbon::parse('2026-08-24 13:27:31'));

        $result = $this->invoiceDated(Carbon::parse('2026-05-25 09:14:02'))->calculateLateFees();

        $this->assertIsInt($result['days_overdue']);
        $this->assertSame($result['days_overdue'], (int) $result['days_overdue']);

        Carbon::setTestNow();
    }

    public function test_an_invoice_inside_the_grace_period_is_not_overdue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 13:27:31'));

        $result = $this->invoiceDated(Carbon::parse('2026-08-10 09:14:02'))->calculateLateFees();

        $this->assertFalse($result['is_past_due']);
        $this->assertSame(0, $result['days_overdue']);

        Carbon::setTestNow();
    }

    /**
     * Flooring must never overstate how late a customer is: 59 days and 23
     * hours past the grace period is 59 days overdue, not 60.
     */
    public function test_a_partial_day_does_not_round_up(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 08:00:00'));

        // 89 days and 23 hours before now, less a 30 day grace period.
        $result = $this->invoiceDated(Carbon::parse('2026-05-26 09:00:00'))->calculateLateFees();

        $this->assertSame(59, $result['days_overdue']);

        Carbon::setTestNow();
    }

    /**
     * Late fee periods already floored, so the money was never wrong -- this
     * guards that the display fix did not disturb it.
     */
    public function test_late_fee_periods_are_unaffected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 13:27:31'));

        $result = $this->invoiceDated(Carbon::parse('2026-05-25 09:14:02'))->calculateLateFees();

        $this->assertTrue($result['is_past_due']);
        $this->assertIsInt($result['late_fee_periods']);
        $this->assertSame(2, $result['late_fee_periods']); // 61 days overdue / 30

        Carbon::setTestNow();
    }
}
