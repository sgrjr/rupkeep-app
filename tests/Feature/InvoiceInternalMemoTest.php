<?php

namespace Tests\Feature;

use App\Mail\InvoiceEmail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-444. Driver log memos are internal. The invoice template showed them
 * whenever the viewer had an organization_id -- which customer-portal users
 * carry -- and relied on `.no-print` to keep them off paper, which dompdf and
 * mail clients ignore. So the portal page, the emailed HTML and the PDF all
 * carried the driver's private notes.
 */
class InvoiceInternalMemoTest extends TestCase
{
    use RefreshDatabase;

    private const MEMO = 'Customer foreman was rude; do not send Dave back to this site.';
    private const PUBLIC_MEMO = 'Escort included overnight stop at Kittery.';

    private Organization $organization;
    private Customer $customer;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->organization = Organization::factory()->create();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $job = PilotCarJob::create([
            'job_no' => 'JOB-444',
            'customer_id' => $this->customer->id,
            'organization_id' => $this->organization->id,
            'pickup_address' => 'Gorham, ME',
            'delivery_address' => 'Boston, MA',
            'rate_code' => 'flat_rate',
            'rate_value' => '575.00',
            'public_memo' => self::PUBLIC_MEMO,
        ]);

        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id]);

        UserLog::create([
            'job_id' => $job->id,
            'organization_id' => $this->organization->id,
            'vehicle_id' => $vehicle->id,
            'approval_status' => 'confirmed',
            'start_mileage' => 0,
            'end_mileage' => 100,
            'start_job_mileage' => 0,
            'end_job_mileage' => 80,
            'memo' => self::MEMO,
        ]);

        $this->invoice = $job->fresh()->createInvoice();
        $this->invoice->markSent(); // the portal shows only what was sent (TASK-480)
    }

    public function test_the_customer_portal_never_shows_internal_log_memos(): void
    {
        $portalUser = User::factory()->asCustomer($this->customer)->create();

        $this->actingAs($portalUser)
            ->get(route('customer.invoices.show', $this->invoice))
            ->assertOk()
            ->assertSee(self::PUBLIC_MEMO)
            ->assertDontSee(self::MEMO)
            ->assertDontSee(__('Internal Log Memos (Not Printed on Invoice)'));
    }

    /**
     * The email is sent from a staff session, which is exactly when the old
     * auth()-based gate said yes.
     */
    public function test_the_emailed_invoice_html_never_carries_internal_log_memos(): void
    {
        $staff = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($staff);

        $html = (new InvoiceEmail($this->invoice, '', false))->render();

        $this->assertStringContainsString(self::PUBLIC_MEMO, $html);
        $this->assertStringNotContainsString(self::MEMO, $html);
    }

    /**
     * dompdf renders the screen stylesheet, so `.no-print` hides nothing. The
     * PDF must be built from HTML that never contained the memos at all.
     */
    public function test_the_pdf_source_never_carries_internal_log_memos(): void
    {
        $staff = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($staff);

        $html = view('invoices.print', [
            'invoice' => $this->invoice,
            'values' => $this->invoice->values,
            'forPdf' => true,
        ])->render();

        $this->assertStringContainsString(self::PUBLIC_MEMO, $html);
        $this->assertStringNotContainsString(self::MEMO, $html);
        preg_match('/<body class="([^"]*)"/', $html, $m);
        $this->assertStringNotContainsString('invoice-doc--screen', $m[1] ?? '', 'the PDF must not use the browser-preview layout');
    }

    /** The one place they belong: the staff browser preview, where .no-print applies. */
    public function test_staff_still_see_internal_log_memos_on_the_print_preview(): void
    {
        $staff = User::factory()->manager()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($staff)
            ->get(route('my.invoices.print', $this->invoice))
            ->assertOk()
            ->assertSee(self::MEMO)
            ->assertSee('class="no-print"', false);
    }
}
