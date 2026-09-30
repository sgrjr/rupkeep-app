<?php

namespace Tests\Feature;

use App\Livewire\InvoiceEmailForm;
use App\Livewire\InvoicePaymentForm;
use App\Livewire\ShowPilotCarJob;
use App\Livewire\UserProfile;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Fixtures\CreatesMultiTenantFixtures;
use Tests\TestCase;

/**
 * TASK-438 regression. InvoiceEmailForm::send, InvoicePaymentForm::applyPayment
 * and UserProfile::mergeToUser had no authorization of their own, so a driver
 * or customer who could open the page could email the invoice anywhere,
 * record payments, or merge (and delete) their own account into another.
 */
class InvoiceActionAuthorizationTest extends TestCase
{
    use RefreshDatabase, CreatesMultiTenantFixtures;

    private $org;
    private User $driver;
    private User $manager;
    private $invoice;
    private $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();

        $this->org = $this->createOrganization('A');
        $this->driver = $this->createUserForOrganization($this->org, User::ROLE_EMPLOYEE_STANDARD);
        $this->manager = $this->createUserForOrganization($this->org, User::ROLE_EMPLOYEE_MANAGER);
        $customer = $this->createCustomerForOrganization($this->org);
        $this->job = $this->createJobForOrganization($this->org, $customer);
        $this->invoice = $this->createInvoiceForOrganization($this->org, $customer, $this->job);
    }

    public function test_only_users_who_may_edit_the_invoice_can_mount_the_email_and_payment_forms(): void
    {
        Livewire::actingAs($this->driver)->test(InvoiceEmailForm::class, ['invoice' => $this->invoice])->assertForbidden();
        Livewire::actingAs($this->driver)->test(InvoicePaymentForm::class, ['invoice' => $this->invoice])->assertForbidden();

        Livewire::actingAs($this->manager)->test(InvoiceEmailForm::class, ['invoice' => $this->invoice])->assertOk();
        Livewire::actingAs($this->manager)->test(InvoicePaymentForm::class, ['invoice' => $this->invoice])->assertOk();
    }

    public function test_the_actions_refuse_when_permission_is_lost_after_mount(): void
    {
        $email = Livewire::actingAs($this->manager)->test(InvoiceEmailForm::class, ['invoice' => $this->invoice]);
        $payment = Livewire::actingAs($this->manager)->test(InvoicePaymentForm::class, ['invoice' => $this->invoice]);

        $this->manager->forceFill(['organization_role' => User::ROLE_EMPLOYEE_STANDARD])->save();

        $email->set('to', 'anyone@example.test')->call('send')->assertForbidden();
        $payment->set('paymentAmount', '10')->call('applyPayment')->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_the_job_page_still_opens_for_a_driver_when_the_job_has_an_invoice(): void
    {
        Livewire::actingAs($this->driver)
            ->test(ShowPilotCarJob::class, ['job' => $this->job->id])
            ->assertOk();
    }

    public function test_merging_a_profile_requires_delete_on_it(): void
    {
        $other = $this->createUserForOrganization($this->org, User::ROLE_EMPLOYEE_STANDARD);
        $vehicle = $this->createVehicleForOrganization($this->org);
        $contact = $this->createCustomerContact($this->job->customer);
        $log = $this->createLogForOrganization($this->org, $this->job, $this->driver, $vehicle, $contact);

        // A driver editing their own profile may not merge themselves away.
        Livewire::actingAs($this->driver)
            ->test(UserProfile::class, ['user' => $this->driver->id])
            ->set('merged_user', $other->id)
            ->call('mergeToUser')
            ->assertForbidden();

        $this->assertNotNull(User::find($this->driver->id));
        $this->assertSame($this->driver->id, $log->fresh()->car_driver_id);

        // An admin may.
        $admin = $this->createUserForOrganization($this->org, User::ROLE_ADMIN);

        Livewire::actingAs($admin)
            ->test(UserProfile::class, ['user' => $this->driver->id])
            ->set('merged_user', $other->id)
            ->call('mergeToUser');

        $this->assertNull(User::find($this->driver->id));
        $this->assertSame($other->id, UserLog::find($log->id)->car_driver_id);
    }
}
