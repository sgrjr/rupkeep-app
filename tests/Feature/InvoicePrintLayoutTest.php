<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-369 / TASK-370.
 *
 * The print view was cropping its bill-to block. Two causes: the header was a
 * display:table at width:100% WITH horizontal padding (so it measured wider
 * than .page and got sliced by overflow:hidden), and the full-bleed rules
 * TASK-340 added for dompdf were unconditional, stretching the browser preview
 * across the whole window.
 */
class InvoicePrintLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(): Invoice
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Casco Bay Steel Structures, Inc.',
        ]);

        $job = PilotCarJob::create([
            'job_no' => 'JOB-LAYOUT',
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'pickup_address' => 'Main Street, Portland ME',
            'delivery_address' => 'Boston MA',
            'rate_code' => 'flat_rate',
            'rate_value' => '575.00',
        ]);

        return $job->createInvoice();
    }

    private function staffFor(Invoice $invoice): User
    {
        return User::factory()->manager()->create([
            'organization_id' => $invoice->organization_id,
        ]);
    }

    public function test_the_browser_preview_is_marked_as_a_screen_render(): void
    {
        $invoice = $this->invoice();

        $html = $this->actingAs($this->staffFor($invoice))
            ->get(route('my.invoices.print', $invoice))
            ->assertOk()
            ->getContent();

        // --screen is what restores a letter-width page; without it the preview
        // stretches to the full browser window and stops resembling the PDF.
        preg_match('/<body class="([^"]*)"/', $html, $m);

        $this->assertNotEmpty($m);
        $this->assertStringContainsString('invoice-doc--screen', $m[1]);
    }

    public function test_the_pdf_render_is_not_marked_as_a_screen_render(): void
    {
        $invoice = $this->invoice();

        // The dompdf route passes forPdf, which must keep the page full-bleed.
        $html = view('invoices.print', [
            'invoice' => $invoice,
            'values' => $invoice->values,
            'forPdf' => true,
        ])->render();

        // Assert on the <body> tag, not the document: the inline stylesheet
        // legitimately mentions --screen in its own rules.
        preg_match('/<body class="([^"]*)"/', $html, $m);

        $this->assertNotEmpty($m, 'the print view must render a body class');
        $this->assertStringContainsString('invoice-doc--print', $m[1]);
        $this->assertStringNotContainsString('invoice-doc--screen', $m[1]);
    }

    public function test_the_full_customer_name_is_present_and_unclipped(): void
    {
        $invoice = $this->invoice();

        $html = $this->actingAs($this->staffFor($invoice))
            ->get(route('my.invoices.print', $invoice))
            ->getContent();

        $this->assertStringContainsString('Casco Bay Steel Structures, Inc.', $html);
    }

    public function test_the_print_header_does_not_combine_full_width_with_padding(): void
    {
        // The actual cause of the crop. Guards the specific regression rather
        // than the symptom: a display:table at width:100% plus horizontal
        // padding renders wider than its container in every engine.
        $css = file_get_contents(resource_path('views/invoices/templates/styles.blade.php'));

        $this->assertMatchesRegularExpression(
            '/\.invoice-doc--print header \{[^}]*padding: 0;[^}]*\}/s',
            $css,
            'the print header must not carry its own horizontal padding'
        );

        $this->assertStringContainsString('box-sizing: border-box', $css);
    }

    public function test_the_bare_invoice_url_redirects_to_the_edit_view(): void
    {
        $invoice = $this->invoice();

        // Previously a 405: only PUT was bound to this URI (TASK-370).
        $this->actingAs($this->staffFor($invoice))
            ->get('/my/invoices/' . $invoice->id)
            ->assertRedirect(route('my.invoices.edit', ['invoice' => $invoice->id]));
    }
}
