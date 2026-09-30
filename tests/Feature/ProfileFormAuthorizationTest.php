<?php

namespace Tests\Feature;

use App\Livewire\UpdateProfileInformationForm;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-428 regression. The profile form used to do
 * User::find($state['id'])->update($state) with password, organization_id and
 * organization_role all fillable, so any signed-in user could rewrite any
 * account (proved by tests/Audit/2026-09-30-authz-poc/PocTest.php). Now it
 * acts only on the mounted user, authorizes every action, and writes only
 * whitelisted fields.
 */
class ProfileFormAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->orgA = Organization::factory()->create(['name' => 'A']);
        $this->orgB = Organization::factory()->create(['name' => 'B']);
    }

    private function user(Organization $organization, string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'organization_id' => $organization->id,
            'organization_role' => $role,
        ], $attributes));
    }

    public function test_state_id_is_ignored_so_another_account_cannot_be_rewritten(): void
    {
        $customer = $this->user($this->orgA, User::ROLE_CUSTOMER);
        $victim = $this->user($this->orgB, User::ROLE_ADMIN, [
            'email' => 'victim@example.test',
            'password' => Hash::make('victim-password'),
        ]);

        Livewire::actingAs($customer)
            ->test(UpdateProfileInformationForm::class)
            ->set('state.id', $victim->id)
            ->set('state.email', 'evil@example.test')
            ->set('state.password', 'Pwned12345!')
            ->call('updateProfileInformation');

        $victim->refresh();
        $this->assertSame('victim@example.test', $victim->email);
        $this->assertTrue(Hash::check('victim-password', $victim->password));

        // The write landed on the customer's own row instead, and only the
        // whitelisted part of it.
        $customer->refresh();
        $this->assertSame('evil@example.test', $customer->email);
        $this->assertTrue(Hash::check('password', $customer->password), 'password must never be accepted from the form');
    }

    public function test_a_user_cannot_promote_or_move_themselves(): void
    {
        $customer = $this->user($this->orgA, User::ROLE_CUSTOMER);

        Livewire::actingAs($customer)
            ->test(UpdateProfileInformationForm::class)
            ->set('state.organization_role', User::ROLE_ADMIN)
            ->set('state.organization_id', $this->orgB->id)
            ->set('state.customer_id', 999)
            ->set('state.is_super', true)
            ->call('updateProfileInformation')
            ->assertHasNoErrors()
            ->assertSet('state.organization_role', User::ROLE_CUSTOMER);

        $customer->refresh();
        $this->assertSame(User::ROLE_CUSTOMER, $customer->organization_role);
        $this->assertSame($this->orgA->id, $customer->organization_id);
        $this->assertNull($customer->customer_id);
        $this->assertFalse($customer->isSuper());
    }

    public function test_org_admin_can_change_a_same_org_users_role(): void
    {
        $admin = $this->user($this->orgA, User::ROLE_ADMIN);
        $driver = $this->user($this->orgA, User::ROLE_EMPLOYEE_STANDARD);

        Livewire::actingAs($admin)
            ->test(UpdateProfileInformationForm::class, ['profile' => $driver])
            ->set('state.organization_role', User::ROLE_EMPLOYEE_MANAGER)
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertSame(User::ROLE_EMPLOYEE_MANAGER, $driver->fresh()->organization_role);
    }

    public function test_role_must_be_a_known_role(): void
    {
        $admin = $this->user($this->orgA, User::ROLE_ADMIN);
        $driver = $this->user($this->orgA, User::ROLE_EMPLOYEE_STANDARD);

        Livewire::actingAs($admin)
            ->test(UpdateProfileInformationForm::class, ['profile' => $driver])
            ->set('state.organization_role', 'overlord')
            ->call('updateProfileInformation')
            ->assertHasErrors(['state.organization_role']);

        $this->assertSame(User::ROLE_EMPLOYEE_STANDARD, $driver->fresh()->organization_role);
    }

    public function test_org_admin_cannot_open_another_orgs_user(): void
    {
        $admin = $this->user($this->orgA, User::ROLE_ADMIN);
        $outsider = $this->user($this->orgB, User::ROLE_EMPLOYEE_STANDARD);

        Livewire::actingAs($admin)
            ->test(UpdateProfileInformationForm::class, ['profile' => $outsider])
            ->assertForbidden();
    }

    public function test_every_action_is_authorized_not_just_mount(): void
    {
        $admin = $this->user($this->orgA, User::ROLE_ADMIN);
        $driver = $this->user($this->orgA, User::ROLE_EMPLOYEE_STANDARD);

        // Three components mounted while the admin still is one. (One per
        // action: a 403 response leaves a test instance with no snapshot.)
        $mount = fn () => Livewire::actingAs($admin)
            ->test(UpdateProfileInformationForm::class, ['profile' => $driver]);
        [$save, $photo, $verify] = [$mount(), $mount(), $mount()];

        // The admin loses their role between mount and save: every action on
        // the already-mounted components must now refuse.
        $admin->forceFill(['organization_role' => User::ROLE_EMPLOYEE_STANDARD])->save();

        $save->set('state.name', 'Renamed')->call('updateProfileInformation')->assertForbidden();
        $photo->call('deleteProfilePhoto')->assertForbidden();
        $verify->call('sendEmailVerification')->assertForbidden();

        $this->assertNotSame('Renamed', $driver->fresh()->name);
    }

    public function test_notification_address_and_theme_are_validated(): void
    {
        $driver = $this->user($this->orgA, User::ROLE_EMPLOYEE_STANDARD);

        Livewire::actingAs($driver)
            ->test(UpdateProfileInformationForm::class)
            ->set('state.notification_address', 'not an address')
            ->call('updateProfileInformation')
            ->assertHasErrors(['notification_address']);

        Livewire::actingAs($driver)
            ->test(UpdateProfileInformationForm::class)
            ->set('state.theme', 'neon-theme')
            ->call('updateProfileInformation')
            ->assertHasErrors(['theme']);

        Livewire::actingAs($driver)
            ->test(UpdateProfileInformationForm::class)
            ->set('state.notification_address', '2075551234@mms.uscc.net')
            ->set('state.theme', 'dark-theme')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $driver->refresh();
        $this->assertSame('2075551234@mms.uscc.net', $driver->notification_address);
        $this->assertSame('dark-theme', $driver->theme);
    }

    public function test_email_must_be_unique(): void
    {
        $driver = $this->user($this->orgA, User::ROLE_EMPLOYEE_STANDARD);
        $this->user($this->orgA, User::ROLE_EMPLOYEE_STANDARD, ['email' => 'taken@example.test']);

        Livewire::actingAs($driver)
            ->test(UpdateProfileInformationForm::class)
            ->set('state.email', 'taken@example.test')
            ->call('updateProfileInformation')
            ->assertHasErrors(['email']);
    }
}
