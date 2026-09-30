<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-450. The job CSV exported wait HOURS in a dollar column, a Subtotal
 * and Gas that were always 0, `updated_at` as the payment date, "Unpaid" for
 * a partly paid invoice, and the job's current rate rather than the one the
 * invoice was cut at. The QuickBooks feed omitted a summary's hand-set
 * discount and any applied late fee, so its totals diverged from the paper
 * invoice. Both wrote free text raw, so a memo starting with "=" ran as a
 * formula in Excel.
 */
class ExportFiguresTest extends TestCase
{
    use RefreshDatabase;

    // Job CSV columns.
    private const RATE_CODE = 7;
    private const RATE_VALUE = 8;
    private const SUBTOTAL = 9;
    private const HOTEL = 10;
    private const TOLLS = 11;
    private const GAS = 12;
    private const WAIT = 13;
    private const EXTRA = 14;
    private const DEADHEAD = 17;
    private const MINI = 18;
    private const TOTAL = 19;
    private const PAID_STATUS = 20;
    private const PAYMENT_DATE = 21;
    private const MEMO = 23;

    // QuickBooks columns.
    private const QB_MEMO = 6;
    private const QB_ITEM = 7;
    private const QB_DESCRIPTION = 8;
    private const QB_AMOUNT = 11;

