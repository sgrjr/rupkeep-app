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
 *
 * TASK-443. The applied fee used to be written into values.total AND added on
 * top again at read time. The second half of this file pins the arithmetic:
 * values.total is the pre-fee amount, the applied fee is locked, and only
 * periods it does not cover accrue anything further.
 */
class InvoiceLateFeeTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function invoiceDated(Carbon $created, float $total = 1000.0, array $values = []): Invoice
    {
        $invoice = new Invoice();
        $invoice->created_at = $created;
        $invoice->values = ['total' => $total] + $values;
        $invoice->paid_in_full = false;

        return $invoice;
    }

    /** An invoice with a fee already applied for $periods periods at 10%. */
    private function appliedFee(int $periods, float $total = 1000.0): array
    {
        return [
            'applied_at' => '2026-07-26 09:00:00',
            'applied_by' => 1,
            'late_fee_periods' => $periods,
            'late_fee_amount' => round($total * 0.10 * $periods, 2),
            'original_total' => $total,
        ];
    }

    public function test_days_overdue_is_a_whole_number(): void
    {
        // A time of day is what produced the fraction, so make sure there is one.
        Carbon::setTestNow(Carbon::parse('2026-08-24 13:27:31'));

        $result = $this->invoiceDated(Carbon::parse('2026-05-25 09:14:02'))->calculateLateFees();

        $this->assertIsInt($result['days_overdue']);
        $this->assertSame($result['days_overdue'], (int) $result['days_overdue']);
    }

    public function test_an_invoice_inside_the_grace_period_is_not_overdue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 13:27:31'));

        $result = $this->invoiceDated(Carbon::parse('2026-08-10 09:14:02'))->calculateLateFees();

        $this->assertFalse($result['is_past_due']);
        $this->assertSame(0, $result['days_overdue']);
        $this->assertSame(0.0, $result['late_fee_amount']);
        $this->assertSame(1000.0, $result['total_with_late_fees']);
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
    }

    // ---- TASK-443 -------------------------------------------------------

    /** The reproduction from the task: $1,000, 65 days old, nothing applied yet. */
    public function test_an_unapplied_fee_is_shown_and_due_once(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-04 12:00:00'));

        $result = $this->invoiceDated(Carbon::parse('2026-07-01 12:00:00'))->calculateLateFees();

        $this->assertTrue($result['is_past_due']);
        $this->assertSame(1, $result['late_fee_periods']);
        $this->assertSame(100.0, $result['late_fee_amount']);
        $this->assertSame(100.0, $result['additional_late_fee_amount']);
        $this->assertSame(0.0, $result['applied_late_fee_amount']);
        $this->assertFalse($result['late_fees_applied']);
        $this->assertSame(1100.0, $result['total_with_late_fees']);
    }

    /**
     * The defect. After "Apply", the saved fee was added to a total that
     * already contained it: $1,000 + $100 became $1,200 on every later read.
     */
    public function test_an_applied_fee_is_not_added_twice(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-04 12:00:00'));

        $result = $this->invoiceDated(
            Carbon::parse('2026-07-01 12:00:00'),
            1000.0,
            ['late_fees' => $this->appliedFee(1)]
        )->calculateLateFees();

        $this->assertTrue($result['late_fees_applied']);
        $this->assertSame(1, $result['late_fee_periods']);
        $this->assertSame(100.0, $result['late_fee_amount']);
        $this->assertSame(100.0, $result['applied_late_fee_amount']);
        $this->assertSame(0.0, $result['additional_late_fee_amount']);
        $this->assertSame(0, $result['additional_late_fee_periods']);
        $this->assertSame(1100.0, $result['total_with_late_fees']);
    }

    /**
     * A month later one more period has accrued. The applied fee stays locked
     * at $100 and only the new period is added -- the fee is not recomputed
     * from a fee-inclusive base, which is what compounded it before.
     */
    public function test_new_periods_since_the_apply_accrue_only_the_delta(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-04 12:00:00'));

        $result = $this->invoiceDated(
            Carbon::parse('2026-07-01 12:00:00'),
            1000.0,
            ['late_fees' => $this->appliedFee(1)]
        )->calculateLateFees();

        $this->assertSame(2, $result['late_fee_periods']);
        $this->assertSame(100.0, $result['applied_late_fee_amount']);
        $this->assertSame(1, $result['applied_late_fee_periods']);
        $this->assertSame(100.0, $result['additional_late_fee_amount']);
        $this->assertSame(1, $result['additional_late_fee_periods']);
        $this->assertSame(200.0, $result['late_fee_amount']);
        $this->assertSame(1200.0, $result['total_with_late_fees']);
    }

    /**
     * Once applied, the fee is a matter of record. Lowering the percentage
     * afterwards does not claw it back, and nothing new accrues on top when no
     * further period has passed.
     */
    public function test_an_applied_fee_is_locked_against_later_rate_changes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-04 12:00:00'));
        config(['pricing.payment_terms.late_fee_percentage' => 5.0]);

        $result = $this->invoiceDated(
            Carbon::parse('2026-07-01 12:00:00'),
            1000.0,
            ['late_fees' => $this->appliedFee(1)]
        )->calculateLateFees();

        $this->assertSame(100.0, $result['late_fee_amount']);
        $this->assertSame(1100.0, $result['total_with_late_fees']);
    }

    /**
     * A paid invoice accrues nothing more, but the fee that WAS applied still
     * belongs to it: the customer paid $1,100, and Total Due must keep saying
     * $1,100 rather than dropping back to the subtotal.
     */
    public function test_a_paid_invoice_keeps_its_applied_fee_and_accrues_no_more(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-01 12:00:00'));

        $invoice = $this->invoiceDated(
            Carbon::parse('2026-07-01 12:00:00'),
            1000.0,
            ['late_fees' => $this->appliedFee(1)]
        );
        $invoice->paid_in_full = true;

        $result = $invoice->calculateLateFees();

        $this->assertFalse($result['is_past_due']);
        $this->assertSame(100.0, $result['late_fee_amount']);
        $this->assertSame(0.0, $result['additional_late_fee_amount']);
        $this->assertSame(1100.0, $result['total_with_late_fees']);
    }

    /**
     * A child rolled into a summary is paid through the summary, which accrues
     * its own fee on its own date. Two documents each charging for the same
     * lateness billed the customer twice.
     */
    public function test_a_child_of_a_summary_accrues_nothing_of_its_own(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-04 12:00:00'));

        $invoice = $this->invoiceDated(Carbon::parse('2026-07-01 12:00:00'));
        $invoice->parent_invoice_id = 99;

        $result = $invoice->calculateLateFees();

        $this->assertFalse($result['is_past_due']);
        $this->assertSame(0.0, $result['late_fee_amount']);
        $this->assertSame(1000.0, $result['total_with_late_fees']);
    }

    /**
     * The pre-fix `original_total` was written as the fee-inclusive figure on
     * a re-apply. It is now purely a record: the arithmetic never reads it,
     * so a wrong value in an old row cannot compound anything.
     */
    public function test_original_total_is_never_read_into_the_arithmetic(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-04 12:00:00'));

        $fees = $this->appliedFee(1);
        $fees['original_total'] = 1100.0; // the old, wrong record

        $result = $this->invoiceDated(Carbon::parse('2026-07-01 12:00:00'), 1000.0, ['late_fees' => $fees])
            ->calculateLateFees();

        // Second period accrues on the $1,000 subtotal, not on $1,100.
        $this->assertSame(200.0, $result['late_fee_amount']);
        $this->assertSame(1200.0, $result['total_with_late_fees']);
    }
}
