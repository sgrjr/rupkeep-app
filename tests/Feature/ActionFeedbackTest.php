<?php

namespace Tests\Feature;

use App\Livewire\InvoiceEmailForm;
use App\Livewire\OnboardingWizard;
use App\Livewire\TaskThread;
use App\Mail\UserNotification;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\LivewireFlashToasts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Fixtures\FlashingComponent;
use Tests\TestCase;

/**
 * TASK-460. The layout's toast stack read the session once, at full page
 * load, so a flash raised inside a Livewire action was never shown -- and
 * then leaked onto whatever page came next. Several controllers redirected
 * with no message at all, one flashed a channel the layout did not read, and
 * two components reported success whatever had actually happened.
 */
class ActionFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
    }

    // -----------------------------------------------------------------
    // The bridge: a flash inside a Livewire request becomes a toast
    // -----------------------------------------------------------------

    public function test_a_flash_raised_in_a_livewire_action_is_dispatched_as_a_toast(): void
    {
        Livewire::actingAs($this->manager)
            ->test(FlashingComponent::class)
            ->call('flashOnly')
            ->assertDispatched('notify', type: 'success', message: 'Saved the thing.')
            // An in-component @if(session('success')) block still sees it.
            ->assertSee('Saved the thing.');
    }

    public function test_the_flash_does_not_leak_onto_the_next_page(): void
    {
        Livewire::actingAs($this->manager)
            ->test(FlashingComponent::class)
            ->call('flashOnly');

        // The bridge demotes the flash to this request only. The test harness
        // does not run the session middleware between requests, so age the
        // flash data here exactly as StartSession does when a request ends.
        $this->assertNotContains('success', session()->get('_flash.new', []));
        session()->ageFlashData();

        // Before the bridge this page showed "Saved the thing." in its toast
        // stack, seconds after the fact and on an unrelated screen.
        $this->actingAs($this->manager)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertDontSee('Saved the thing.');
    }

    public function test_a_redirecting_action_keeps_its_flash_for_the_landing_page(): void
    {
        Livewire::actingAs($this->manager)
            ->test(FlashingComponent::class)
            ->call('flashAndRedirect')
            ->assertRedirect(route('customers.index'))
            ->assertNotDispatched('notify');

        session()->ageFlashData();

        $this->actingAs($this->manager)
            ->get(route('customers.index'))
            ->assertSee('Off we go.');
    }

    public function test_a_structured_message_flash_becomes_one_toast_per_entry(): void
    {
        Livewire::actingAs($this->manager)
            ->test(FlashingComponent::class)
            ->call('flashStructured')
            ->assertDispatched('notify', type: 'warning', message: 'Careful now.')
            ->assertDispatched('notify', type: 'success', message: 'But it worked.');
    }

    public function test_toast_rows_mirror_the_layouts_reading_of_each_channel(): void
    {
        $this->assertSame([['type' => 'success', 'message' => 'Done.']], LivewireFlashToasts::toasts('success', 'Done.'));
        $this->assertSame([['type' => 'info', 'message' => 'Note.']], LivewireFlashToasts::toasts('message', 'Note.'));
        $this->assertSame([], LivewireFlashToasts::toasts('error', '   '));
        $this->assertSame(
            [['type' => 'error', 'message' => 'Bad.'], ['type' => 'info', 'message' => 'Odd.']],
            LivewireFlashToasts::toasts('message', ['error' => 'Bad.', 7 => 'Odd.', 'success' => ''])
        );
    }

    public function test_the_toast_stack_is_on_every_page_listening_for_notify(): void
    {
        // With nothing to show the stack used to be omitted entirely, so there
        // was nobody to hear the first action's event.
        $this->actingAs($this->manager)
            ->get(route('customers.index'))
            ->assertSee('x-on:notify.window', false)
            ->assertSee('data-toast-stack', false);
    }

    // -----------------------------------------------------------------
    // The layout reads what controllers flash
    // -----------------------------------------------------------------

    public function test_the_info_channel_reaches_the_layout(): void
    {
        // MyInvoicesController flashes 'info' ("already sent", "not past due").
        $this->actingAs($this->manager)
            ->withSession(['info' => 'Invoice was already sent on Monday.'])
            ->get(route('customers.index'))
            ->assertSee('Invoice was already sent on Monday.');
    }

    public function test_a_validation_failure_on_a_form_without_error_markup_is_named(): void
    {
        // customers/create renders neither @error nor $errors: the form came
        // back looking untouched.
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->from(route('customers.create'))
            ->post(route('customers.store'), ['name' => ''])
            ->assertRedirect(route('customers.create'));

        $this->actingAs($admin)
            ->get(route('customers.create'))
            ->assertSee('The name field is required.');
    }

    // -----------------------------------------------------------------
    // Controllers that redirected in silence
    // -----------------------------------------------------------------

    public function test_vehicle_archive_restore_and_delete_report_back_on_the_list(): void
    {
        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Escort 7']);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)->delete(route('my.vehicles.destroy', $vehicle))
            ->assertRedirect(route('my.vehicles.index'));
        $this->actingAs($admin)->get(route('my.vehicles.index'))->assertSee('Escort 7 archived.');
        $this->flushSession();

        $this->actingAs($admin)->put(route('my.vehicles.restore', $vehicle->id))
            ->assertRedirect(route('my.vehicles.index'));
        $this->actingAs($admin)->get(route('my.vehicles.index'))->assertSee('Escort 7 restored.');
        $this->flushSession();

        $vehicle->delete();
        $this->actingAs($admin)->delete(route('my.vehicles.force-destroy', $vehicle->id))
            ->assertRedirect(route('my.vehicles.index'));
        $this->actingAs($admin)->get(route('my.vehicles.index'))->assertSee('Escort 7 permanently deleted.');
    }

    public function test_customer_create_update_and_delete_report_back_on_the_list(): void
    {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)->post(route('customers.store'), ['name' => 'Granite State Haulers'])
            ->assertRedirect(route('customers.index'));
        $this->actingAs($admin)->get(route('customers.index'))->assertSee('Granite State Haulers created.');
        $this->flushSession();

        $customer = Customer::where('name', 'Granite State Haulers')->firstOrFail();

        $this->actingAs($admin)->put(route('customers.update', $customer->id), ['name' => 'Granite State Hauling'])
            ->assertRedirect(route('customers.index'));
        $this->actingAs($admin)->get(route('customers.index'))->assertSee('Granite State Hauling updated.');
        $this->flushSession();

        // The list's delete button posts to the other customer controller.
        \Illuminate\Support\Facades\Storage::fake(\App\Services\CustomerArchive::DISK);
        $this->actingAs($admin)->delete(route('my.customers.destroy', $customer->id), ['confirmed' => 1])
            ->assertRedirect(route('customers.index'));
        $this->actingAs($admin)->get(route('customers.index'))->assertSee('Granite State Hauling deleted.');
    }

    // -----------------------------------------------------------------
    // Components that reported success regardless
    // -----------------------------------------------------------------

    public function test_a_customer_update_with_nobody_to_send_to_is_not_marked_sent(): void
    {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $task = Task::create([
            'code' => Task::nextCode(),
            'title' => 'Demo',
            'type' => 'feature',
            'priority' => 'medium',
            'status' => 'open',
            'organization_id' => $this->organization->id,
            'submitter_user_id' => null,
        ]);

        $comment = $task->comments()->create([
            'user_id' => $admin->id,
            'body' => 'Update for the customer',
            'is_internal' => false,
            'event_type' => TaskComment::EVENT_COMMENT,
        ]);

        Livewire::actingAs($admin)
            ->test(TaskThread::class, ['task' => $task])
            ->call('sendCustomerUpdate', $comment->id)
            ->assertDispatched('notify', type: 'error')
            ->assertNotDispatched('commentAdded');

        $this->assertFalse($comment->refresh()->sent_to_customer, 'nothing was emailed, so it must not say it was');
    }

    public function test_welcome_emails_report_what_was_actually_sent(): void
    {
        $super = User::factory()->superUser()->create(['organization_id' => $this->organization->id]);
        $users = User::factory()->count(2)->standard()->create(['organization_id' => $this->organization->id]);

        Mail::fake();

        Livewire::actingAs($super)
            ->test(OnboardingWizard::class)
            ->set('organizationId', $this->organization->id)
            ->set('selected_users_for_email', $users->pluck('id')->all())
            ->call('sendWelcomeEmails')
            ->assertSet('email_sent', true)
            ->assertDispatched('notify', type: 'info', message: 'Welcome emails sent to 2 users.');

        Mail::assertSent(UserNotification::class, 2);
    }

    public function test_welcome_emails_that_all_fail_are_not_reported_as_sent(): void
    {
        $super = User::factory()->superUser()->create(['organization_id' => $this->organization->id]);
        $users = User::factory()->count(2)->standard()->create(['organization_id' => $this->organization->id]);

        // An undefined mailer throws on every send; the real transports are
        // never touched.
        config(['mail.default' => 'no-such-mailer']);

        Livewire::actingAs($super)
            ->test(OnboardingWizard::class)
            ->set('organizationId', $this->organization->id)
            ->set('selected_users_for_email', $users->pluck('id')->all())
            ->call('sendWelcomeEmails')
            ->assertSet('email_sent', false)
            ->assertDispatched('notify', type: 'error')
            ->assertNotDispatched('notify', type: 'info');
    }

    // -----------------------------------------------------------------
    // The screens the task lists, through the bridge
    // -----------------------------------------------------------------

    public function test_an_invoice_email_failure_is_shown_to_the_sender(): void
    {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $job = \App\Models\PilotCarJob::create([
            'job_no' => 'JOB-460',
            'customer_id' => $customer->id,
            'organization_id' => $this->organization->id,
            'pickup_address' => 'A',
            'delivery_address' => 'B',
            'rate_code' => 'flat_rate',
            'rate_value' => '100.00',
        ]);
        $invoice = \App\Models\Invoice::factory()->draft()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'pilot_car_job_id' => $job->id,
        ]);

        config(['mail.default' => 'no-such-mailer']);

        Livewire::actingAs($this->manager)
            ->test(InvoiceEmailForm::class, ['invoice' => $invoice])
            ->set('to', 'billing@example.test')
            ->call('send')
            ->assertDispatched('notify', type: 'error');

        $this->assertNull($invoice->fresh()->sent_at, 'a failed email must not mark the invoice sent');
    }
}
