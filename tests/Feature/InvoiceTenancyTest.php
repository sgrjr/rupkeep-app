<?php

namespace Tests\Feature;

use App\Livewire\ShowPilotCarJob;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fixtures\CreatesMultiTenantFixtures;
use Tests\TestCase;

/**
 * TASK-430 / TASK-431 regression. InvoicePolicy::update and ::delete
 * returned true for any admin with no organization comparison, and the
 * invoice controller looked ids up unscoped, so an org-A admin could edit,
 * delete and regroup org B's invoices, and anyone could invoice anyone's jobs
 * (Poc2Test proved both).
 */
class InvoiceTenancyTest extends TestCase
{
    use RefreshDatabase, CreatesMultiTenantFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /** @return array{0: User, 1: Invoice, 2: \App\Models\PilotCarJob} org-A admin and an org-B invoice */
    private function crossTenant(): array
    {
        $a = $this->createOrganization('A');
        $b = $this->createOrganization('B');
        $adminA = $this->createUserForOrganization($a, User::ROLE_ADMIN);
        $customerB = $this->createCustomerForOrganization($b);
        $jobB = $this->createJobForOrganization($b, $customerB);
        $invoiceB = $this->createInvoiceForOrganization($b, $customerB, $jobB);

        return [$adminA, $invoiceB, $jobB];
    }

    public function test_policy_requires_the_same_organization(): void
    {
        [$adminA, $invoiceB] = $this->crossTenant();

        $this->assertFalse($adminA->can('view', $invoiceB));
        $this->assertFalse($adminA->can('update', $invoiceB));
        $this->assertFalse($adminA->can('delete', $invoiceB));

        $ownInvoice = $this->createInvoiceForOrganization(
            $adminA->organization,
            $this->createCustomerForOrganization($adminA->organization)
        );
        $this->assertTrue($adminA->can('update', $ownInvoice));
        $this->assertTrue($adminA->can('delete', $ownInvoice));
    }

    public function test_customer_view_needs_the_same_organization_not_just_the_customer_id(): void
    {
        $a = $this->createOrganization('A');
        $b = $this->createOrganization('B');
        $customerA = $this->createCustomerForOrganization($a);
        $portalUser = $this->createUserForOrganization($a, User::ROLE_CUSTOMER);
        $portalUser->forceFill(['customer_id' => $customerA->id])->save();

        $own = $this->createInvoiceForOrganization($a, $customerA);
        $foreign = $this->createInvoiceForOrganization($b, $this->createCustomerForOrganization($b), null, [
            'customer_id' => $customerA->id, // same customer id, other organization
        ]);

        $this->assertTrue($portalUser->can('view', $own));
        $this->assertFalse($portalUser->can('view', $foreign));
    }

    public function test_org_admin_cannot_edit_delete_or_act_on_another_orgs_invoice(): void
    {
        [$adminA, $invoiceB] = $this->crossTenant();

        $this->actingAs($adminA)->get(route('my.invoices.edit', $invoiceB))->assertForbidden();
        $this->actingAs($adminA)->put(route('my.invoices.update', $invoiceB), ['values' => []])->assertForbidden();
        $this->actingAs($adminA)->put(route('my.invoices.update', $invoiceB), ['delete' => 1, 'delete_mode' => 'delete_children'])->assertForbidden();
        $this->actingAs($adminA)->post(route('my.invoices.apply-late-fees', $invoiceB))->assertForbidden();
        $this->actingAs($adminA)->post(route('my.invoices.toggle-marked-for-attention', $invoiceB))->assertForbidden();
        $this->actingAs($adminA)->post(route('my.invoices.regenerate-summary', $invoiceB))->assertForbidden();

        $this->assertNotNull(Invoice::find($invoiceB->id), 'invoice must survive');
    }

    public function test_manager_can_edit_but_not_delete_their_own_orgs_invoice(): void
    {
        $a = $this->createOrganization('A');
        $manager = $this->createUserForOrganization($a, User::ROLE_EMPLOYEE_MANAGER);
        $invoice = $this->createInvoiceForOrganization($a, $this->createCustomerForOrganization($a));

        $this->assertTrue($manager->can('update', $invoice));

        $this->actingAs($manager)
            ->put(route('my.invoices.update', $invoice), ['delete' => 1])
            ->assertForbidden();

        $this->assertNotNull(Invoice::find($invoice->id));
    }

    public function test_summary_grouping_ignores_other_orgs_invoice_ids(): void
    {
        [$adminA, $invoiceB] = $this->crossTenant();
        $customerB = $invoiceB->customer;
        $secondB = $this->createInvoiceForOrganization($invoiceB->organization, $customerB);

        $this->actingAs($adminA)
            ->post(route('my.invoices.create-summary'), ['invoice_ids' => [$invoiceB->id, $secondB->id]])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($invoiceB->fresh()->parent_invoice_id);
        $this->assertNull($secondB->fresh()->parent_invoice_id);
        $this->assertSame(0, Invoice::where('invoice_type', 'summary')->count());
    }

    public function test_invoice_creation_is_authorized_and_org_scoped(): void
    {
        [$adminA, $invoiceB, $jobB] = $this->crossTenant();
        $a = $adminA->organization;

        $customer = $this->createUserForOrganization($a, User::ROLE_CUSTOMER);
        $driver = $this->createUserForOrganization($a, User::ROLE_EMPLOYEE_STANDARD);
        $uninvoicedB = $this->createJobForOrganization($jobB->organization, $jobB->customer);

        $before = Invoice::count();

        $this->actingAs($customer)->post(route('my.invoices.store'), ['invoice_this' => [$uninvoicedB->id]])->assertForbidden();
        $this->actingAs($driver)->post(route('my.invoices.store'), ['invoice_this' => [$uninvoicedB->id]])->assertForbidden();

        // An admin of org A posting org B's job id gets "no matching jobs", not an invoice.
        $this->actingAs($adminA)
            ->post(route('my.invoices.store'), ['invoice_this' => [$uninvoicedB->id]])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame($before, Invoice::count());
    }

    public function test_job_page_delete_only_reaches_this_jobs_invoices(): void
    {
        [$adminA, $invoiceB] = $this->crossTenant();
        $a = $adminA->organization;
        $customerA = $this->createCustomerForOrganization($a);
        $jobA = $this->createJobForOrganization($a, $customerA);
        $otherJobA = $this->createJobForOrganization($a, $customerA);
        $otherInvoiceA = $this->createInvoiceForOrganization($a, $customerA, $otherJobA);

        // Another organization's invoice: forbidden by the policy.
        Livewire::actingAs($adminA)
            ->test(ShowPilotCarJob::class, ['job' => $jobA->id])
            ->call('deleteInvoice', $invoiceB->id)
            ->assertForbidden();

        // Own organization, but a different job: not found from this page.
        Livewire::actingAs($adminA)
            ->test(ShowPilotCarJob::class, ['job' => $jobA->id])
            ->call('deleteInvoice', $otherInvoiceA->id)
            ->assertNotFound();

        $this->assertNotNull(Invoice::find($invoiceB->id));
        $this->assertNotNull(Invoice::find($otherInvoiceA->id));
    }
}
