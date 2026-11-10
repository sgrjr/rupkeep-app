<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-507 (drive 003). A customer-portal login landed on the staff
 * dashboard: the staff tagline, the company's staff count with a link to
 * /my/users, and none of the customer's own invoices.
 */
class CustomerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Customer $customer;

    private User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->organization = Organization::factory()->create(['name' => 'Casco Bay Pilot Car']);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'JLS Pilot Car']);
        $this->customerUser = User::factory()->asCustomer($this->customer)->create();

        // Staff the customer must never be told about.
        User::factory()->manager()->count(3)->create(['organization_id' => $this->organization->id]);
    }

    private function invoice(array $overrides): Invoice
    {
        return Invoice::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ], $overrides));
    }

    public function test_a_customer_sees_their_own_sent_invoices_and_balance(): void
    {
        $open = $this->invoice(['status' => Invoice::STATUS_SENT, 'values' => ['total' => 500]]);
        $paid = $this->invoice(['status' => Invoice::STATUS_PAID, 'paid_in_full' => true, 'values' => ['total' => 1112.50]]);
        $draft = $this->invoice(['status' => Invoice::STATUS_DRAFT, 'values' => ['total' => 999]]);

        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $otherCustomer->id,
            'status' => Invoice::STATUS_SENT,
            'values' => ['total' => 777],
        ]);

        Livewire::actingAs($this->customerUser)
            ->test(Dashboard::class)
            ->assertViewIs('livewire.customer-dashboard')
            ->assertViewHas('invoiceCount', 2)
            ->assertViewHas('openCount', 1)
            ->assertViewHas('balanceDue', 500.0)
            ->assertSee($open->invoice_number)
            ->assertSee($paid->invoice_number)
            ->assertDontSee($draft->invoice_number)
            ->assertDontSee($foreign->invoice_number)
            ->assertSee(route('customer.invoices.show', $open), false);
    }

    public function test_a_customer_sees_no_staff_figures_or_staff_links(): void
    {
        $html = Livewire::actingAs($this->customerUser)
            ->test(Dashboard::class)
            ->assertDontSee('Stay in control')
            ->assertDontSee(route('my.users.index'), false)
            ->assertSee('Casco Bay Pilot Car')
            ->html();

        $this->assertStringNotContainsString('/my/', $html, 'A customer dashboard must not link into the staff area');
    }

    public function test_staff_still_get_the_staff_dashboard(): void
    {
        $manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);

        Livewire::actingAs($manager)
            ->test(Dashboard::class)
            ->assertViewIs('livewire.dashboard');
    }
}
