<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\CreatesMultiTenantFixtures;
use Tests\TestCase;

class InvoiceAccessControlTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMultiTenantFixtures;

    public function test_manager_cannot_update_invoice_from_other_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $managerA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_MANAGER);
        $customerB = $this->createCustomerForOrganization($orgB);
        $jobB = $this->createJobForOrganization($orgB, $customerB);
        $invoiceB = $this->createInvoiceForOrganization($orgB, $customerB, $jobB);

        $this->actingAs($managerA)
            ->put(route('my.invoices.update', $invoiceB), [
                'values' => ['total' => 999],
            ])
            ->assertForbidden();
    }

    public function test_manager_cannot_delete_summary_invoice_from_other_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $managerA = $this->createUserForOrganization($orgA, User::ROLE_EMPLOYEE_MANAGER);
        $customerB = $this->createCustomerForOrganization($orgB);
        $jobB = $this->createJobForOrganization($orgB, $customerB);
        $invoiceB = $this->createInvoiceForOrganization($orgB, $customerB, $jobB, [
            'invoice_type' => 'summary',
        ]);

        $this->actingAs($managerA)
            ->put(route('my.invoices.update', $invoiceB), [
                'delete' => 'on',
            ])
            ->assertForbidden();
    }

    public function test_customer_cannot_view_invoice_from_other_organization(): void
    {
        $orgA = $this->createOrganization('Org A');
        $orgB = $this->createOrganization('Org B');

        $customer = $this->createCustomerForOrganization($orgA);
        $customerUser = $this->createUserForOrganization($orgA, User::ROLE_CUSTOMER);
        $customerUser->customer()->associate($customer);
        $customerUser->save();

        $invoiceB = $this->createInvoiceForOrganization($orgB, $this->createCustomerForOrganization($orgB));

        $this->actingAs($customerUser)
            ->get(route('customer.invoices.show', $invoiceB))
            ->assertForbidden();
    }
}
