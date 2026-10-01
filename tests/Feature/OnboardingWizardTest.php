<?php

namespace Tests\Feature;

use App\Livewire\OnboardingWizard;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-473. Next from step 1 always inserted an organization, so Back then
 * Next made a second one and orphaned the first's users and vehicles; the
 * wizard could be completed for an organization with nobody to run it.
 */
class OnboardingWizardTest extends TestCase
{
    use RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->super = User::factory()->superUser()->create(['organization_id' => Organization::factory()->create()->id]);
    }

    private function wizardAtStepTwo()
    {
        return Livewire::actingAs($this->super)
            ->test(OnboardingWizard::class)
            ->set('org_name', 'Northwind Escorts')
            ->set('org_primary_contact', 'Pat Northwind')
            ->call('nextStep')
            ->assertSet('currentStep', 2);
    }

    public function test_back_then_next_updates_the_organization_instead_of_creating_a_second_one(): void
    {
        $before = Organization::count();
        $wizard = $this->wizardAtStepTwo();

        $this->assertSame($before + 1, Organization::count());
        $organizationId = $wizard->get('organizationId');

        $wizard->call('previousStep')
            ->assertSet('currentStep', 1)
            ->set('org_name', 'Northwind Escorts LLC')
            ->set('org_telephone', '555-0100')
            ->call('nextStep')
            ->assertSet('currentStep', 2)
            ->assertSet('organizationId', $organizationId);

        $this->assertSame($before + 1, Organization::count(), 'no second organization');
        $this->assertSame('Northwind Escorts LLC', Organization::find($organizationId)->name);
        $this->assertSame('555-0100', Organization::find($organizationId)->telephone);
    }

    public function test_users_added_before_going_back_still_belong_to_the_same_organization(): void
    {
        $wizard = $this->wizardAtStepTwo()->call('nextStep')->assertSet('currentStep', 3);
        $organizationId = $wizard->get('organizationId');

        $wizard->set('new_user_name', 'Mary Manager')
            ->set('new_user_email', 'mary@northwind.example')
            ->set('new_user_password', 'secret-123')
            ->set('new_user_password_confirmation', 'secret-123')
            ->set('new_user_role', User::ROLE_ADMIN)
            ->call('addUser')
            ->assertHasNoErrors();

        $wizard->call('previousStep')->call('previousStep')->assertSet('currentStep', 1)
            ->call('nextStep');

        $this->assertSame($organizationId, User::where('email', 'mary@northwind.example')->value('organization_id'));
    }

    public function test_completing_without_an_admin_is_refused_and_sends_you_to_the_users_step(): void
    {
        $wizard = $this->wizardAtStepTwo();

        foreach (range(1, 4) as $_) {
            $wizard->call('nextStep');
        }
        $wizard->assertSet('currentStep', 6);

        $wizard->call('complete')
            ->assertNoRedirect()
            ->assertSet('currentStep', 3)
            ->assertSet('new_user_role', User::ROLE_ADMIN)
            ->assertSee('at least one Admin');
    }

    public function test_the_first_user_defaults_to_admin_and_completion_works_once_one_exists(): void
    {
        $wizard = $this->wizardAtStepTwo()->call('nextStep')->assertSet('currentStep', 3)
            ->assertSet('new_user_role', User::ROLE_ADMIN);

        $wizard->set('new_user_name', 'Mary Manager')
            ->set('new_user_email', 'mary@northwind.example')
            ->set('new_user_password', 'secret-123')
            ->set('new_user_password_confirmation', 'secret-123')
            ->call('addUser')
            ->assertHasNoErrors();

        foreach (range(1, 3) as $_) {
            $wizard->call('nextStep');
        }

        $wizard->assertSet('currentStep', 6)
            ->call('complete')
            ->assertRedirect(route('organizations.show', ['organization' => $wizard->get('organizationId')]));
    }

    public function test_completing_before_creating_an_organization_is_refused(): void
    {
        Livewire::actingAs($this->super)
            ->test(OnboardingWizard::class)
            ->set('currentStep', 6)
            ->call('complete')
            ->assertNoRedirect()
            ->assertSet('currentStep', 1);
    }
}
