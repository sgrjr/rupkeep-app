<?php

namespace Tests\Feature;

use App\Livewire\LogExtraCharges;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\LogExtraCharge;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-330. A driver log carried one unlabeled `extra_charge` amount, summed
 * across the job's logs into a single scalar that printed as "Extra Charges
 * $340.00" — so a one-off expense (renting special equipment for an unusual
 * job) reached the customer as an unexplained lump with no way to bill it back
 * legibly.
 *
 * Charges are now named rows on the log. The job view and invoice edit screens
 * write to the same rows; there is no second store, which is what keeps a
 * rebuilt invoice reproducing exactly the same charges.
 */
class ExtraChargeLineItemsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
    }

    private function job(): PilotCarJob
    {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $job = PilotCarJob::create([
            'job_no' => 'JOB-EXTRA',
            'customer_id' => $customer->id,
            'organization_id' => $this->organization->id,
            'pickup_address' => 'Main Street, Portland ME',
            'delivery_address' => 'Boston MA',
            'rate_code' => 'flat_rate',
            'rate_value' => '575.00',
        ]);

        return $job->fresh();
    }

    private function log(PilotCarJob $job, array $attributes = []): UserLog
    {
        return UserLog::create(array_merge([
            'job_id' => $job->id,
            'organization_id' => $this->organization->id,
            'approval_status' => 'confirmed',
            'start_mileage' => 123,
            'end_mileage' => 400,
            'start_job_mileage' => 123,
            'end_job_mileage' => 324,
        ], $attributes));
    }

    private function charge(UserLog $log, string $description, float $amount): LogExtraCharge
    {
        return LogExtraCharge::create([
            'user_log_id' => $log->id,
            'organization_id' => $log->organization_id,
            'description' => $description,
            'amount' => $amount,
        ]);
    }

    private function manager(): User
    {
        return User::factory()->manager()->create(['organization_id' => $this->organization->id]);
    }

    private function renderInvoice(Invoice $invoice): string
    {
        $response = $this->actingAs($this->manager())->get(route('my.invoices.print', $invoice));
        $response->assertOk();

        return $response->getContent();
    }

    public function test_each_named_charge_prints_as_its_own_line_item(): void
    {
        $job = $this->job();
        $log = $this->log($job);

        $this->charge($log, 'Equipment rental', 340.00);
        $this->charge($log, 'Permit expediting', 75.00);

        $html = $this->renderInvoice($job->fresh()->createInvoice());

        $this->assertStringContainsString('Equipment rental', $html);
        $this->assertStringContainsString('$340.00', $html);
        $this->assertStringContainsString('Permit expediting', $html);
        $this->assertStringContainsString('$75.00', $html);
    }

    public function test_charges_on_different_logs_of_the_same_job_both_appear(): void
    {
        $job = $this->job();

        $this->charge($this->log($job), 'Equipment rental', 340.00);
        $this->charge($this->log($job), 'Ferry crossing', 60.00);

        $html = $this->renderInvoice($job->fresh()->createInvoice());

        $this->assertStringContainsString('Equipment rental', $html);
        $this->assertStringContainsString('Ferry crossing', $html);
    }

    /**
     * The trap this feature had to avoid. The template derives Pilot Car
     * Service as (total − everything itemized), so a charge added to the
     * itemized side without raising the total silently comes OUT of the service
     * line and is never billed — the same failure as TASK-365 and TASK-367.
     */
    public function test_adding_a_charge_from_the_invoice_raises_the_total_and_leaves_the_service_line_alone(): void
    {
        $job = $this->job();
        $log = $this->log($job);
        $invoice = $job->fresh()->createInvoice();

        $totalBefore = (float) $invoice->values['total'];

        Livewire::actingAs($this->manager())
            ->test(LogExtraCharges::class, ['log' => $log, 'invoice' => $invoice])
            ->set('description', 'Equipment rental')
            ->set('amount', '340.00')
            ->call('addCharge')
            ->assertHasNoErrors();

        $values = $invoice->fresh()->values;

        $this->assertEqualsWithDelta($totalBefore + 340.00, (float) $values['total'], 0.01);
        $this->assertEqualsWithDelta(340.00, (float) $values['extra_charge'], 0.01);

        // The rate charge itself must not have moved.
        $html = $this->renderInvoice($invoice->fresh());
        $this->assertStringContainsString('$575.00', $html);
        $this->assertStringContainsString('Equipment rental', $html);
    }

    public function test_a_charge_added_from_the_invoice_lands_on_the_log(): void
    {
        $job = $this->job();
        $log = $this->log($job);
        $invoice = $job->fresh()->createInvoice();

        Livewire::actingAs($this->manager())
            ->test(LogExtraCharges::class, ['log' => $log, 'invoice' => $invoice])
            ->set('description', 'Equipment rental')
            ->set('amount', '340.00')
            ->call('addCharge');

        $this->assertDatabaseHas('log_extra_charges', [
            'user_log_id' => $log->id,
            'organization_id' => $this->organization->id,
            'description' => 'Equipment rental',
        ]);

        // ... and is visible from the log's own surface, not just the invoice.
        Livewire::actingAs($this->manager())
            ->test(LogExtraCharges::class, ['log' => $log->fresh()])
            ->assertSee('Equipment rental');
    }

    public function test_removing_a_charge_drops_the_invoice_total_back(): void
    {
        $job = $this->job();
        $log = $this->log($job);
        $invoice = $job->fresh()->createInvoice();
        $totalBefore = (float) $invoice->values['total'];

        $component = Livewire::actingAs($this->manager())
            ->test(LogExtraCharges::class, ['log' => $log, 'invoice' => $invoice])
            ->set('description', 'Equipment rental')
            ->set('amount', '340.00')
            ->call('addCharge');

        $charge = LogExtraCharge::firstOrFail();
        $component->call('removeCharge', $charge->id);

        $values = $invoice->fresh()->values;

        $this->assertSame([], $values['extra_charges']);
        $this->assertEqualsWithDelta($totalBefore, (float) $values['total'], 0.01);
        $this->assertDatabaseMissing('log_extra_charges', ['id' => $charge->id]);
    }

    /**
     * Every invoice issued before TASK-330 has the scalar and no array. Those
     * must keep rendering exactly as they did.
     */
    public function test_a_legacy_snapshot_still_renders_the_single_aggregate_row(): void
    {
        $job = $this->job();
        $this->log($job);
        $invoice = $job->fresh()->createInvoice();

        $values = $invoice->values;
        unset($values['extra_charges']);
        $values['extra_charge'] = '45.00';
        $values['total'] = (float) $values['total'] + 45.00;
        $invoice->values = $values;
        $invoice->save();

        $html = $this->renderInvoice($invoice->fresh());

        $this->assertStringContainsString('Extra Charges', $html);
        $this->assertStringContainsString('$45.00', $html);
    }

    /**
     * A log written by an older code path still holds the legacy column. It has
     * to keep billing, described the way invoices used to label it.
     */
    public function test_a_legacy_column_value_still_bills(): void
    {
        $job = $this->job();
        $this->log($job, ['extra_charge' => '45.00']);

        $job = $job->fresh();

        $this->assertSame('45.00', $job->getExtraCharges());
        $this->assertStringContainsString('Extra Charges', $this->renderInvoice($job->createInvoice()));
    }

    /**
     * InvoiceCalculationTest asserts this exact string format, and both
     * calculateTotalDue() and the render template consume it with a bare
     * (float) cast — a thousands separator would truncate it (TASK-353).
     */
    public function test_get_extra_charges_still_returns_a_formatted_string(): void
    {
        $job = $this->job();
        $log = $this->log($job);

        $this->charge($log, 'Equipment rental', 1000.50);
        $this->charge($log, 'Permit expediting', 500.25);

        $this->assertSame('1500.75', $job->fresh()->getExtraCharges());
    }

    public function test_a_blank_description_or_negative_amount_is_rejected(): void
    {
        $log = $this->log($this->job());

        Livewire::actingAs($this->manager())
            ->test(LogExtraCharges::class, ['log' => $log])
            ->set('description', '')
            ->set('amount', '10.00')
            ->call('addCharge')
            ->assertHasErrors(['description' => 'required']);

        Livewire::actingAs($this->manager())
            ->test(LogExtraCharges::class, ['log' => $log])
            ->set('description', 'Equipment rental')
            ->set('amount', '-5')
            ->call('addCharge')
            ->assertHasErrors(['amount' => 'min']);

        $this->assertDatabaseCount('log_extra_charges', 0);
    }

    public function test_a_user_from_another_organization_cannot_add_a_charge(): void
    {
        $log = $this->log($this->job());

        $otherOrg = Organization::factory()->create();
        $outsider = User::factory()->manager()->create(['organization_id' => $otherOrg->id]);

        Livewire::actingAs($outsider)
            ->test(LogExtraCharges::class, ['log' => $log])
            ->set('description', 'Equipment rental')
            ->set('amount', '340.00')
            ->call('addCharge')
            ->assertForbidden();

        $this->assertDatabaseCount('log_extra_charges', 0);
    }

    public function test_a_charge_id_from_another_log_cannot_be_removed(): void
    {
        $job = $this->job();
        $mine = $this->log($job);
        $theirs = $this->log($job);

        $victim = $this->charge($theirs, 'Ferry crossing', 60.00);

        Livewire::actingAs($this->manager())
            ->test(LogExtraCharges::class, ['log' => $mine])
            ->call('removeCharge', $victim->id);

        $this->assertDatabaseHas('log_extra_charges', ['id' => $victim->id]);
    }
}
