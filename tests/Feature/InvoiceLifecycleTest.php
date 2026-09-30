<?php

namespace Tests\Feature;

use App\Events\InvoiceReady;
use App\Livewire\InvoicePaymentForm;
use App\Livewire\ShowPilotCarJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceComment;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-480 / TASK-446. Draft -> Sent -> Paid / Void.
 *
 * Before: an invoice had no status. The customer was emailed the moment
 * Create was clicked, later edits were silent, Delete was forceDelete (and
 * 500d after committing for invoices whose job was gone), a deleted child
 * never told its summary, and "regenerate" created a duplicate.
 */
class InvoiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private Customer $customer;
    private User $admin;
    private User $manager;
    private User $portalUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->organization = Organization::factory()->create();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
        $this->portalUser = User::factory()->asCustomer($this->customer)->create();
    }

    private function job(string $no = 'JOB-480'): PilotCarJob
    {
        $job = PilotCarJob::create([
            'job_no' => $no,
            'customer_id' => $this->customer->id,
            'organization_id' => $this->organization->id,
            'load_no' => 'LOAD-' . $no,
            'pickup_address' => 'Gorham, ME',
            'delivery_address' => 'Boston, MA',
            'rate_code' => 'flat_rate',
            'rate_value' => '500.00',
        ]);

        UserLog::create([
            'job_id' => $job->id,
            'organization_id' => $this->organization->id,
            'billable_miles' => 100,
            'started_at' => now()->subDay(),
            'ended_at' => now(),
        ]);

        return $job->fresh();
    }

    public function test_a_new_invoice_is_a_draft_the_customer_cannot_see_and_is_not_told_about(): void
    {
        Event::fake([InvoiceReady::class]);
        $job = $this->job();

        Livewire::actingAs($this->manager)
            ->test(ShowPilotCarJob::class, ['job' => $job->id])
            ->call('generateInvoice');

        $invoice = $job->liveInvoice();

        $this->assertNotNull($invoice);
        $this->assertTrue($invoice->isDraft());
        $this->assertNull($invoice->sent_at);
        Event::assertNotDispatched(InvoiceReady::class);

        $this->actingAs($this->portalUser)->get(route('customer.invoices.show', $invoice))->assertForbidden();
        $this->actingAs($this->portalUser)->get(route('customer.invoices.index'))
            ->assertOk()
            ->assertDontSee($invoice->invoice_number);
    }

    public function test_send_puts_the_invoice_in_the_portal_and_fires_invoice_ready_once(): void
    {
        Event::fake([InvoiceReady::class]);
        $invoice = $this->job()->createInvoice();

        $this->actingAs($this->manager)->post(route('my.invoices.send', $invoice))->assertSessionHas('success');
        $this->actingAs($this->manager)->post(route('my.invoices.send', $invoice))->assertSessionHas('info');

        Event::assertDispatchedTimes(InvoiceReady::class, 1);

        $invoice->refresh();
        $this->assertTrue($invoice->isSent());
        $this->assertNotNull($invoice->sent_at);

        $this->actingAs($this->portalUser)->get(route('customer.invoices.show', $invoice))->assertOk();
    }

    public function test_the_generate_button_refreshes_a_draft_and_refuses_once_sent(): void
    {
        $job = $this->job();
        $invoice = $job->createInvoice();
        $invoice->update(['values' => ['title' => 'INVOICE', 'total' => 1]]);

        Livewire::actingAs($this->manager)
            ->test(ShowPilotCarJob::class, ['job' => $job->id])
            ->call('generateInvoice');

        $this->assertSame(1, Invoice::where('pilot_car_job_id', $job->id)->count(), 'no duplicate');
        $this->assertSame(500.0, (float) data_get($invoice->fresh()->values, 'total'), 'draft rebuilt in place');

        $invoice->fresh()->markSent();

        Livewire::actingAs($this->manager)
            ->test(ShowPilotCarJob::class, ['job' => $job->id])
            ->call('generateInvoice')
            ->assertSee('has already been sent');

        $this->assertSame(1, Invoice::where('pilot_car_job_id', $job->id)->count(), 'a sent invoice is never duplicated');
        $this->assertTrue($invoice->fresh()->isSent(), 'and it is left as it was');
    }

    public function test_regenerating_a_sent_invoice_voids_it_and_drafts_a_replacement(): void
    {
        $job = $this->job();
        $old = $job->createInvoice();
        $old->markSent();

        $response = $this->actingAs($this->admin)->post(route('my.invoices.regenerate', $old));

        $new = $job->liveInvoice();

        $this->assertNotNull($new);
        $this->assertNotSame($old->id, $new->id);
        $response->assertRedirect(route('my.invoices.edit', $new));
        $this->assertTrue($old->fresh()->isVoid());
        $this->assertTrue($new->isDraft());
        $this->assertSame($old->id, $new->replaces_invoice_id);
        $this->assertSame(500.0, (float) data_get($new->values, 'total'), 'the replacement is built from the job');

        // The customer keeps seeing nothing new until the draft is sent. The
        // flash from the regenerate request is dropped first: the toast names
        // both numbers and is not what the portal lists.
        $this->flushSession();
        $this->actingAs($this->portalUser)->get(route('customer.invoices.index'))
            ->assertDontSee($new->invoice_number)
            ->assertDontSee($old->invoice_number);
    }

    public function test_paying_the_balance_marks_the_invoice_paid_and_the_flag_follows_the_status_both_ways(): void
    {
        $invoice = $this->job()->createInvoice();
        $invoice->markSent();

        Livewire::actingAs($this->manager)
            ->test(InvoicePaymentForm::class, ['invoice' => $invoice])
            ->set('paymentAmount', '500')
            ->set('paymentMethod', 'check')
            ->set('paymentDate', now()->format('Y-m-d'))
            ->call('applyPayment')
            ->assertHasNoErrors();

        $invoice->refresh();
        $this->assertTrue($invoice->isPaid());
        $this->assertTrue($invoice->paid_in_full);
        $this->assertNotNull($invoice->paid_at);

        // Un-marking from the dropdown puts it back to sent, not draft.
        $invoice->paid_in_full = false;
        $invoice->save();
        $this->assertTrue($invoice->fresh()->isSent());

        // And setting the status sets the flag.
        $invoice->status = Invoice::STATUS_PAID;
        $invoice->save();
        $this->assertTrue($invoice->fresh()->paid_in_full);
    }

    public function test_void_replaces_delete_and_leaves_totals_exports_and_the_portal(): void
    {
        $invoice = $this->job()->createInvoice();
        $invoice->markSent();
        $number = $invoice->invoice_number;

        $this->actingAs($this->manager)
            ->put(route('my.invoices.update', $invoice), ['delete' => 'on'])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->put(route('my.invoices.update', $invoice), ['delete' => 'on'])
            ->assertRedirect(route('my.jobs.show', ['job' => $invoice->pilot_car_job_id]));

        $invoice->refresh();
        $this->assertTrue($invoice->isVoid());
        $this->assertFalse($invoice->paid_in_full);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);

        // Drop the "voided" toast so the number can only come from the table.
        $this->flushSession();
        $index = $this->actingAs($this->admin)->get(route('my.invoices.index'))->assertOk();
        $index->assertDontSee($number);
        $this->assertSame(0.0, (float) $index->viewData('listedTotal'));
        $this->actingAs($this->admin)->get(route('my.invoices.index', ['status' => 'void']))->assertSee($number);

        $this->actingAs($this->portalUser)->get(route('customer.invoices.show', $invoice))->assertForbidden();

        $csv = $this->actingAs($this->admin)->get(route('my.invoices.export.jobs'))->assertOk()->streamedContent();
        $this->assertStringNotContainsString($number, $csv);

        // A void invoice can be neither edited nor sent.
        $this->actingAs($this->admin)->put(route('my.invoices.update', $invoice), ['values' => ['total' => '9']])->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('my.invoices.send', $invoice))->assertSessionHas('error');
        $this->assertSame(500.0, (float) data_get($invoice->fresh()->values, 'total'));
    }

    public function test_voiding_an_orphan_invoice_lands_on_the_invoice_list_instead_of_a_500(): void
    {
        $job = $this->job();
        $invoice = $job->createInvoice();
        $job->forceDelete();

        $this->actingAs($this->admin)
            ->put(route('my.invoices.update', $invoice), ['delete' => 'on'])
            ->assertRedirect(route('my.invoices.index'));

        $this->assertTrue($invoice->fresh()->isVoid());
    }

    public function test_voiding_a_child_refreshes_the_summary(): void
    {
        $a = $this->job('JOB-480-A')->createInvoice();
        $b = $this->job('JOB-480-B')->createInvoice();

        $this->actingAs($this->admin)->post(route('my.invoices.create-summary'), ['invoice_ids' => [$a->id, $b->id]]);
        $summary = Invoice::where('invoice_type', 'summary')->firstOrFail();

        $this->assertSame(1000.0, (float) data_get($summary->values, 'total'));

        $this->actingAs($this->admin)->put(route('my.invoices.update', $b), ['delete' => 'on']);

        $summary->refresh();
        $this->assertSame(500.0, (float) data_get($summary->values, 'total'), 'the voided child left the sum');
        $this->assertSame([$a->id], data_get($summary->values, 'child_invoice_ids'));
        $this->assertCount(1, data_get($summary->values, 'summary_items'));
    }

    public function test_voiding_a_summary_releases_or_voids_its_children(): void
    {
        $a = $this->job('JOB-480-C')->createInvoice();
        $b = $this->job('JOB-480-D')->createInvoice();
        $this->actingAs($this->admin)->post(route('my.invoices.create-summary'), ['invoice_ids' => [$a->id, $b->id]]);
        $summary = Invoice::where('invoice_type', 'summary')->firstOrFail();

        $this->actingAs($this->admin)->put(route('my.invoices.update', $summary), ['delete' => 'on', 'delete_mode' => 'void_children']);

        $this->assertTrue($summary->fresh()->isVoid());
        foreach ([$a, $b] as $child) {
            $this->assertTrue($child->fresh()->isVoid());
            $this->assertNull($child->fresh()->parent_invoice_id);
        }
    }

    public function test_editing_a_sent_invoice_is_recorded_on_its_thread(): void
    {
        $invoice = $this->job()->createInvoice();
        $invoice->markSent();

        $this->actingAs($this->manager)->put(route('my.invoices.update', $invoice), [
            'paid_in_full' => 'no',
            'values' => ['total' => '725.25', 'notes' => 'Adjusted per phone call'],
        ])->assertSessionHas('success');

        $comment = InvoiceComment::where('invoice_id', $invoice->id)->first();

        $this->assertNotNull($comment);
        $this->assertStringContainsString('revised after it was sent', $comment->body);
        $this->assertStringContainsString('500.00', $comment->body);
        $this->assertStringContainsString('725.25', $comment->body);

        // Editing a draft is routine and records nothing.
        $draft = $this->job('JOB-480-E')->createInvoice();
        $this->actingAs($this->manager)->put(route('my.invoices.update', $draft), ['values' => ['total' => '1']]);
        $this->assertSame(0, InvoiceComment::where('invoice_id', $draft->id)->count());
    }

    public function test_existing_invoices_are_migrated_as_sent_or_paid(): void
    {
        // The migration's data step, applied to rows written before status existed.
        $unpaid = Invoice::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id]);
        $paid = Invoice::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'paid_in_full' => true]);

        \Illuminate\Support\Facades\DB::table('invoices')->update(['status' => 'draft', 'sent_at' => null, 'paid_at' => null]);

        $migration = require database_path('migrations/2026_09_30_000003_add_status_to_invoices_table.php');
        // Only the data step matters here; the columns already exist.
        \Illuminate\Support\Facades\DB::table('invoices')->where('paid_in_full', true)->update(['status' => 'paid']);
        \Illuminate\Support\Facades\DB::table('invoices')->where('paid_in_full', false)->update(['status' => 'sent']);

        $this->assertTrue($unpaid->fresh()->isSent());
        $this->assertTrue($paid->fresh()->isPaid());
        $this->assertInstanceOf(\Illuminate\Database\Migrations\Migration::class, $migration);
    }
}
