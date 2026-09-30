<?php

namespace Tests\Feature;

use App\Livewire\CancelJob;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\PricingSetting;
use App\Models\User;
use App\Services\PricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-451. Every invoice carried Casco Bay's name, contact, P.O. box and
 * thank-you line, typed into PilotCarJob::invoiceValues(); the print template
 * fell back to Casco Bay's phone number; the payment-terms sentence came from
 * a fixed config string that still said "10% every 30 days" after an
 * organization changed its percentage; cancelling a job priced the outcome
 * from config rather than the organization's own price list; and building an
 * invoice for a job whose customer was gone threw.
 */
class InvoiceLetterheadTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $manager;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->organization = Organization::factory()->create([
            'name' => 'Northwind Escorts',
            'primary_contact' => 'Pat Northwind',
            'telephone' => '555-0100',
            'street' => '9 Harbor Way',
            'city' => 'Rockland',
            'state' => 'ME',
            'zip' => '04841',
        ]);
        $this->manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function job(array $attributes = []): PilotCarJob
    {
        return PilotCarJob::create(array_merge([
            'job_no' => 'JOB-451',
            'customer_id' => $this->customer->id,
            'organization_id' => $this->organization->id,
            'rate_code' => 'flat_rate',
            'rate_value' => '400.00',
        ], $attributes));
    }

    public function test_the_letterhead_and_footer_come_from_the_organization(): void
    {
        $values = $this->job()->invoiceValues()['values'];

        $this->assertSame([
            'company' => 'Northwind Escorts',
            'attention' => 'Pat Northwind',
            'street' => '9 Harbor Way',
            'city' => 'Rockland',
            'state' => 'ME',
            'zip' => '04841',
        ], $values['bill_from']);

        $this->assertStringContainsString('Northwind Escorts would like to thank you', $values['footer']);
        $this->assertStringContainsString('555-0100', $values['footer']);
        $this->assertStringNotContainsString('Casco Bay', json_encode($values));
        $this->assertStringNotContainsString('Mary Reynolds', json_encode($values));
    }

    public function test_an_email_address_as_primary_contact_is_not_printed_as_the_attention_line(): void
    {
        $this->organization->update(['primary_contact' => 'office@northwind.example']);

        $values = $this->job()->fresh()->invoiceValues()['values'];

        $this->assertNull($values['bill_from']['attention']);
    }

    public function test_the_printed_invoice_names_no_other_company(): void
    {
        $invoice = $this->job()->createInvoice();

        $html = $this->actingAs($this->manager)->get(route('my.invoices.print', $invoice))->assertOk()->getContent();

        $this->assertStringContainsString('Northwind Escorts', $html);
        $this->assertStringContainsString('9 Harbor Way', $html);
        $this->assertStringContainsString('555-0100', $html);
        $this->assertStringNotContainsString('Casco Bay', $html);
        $this->assertStringNotContainsString('207-712-8064', $html);
    }

    public function test_an_old_snapshot_without_a_footer_falls_back_to_the_organization_not_to_casco_bay(): void
    {
        $this->organization->update(['telephone' => null]);
        $invoice = $this->job()->createInvoice();

        $values = $invoice->values;
        unset($values['footer']);
        $invoice->values = $values;
        $invoice->save();

        $html = $this->actingAs($this->manager)->get(route('my.invoices.print', $invoice))->assertOk()->getContent();

        $this->assertStringContainsString('Northwind Escorts would like to thank you', $html);
        $this->assertStringNotContainsString('207-712-8064', $html);
        $this->assertStringNotContainsString('Casco Bay', $html);
    }

    public function test_a_job_whose_customer_is_gone_still_builds_an_invoice(): void
    {
        $job = $this->job();

        // Deleting the customer row cascades to the job (TASK-471 is about
        // that), and SQLite will not drop the constraint inside the test
        // transaction, so present the job the way production does when the
        // customer is gone: the relation resolves to null.
        $job->setRelation('customer', null);
        $this->assertNull($job->customer);

        $values = $job->invoiceValues()['values'];

        $this->assertNull($values['bill_to']['company']);
        $this->assertEquals(400.0, (float) $values['total']);
    }

    public function test_the_payment_terms_sentence_follows_the_organizations_numbers(): void
    {
        PricingSetting::setValueForOrganization($this->organization->id, 'payment_terms.late_fee_percentage', 5, 'float', 'payment_terms');
        PricingSetting::setValueForOrganization($this->organization->id, 'payment_terms.grace_period_days', 45, 'integer', 'payment_terms');

        $terms = PricingResolver::paymentTerms($this->organization->id);

        $this->assertFalse($terms['terms_text_is_custom']);
        $this->assertStringContainsString('45 days after the invoice date', $terms['terms_text']);
        $this->assertStringContainsString('5% interest is charged every 30 days', $terms['terms_text']);
        $this->assertStringNotContainsString('10%', $terms['terms_text']);

        $invoice = $this->job()->createInvoice();
        $html = $this->actingAs($this->manager)->get(route('my.invoices.print', $invoice))->assertOk()->getContent();

        $this->assertStringContainsString('5% interest is charged every 30 days', $html);
        $this->assertStringNotContainsString('10% interest', $html);
    }

    public function test_an_organizations_own_terms_sentence_wins(): void
    {
        PricingSetting::setValueForOrganization($this->organization->id, 'payment_terms.terms_text', 'Net 15. No exceptions.', 'string', 'payment_terms');

        $terms = PricingResolver::paymentTerms($this->organization->id);
        $this->assertTrue($terms['terms_text_is_custom']);
        $this->assertSame('Net 15. No exceptions.', $terms['terms_text']);

        $invoice = $this->job()->createInvoice();
        $this->actingAs($this->manager)->get(route('my.invoices.print', $invoice))->assertOk()->assertSee('Net 15. No exceptions.');
    }

    public function test_the_default_terms_sentence_matches_the_default_numbers(): void
    {
        $text = PricingResolver::paymentTerms(null)['terms_text'];

        $this->assertStringContainsString('Payment is due upon receipt', $text);
        $this->assertStringContainsString('30 days after the invoice date', $text);
        $this->assertStringContainsString('10% interest is charged every 30 days', $text);
    }

    public function test_cancelling_a_job_prices_the_outcome_from_the_organizations_price_list(): void
    {
        PricingSetting::setValueForOrganization($this->organization->id, 'rates.show_no_go.flat_amount', 275, 'float', 'rates');

        $job = $this->job(['rate_code' => 'per_mile_rate_2_00', 'rate_value' => '2.00']);

        Livewire::actingAs($this->manager)
            ->test(CancelJob::class, ['job' => $job])
            ->set('cancellationReason', 'Load not ready')
            ->set('cancellationType', 'show_no_go')
            ->call('cancel')
            ->assertHasNoErrors();

        $job->refresh();
        $this->assertNotNull($job->canceled_at);
        $this->assertSame('show_no_go', $job->rate_code);
        $this->assertEquals(275.0, (float) $job->rate_value);
    }
}
