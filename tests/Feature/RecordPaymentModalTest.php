<?php

namespace Tests\Feature;

use App\Livewire\InvoicePaymentForm;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-455. The Record Payment modal kept `style="display: none;" x-cloak`
 * after the Alpine x-show that cleared them was removed (d11393e1, TASK-423),
 * so @if($showModal) rendered a modal nobody could see and no payment could
 * be recorded.
 */
class RecordPaymentModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_the_modal_renders_it_visibly(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);
        $invoice = Invoice::factory()->create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'values' => ['title' => 'INVOICE', 'total' => 500],
        ]);

        $component = Livewire::actingAs($manager)
            ->test(InvoicePaymentForm::class, ['invoice' => $invoice])
            ->assertDontSee('applyPayment', false);

        $component->call('openModal')
            ->assertSet('showModal', true)
            ->assertSee('applyPayment', false)
            ->assertSee('Total Due');

        $html = $component->html();

        $this->assertMatchesRegularExpression('/<div data-test="record-payment-modal" class="[^"]*"\s*>/', $html, 'no inline style on the container');
        $this->assertStringNotContainsString('display: none', $html);
        $this->assertStringNotContainsString('x-cloak', $html);

        $component->call('closeModal')
            ->assertSet('showModal', false)
            ->assertDontSee('applyPayment', false);
    }
}
