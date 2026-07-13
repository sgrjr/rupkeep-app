<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MainContactUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private function contact(Customer $customer, array $attrs = []): CustomerContact
    {
        return CustomerContact::create(array_merge([
            'customer_id' => $customer->id,
            'organization_id' => $customer->organization_id,
            'name' => 'Contact',
            'phone' => '555-0000',
        ], $attrs));
    }

    public function test_promoting_a_second_main_contact_demotes_the_first(): void
    {
        $org = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $org->id]);

        $first = $this->contact($customer, ['name' => 'First', 'is_main_contact' => true]);
        $second = $this->contact($customer, ['name' => 'Second', 'is_main_contact' => true]);

        $this->assertFalse($first->fresh()->is_main_contact, 'The first main contact must be demoted.');
        $this->assertTrue($second->fresh()->is_main_contact);
        $this->assertSame(1, CustomerContact::where('customer_id', $customer->id)
            ->where('is_main_contact', true)->count());
    }

    public function test_billing_contact_is_also_unique_per_customer(): void
    {
        $org = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $org->id]);

        $first = $this->contact($customer, ['is_billing_contact' => true]);
        $second = $this->contact($customer, ['is_billing_contact' => true]);

        $this->assertFalse($first->fresh()->is_billing_contact);
        $this->assertSame(1, CustomerContact::where('customer_id', $customer->id)
            ->where('is_billing_contact', true)->count());
    }

    public function test_main_contacts_of_different_customers_are_independent(): void
    {
        $org = Organization::factory()->create();
        $customerA = Customer::factory()->create(['organization_id' => $org->id]);
        $customerB = Customer::factory()->create(['organization_id' => $org->id]);

        $a = $this->contact($customerA, ['is_main_contact' => true]);
        $b = $this->contact($customerB, ['is_main_contact' => true]);

        // Promoting B's contact must not touch A's.
        $this->assertTrue($a->fresh()->is_main_contact);
        $this->assertTrue($b->fresh()->is_main_contact);
    }
}