    private Organization $organization;
    private Customer $customer;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
    }

    private function job(string $no = 'JOB-450'): PilotCarJob
    {
        return PilotCarJob::create([
            'job_no' => $no,
            'customer_id' => $this->customer->id,
            'organization_id' => $this->organization->id,
            'pickup_address' => 'Gorham, ME',
            'delivery_address' => 'Boston, MA',
            'rate_code' => 'per_mile_rate_2_00',
            'rate_value' => '2.00',
        ]);
    }

    private function invoice(array $values, ?PilotCarJob $job = null, array $attributes = []): Invoice
    {
        return Invoice::create(array_merge([
            'status' => Invoice::STATUS_SENT,
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'pilot_car_job_id' => $job?->id,
            'values' => $values,
        ], $attributes));
    }

    private function rows(string $route, array $filters = []): array
    {
        $content = $this->actingAs($this->manager)->get(route($route, $filters))->assertOk()->streamedContent();

        return array_map('str_getcsv', array_values(array_filter(explode("\n", trim($content)))));
    }

    public function test_the_job_csv_money_columns_are_dollars_and_add_up_to_the_total(): void
    {
        // 200 mi x $2 = 400, plus 3 wait hours billed at $30 (first free) = 60,
        // hotel 125, tolls 10, deadhead 40, mini 25, extra 15: total 675.
        $this->invoice([
            'total' => 675,
            'billable_miles' => 200,
            'cost_for_mileage' => 400,
            'wait_time_hours' => 3,
            'wait_time_billable_hours' => 2,
            'wait_time_rate' => 30,
            'cost_of_wait_time' => 60,
            'hotel' => 125,
            'tolls' => 10,
            'dead_head_charge' => 40,
            'mini_addon_amount' => 25,
            'extra_charge' => 15,
        ], $this->job());

        $row = $this->rows('my.invoices.export.jobs')[1];

        $this->assertSame('60.00', $row[self::WAIT], 'dollars, not the 3.00 hours this used to export');
        $this->assertSame('125.00', $row[self::HOTEL]);
        $this->assertSame('10.00', $row[self::TOLLS]);
        $this->assertSame('40.00', $row[self::DEADHEAD]);
        $this->assertSame('25.00', $row[self::MINI]);
        $this->assertSame('15.00', $row[self::EXTRA]);
        $this->assertSame('675.00', $row[self::TOTAL]);
        $this->assertSame('400.00', $row[self::SUBTOTAL], 'what is left once every other column is taken out');

        $sum = 0.0;
        foreach ([self::SUBTOTAL, self::HOTEL, self::TOLLS, self::WAIT, self::EXTRA, self::DEADHEAD, self::MINI] as $column) {
            $sum += (float) $row[$column];
        }
        $this->assertEqualsWithDelta(675.0, $sum, 0.001, 'the row reconciles');
    }

    public function test_gas_comes_from_the_drivers_logs_and_is_not_billed(): void
    {
        $job = $this->job();
        foreach ([42.5, 17.5] as $gas) {
            UserLog::create([
                'job_id' => $job->id,
                'organization_id' => $this->organization->id,
                'approval_status' => 'confirmed',
                'gas' => $gas,
            ]);
        }

        $this->invoice(['total' => 400, 'cost_for_mileage' => 400, 'billable_miles' => 200], $job);

        $row = $this->rows('my.invoices.export.jobs')[1];

        $this->assertSame('60.00', $row[self::GAS]);
        $this->assertSame('400.00', $row[self::SUBTOTAL], 'fuel is outside the billed columns');
    }

    public function test_paid_status_and_payment_date_come_from_the_recorded_payments(): void
    {
        $partial = $this->invoice([
            'total' => 500,
            'payments' => [
                ['amount' => 200, 'payment_date' => '2026-03-02'],
                ['amount' => 100, 'payment_date' => '2026-04-15'],
            ],
        ], $this->job('JOB-PARTIAL'));

        $paid = $this->invoice(['total' => 300], $this->job('JOB-PAID'), ['paid_in_full' => true]);
        $paid->forceFill(['paid_at' => '2026-05-20 10:00:00'])->save();
        // A later edit must not move the payment date.
        $paid->forceFill(['updated_at' => '2026-06-30 10:00:00'])->saveQuietly();

        $rows = collect($this->rows('my.invoices.export.jobs'))->slice(1)->keyBy(0);

        $this->assertSame('Partial', $rows[(string) $partial->invoice_number][self::PAID_STATUS]);
        $this->assertSame('04/15/2026', $rows[(string) $partial->invoice_number][self::PAYMENT_DATE], 'the latest payment');

        $this->assertSame('Paid', $rows[(string) $paid->invoice_number][self::PAID_STATUS]);
        $this->assertSame('05/20/2026', $rows[(string) $paid->invoice_number][self::PAYMENT_DATE], 'paid_at, not updated_at');
    }

    public function test_the_rate_is_the_one_the_invoice_was_cut_at(): void
    {
        $job = $this->job();
        $this->invoice(['total' => 400, 'rate_code' => 'flat_rate', 'rate_value' => '400.00'], $job);

        // The job's rate changed after invoicing.
        $job->update(['rate_code' => 'per_mile_rate_3_00', 'rate_value' => '3.00']);

        $row = $this->rows('my.invoices.export.jobs')[1];

        $this->assertSame('flat_rate', $row[self::RATE_CODE]);
        $this->assertSame('400.00', $row[self::RATE_VALUE]);
    }

    public function test_free_text_that_looks_like_a_formula_is_neutralised_in_both_exports(): void
    {
        $this->invoice(['total' => 400, 'notes' => '=HYPERLINK("http://evil.example")'], $this->job());

        $csv = $this->rows('my.invoices.export.jobs')[1];
        $this->assertStringStartsWith("'=", $csv[self::MEMO]);

        // With a job, the memo starts with "Job ..." and needs no guard. An
        // orphan invoice's memo is the note itself, so there it is guarded.
        $orphan = $this->invoice(['total' => 100, 'notes' => '=1+1']);

        $qb = collect($this->rows('my.invoices.export.quickbooks'))->slice(1)->keyBy(0);
        $this->assertStringStartsWith('Job JOB-450 - =HYPERLINK', $qb->first()[self::QB_MEMO]);
        $this->assertSame("'=1+1", $qb[(string) $orphan->invoice_number][self::QB_MEMO]);

        // A negative amount is a number and must stay one.
        $this->assertMatchesRegularExpression('/^-?\d+\.\d\d$/', $qb->first()[self::QB_AMOUNT]);
    }

    public function test_quickbooks_carries_a_summary_discount_and_an_applied_late_fee_as_lines(): void
    {
        $summary = $this->invoice(['total' => 900, 'title' => 'SUMMARY INVOICE'], null, ['invoice_type' => 'summary']);
        foreach (['JOB-A' => 500, 'JOB-B' => 500] as $jobNo => $total) {
            $this->invoice(
                ['total' => $total, 'cost_for_mileage' => $total, 'billable_miles' => $total / 2, 'job_no' => $jobNo],
                $this->job($jobNo),
                ['parent_invoice_id' => $summary->id]
            );
        }

        $late = $this->invoice([
            'total' => 400,
            'cost_for_mileage' => 400,
            'billable_miles' => 200,
            'late_fees' => ['applied_at' => '2026-05-01 09:00:00', 'late_fee_amount' => 40, 'late_fee_periods' => 1],
        ], $this->job('JOB-LATE'));

        $rows = collect($this->rows('my.invoices.export.quickbooks'))->slice(1)->groupBy(0);

        $summaryRows = $rows[(string) $summary->fresh()->invoice_number];
        $this->assertEqualsWithDelta(900.0, $summaryRows->sum(fn ($r) => (float) $r[self::QB_AMOUNT]), 0.001, 'the QuickBooks total is the paper total');
        $discount = $summaryRows->firstWhere(self::QB_ITEM, 'Adjustment');
        $this->assertNotNull($discount);
        $this->assertSame('-100.00', $discount[self::QB_AMOUNT]);
        $this->assertSame('Summary discount', $discount[self::QB_DESCRIPTION]);

        $lateRows = $rows[(string) $late->fresh()->invoice_number];
        $fee = $lateRows->firstWhere(self::QB_ITEM, 'Late Fee');
        $this->assertNotNull($fee);
        $this->assertSame('40.00', $fee[self::QB_AMOUNT]);
        $this->assertSame('Late fee applied 05/01/2026', $fee[self::QB_DESCRIPTION]);
        $this->assertEqualsWithDelta(440.0, $lateRows->sum(fn ($r) => (float) $r[self::QB_AMOUNT]), 0.001);
    }
}
