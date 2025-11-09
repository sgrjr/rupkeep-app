<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_view_invoices_index(): void
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->asCustomer($customer)->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'organization_id' => $customer->organization_id,
        ]);

        $this->actingAs($user)
            ->get(route('customer.invoices.index'))
            ->assertOk()
            ->assertSee($invoice->invoice_number);
    }

    public function test_guest_is_prompted_for_authentication(): void
    {
        $this->get(route('customer.invoices.index'))
            ->assertStatus(200)
            ->assertSeeText(__('Sign in to view your invoices'))
            ->assertSee(route('login-code.create'), false)
            ->assertSee(route('login'), false);
    }

    public function test_customer_cannot_view_other_customer_invoice(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $user = User::factory()->asCustomer($customer)->create();
        $otherInvoice = Invoice::factory()->create([
            'customer_id' => $otherCustomer->id,
            'organization_id' => $otherCustomer->organization_id,
        ]);

        $this->actingAs($user)
            ->get(route('customer.invoices.show', $otherInvoice))
            ->assertStatus(403)
            ->assertSeeText(__('We couldn\'t open that invoice'))
            ->assertSee(route('customer.invoices.index'), false);
    }

    public function test_employee_cannot_access_customer_portal(): void
    {
        $user = User::factory()->manager()->create();

        $this->actingAs($user)
            ->get(route('customer.invoices.index'))
            ->assertStatus(403)
            ->assertSeeText(__('Customer portal access required'))
            ->assertSee(route('login-code.create'), false);
    }
}

