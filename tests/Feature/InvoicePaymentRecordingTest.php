<?php

namespace Tests\Feature;

use App\Livewire\InvoicePaymentForm;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Services\InvoicePayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-449. Recording a payment compared unrounded floats to decide "paid",
 * ran with no transaction or lock so a double-click wrote two payments,
 * required a cash amount so a payment could not be made from account credit
 * alone, set the overpayment on the payment row after the row had already
 * been appended (so it was never stored), and dispatched a success flash that
 * nothing rendered while the totals around the modal stayed stale.
 */
class InvoicePaymentRecordingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $manager;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->organization = Organization::factory()->create();
        $this->manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'account_credit' => 0]);
    }

    private function invoice(float $total, array $overrides = []): Invoice
    {
        return Invoice::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'status' => Invoice::STATUS_SENT,
            'paid_in_full' => false,
            'values' => ['title' => 'INVOICE', 'total' => $total],
        ], $overrides));
    }

    private function form(Invoice $invoice)
    {
        return Livewire::actingAs($this->manager)
            ->test(InvoicePaymentForm::class, ['invoice' => $invoice])
            ->call('openModal');
    }

    public function test_a_balance_paid_to_the_cent_is_paid_even_when_the_floats_disagree(): void
    {
        // 0.7 + 0.1 is 0.7999999999999999 in binary floating point, which is
        // less than 0.8, so the old comparison left this invoice unpaid.
        $invoice = $this->invoice(0.80);

        InvoicePayments::record($invoice, ['cash_amount' => '0.70']);
        $this->assertFalse($invoice->fresh()->paid_in_full);

        InvoicePayments::record($invoice, ['cash_amount' => '0.10']);

        $invoice->refresh();
        $this->assertTrue($invoice->paid_in_full);
        $this->assertTrue($invoice->isPaid());
        $this->assertSame(0.0, $invoice->remaining_balance);
        $this->assertSame(0.8, (float) data_get($invoice->values, 'total_paid'));
        $this->assertEquals(0.0, (float) $this->customer->fresh()->account_credit, 'no overpayment was invented from the rounding');
    }

    public function test_a_double_click_records_one_payment(): void
    {
        $invoice = $this->invoice(500);

        $form = $this->form($invoice)
            ->set('paymentAmount', '200')
            ->set('paymentMethod', 'check')
            ->set('checkNumber', '4411');

        $key = $form->get('submissionKey');
        $this->assertNotSame('', $key);

        $form->call('applyPayment')->assertHasNoErrors();

        // The second request carries the same key the first one did.
        Livewire::actingAs($this->manager)
            ->test(InvoicePaymentForm::class, ['invoice' => $invoice->fresh()])
            ->set('showModal', true)
            ->set('submissionKey', $key)
            ->set('paymentAmount', '200')
            ->set('paymentMethod', 'check')
            ->set('checkNumber', '4411')
            ->call('applyPayment')
            ->assertHasNoErrors()
            ->assertRedirect(route('my.invoices.edit', $invoice));

        $invoice->refresh();
        $this->assertCount(1, $invoice->getPayments());
        $this->assertSame(200.0, $invoice->total_paid);
        $this->assertSame(300.0, $invoice->remaining_balance);
    }

    public function test_the_modal_mints_a_fresh_key_each_time_it_opens(): void
    {
        $invoice = $this->invoice(500);

        $form = $this->form($invoice);
        $first = $form->get('submissionKey');

        $form->call('closeModal')->call('openModal');

        $this->assertNotSame($first, $form->get('submissionKey'));
    }

    public function test_a_payment_can_be_made_entirely_from_account_credit(): void
    {
        $this->customer->update(['account_credit' => 250]);
        $invoice = $this->invoice(200);

        $this->form($invoice)
            ->set('paymentAmount', '')
            ->set('useAccountCredit', true)
            ->set('creditAmount', '200')
            ->call('applyPayment')
            ->assertHasNoErrors();

        $invoice->refresh();
        $payment = $invoice->getPayments()[0];

        $this->assertTrue($invoice->paid_in_full);
        $this->assertSame(0.0, (float) $payment['cash_amount']);
        $this->assertSame(200.0, (float) $payment['credit_amount']);
        $this->assertTrue($payment['used_credit']);
        $this->assertEquals(50.0, (float) $this->customer->fresh()->account_credit);
    }

    public function test_nothing_at_all_is_refused(): void
    {
        $invoice = $this->invoice(200);

        $this->form($invoice)
            ->set('paymentAmount', '0')
            ->call('applyPayment')
            ->assertHasErrors(['paymentAmount']);

        $this->assertCount(0, $invoice->fresh()->getPayments());
    }

    public function test_credit_beyond_what_the_customer_has_is_refused_and_nothing_is_written(): void
    {
        $this->customer->update(['account_credit' => 50]);
        $invoice = $this->invoice(200);

        $this->form($invoice)
            ->set('paymentAmount', '100')
            ->set('useAccountCredit', true)
            ->set('creditAmount', '75')
            ->call('applyPayment')
            ->assertHasErrors(['creditAmount']);

        $this->assertCount(0, $invoice->fresh()->getPayments());
        $this->assertEquals(50.0, (float) $this->customer->fresh()->account_credit);
    }

    public function test_the_overpayment_is_stored_on_the_payment_and_credited_once(): void
    {
        $invoice = $this->invoice(500);

        $this->form($invoice)
            ->set('paymentAmount', '600')
            ->call('applyPayment')
            ->assertHasNoErrors();

        $invoice->refresh();
        $payment = $invoice->getPayments()[0];

        $this->assertSame(100.0, (float) $payment['overpayment'], 'the overpayment is on the row the customer can see');
        $this->assertTrue($payment['overpayment_added_to_credit']);
        $this->assertTrue($invoice->paid_in_full);
        $this->assertEquals(100.0, (float) $this->customer->fresh()->account_credit);

        // A further payment on a paid invoice is all overpayment -- and only
        // ITS excess is credited, not the running total's.
        $this->form($invoice->fresh())
            ->set('paymentAmount', '50')
            ->call('applyPayment')
            ->assertHasNoErrors();

        $invoice->refresh();
        $this->assertSame(50.0, (float) $invoice->getPayments()[1]['overpayment']);
        $this->assertEquals(150.0, (float) $this->customer->fresh()->account_credit);
    }

    public function test_the_edit_page_shows_the_stored_overpayment(): void
    {
        $invoice = $this->invoice(500);
        InvoicePayments::record($invoice, ['cash_amount' => 600]);

        $this->actingAs($this->manager)
            ->get(route('my.invoices.edit', $invoice))
            ->assertOk()
            ->assertSee('$100.00')
            ->assertSee('overpayment');
    }

    public function test_recording_reloads_the_page_with_a_message_that_says_what_happened(): void
    {
        $invoice = $this->invoice(500);

        $this->form($invoice)
            ->set('paymentAmount', '200')
            ->call('applyPayment')
            ->assertHasNoErrors()
            ->assertRedirect(route('my.invoices.edit', $invoice))
            ->assertSessionHas('success', fn (string $m) => str_contains($m, '$200.00') && str_contains($m, 'Remaining balance $300.00'));

        $this->form($invoice->fresh())
            ->set('paymentAmount', '350')
            ->call('applyPayment')
            ->assertSessionHas('success', fn (string $m) => str_contains($m, 'paid in full') && str_contains($m, '$50.00'));
    }

    public function test_a_void_invoice_takes_no_payment(): void
    {
        $invoice = $this->invoice(500);
        $invoice->void($this->manager->id);

        $this->form($invoice->fresh())
            ->set('paymentAmount', '200')
            ->call('applyPayment')
            ->assertHasErrors(['paymentAmount']);

        $this->assertCount(0, $invoice->fresh()->getPayments());
    }

    public function test_paying_a_summary_in_full_marks_its_children_paid(): void
    {
        $jobA = PilotCarJob::create(['job_no' => 'JOB-449-A', 'customer_id' => $this->customer->id, 'organization_id' => $this->organization->id, 'rate_code' => 'flat_rate', 'rate_value' => '300']);
        $jobB = PilotCarJob::create(['job_no' => 'JOB-449-B', 'customer_id' => $this->customer->id, 'organization_id' => $this->organization->id, 'rate_code' => 'flat_rate', 'rate_value' => '200']);

        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($admin)->post(route('my.invoices.store'), ['invoice_this' => [$jobA->id, $jobB->id]]);

        $summary = Invoice::where('invoice_type', 'summary')->firstOrFail();
        $this->assertEquals(500.0, (float) data_get($summary->values, 'total'));

        foreach ($summary->children as $child) {
            $child->markSent();
        }
        $summary->markSent();

        $this->form($summary->fresh())
            ->set('paymentAmount', '500')
            ->call('applyPayment')
            ->assertHasNoErrors();

        $summary->refresh();
        $this->assertTrue($summary->paid_in_full);
        $this->assertTrue($summary->children()->get()->every(fn (Invoice $c) => $c->fresh()->paid_in_full));
        $this->assertTrue($summary->children()->get()->every(fn (Invoice $c) => $c->fresh()->remaining_balance === 0.0));
    }
}
