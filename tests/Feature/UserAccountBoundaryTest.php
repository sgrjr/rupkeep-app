<?php

namespace Tests\Feature;

use App\Livewire\UpdatePasswordForm;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-436 regression. UserPolicy never excluded super-user targets, so an
 * org admin sharing an organization with the super user could act on that
 * account; self-delete and deleting the last admin were allowed; and
 * impersonation started with a GET and left no audit row.
 */
class UserAccountBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $super;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->org = Organization::factory()->create();
        $this->super = User::factory()->superUser()->create(['organization_id' => $this->org->id]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
    }

    public function test_org_admin_cannot_act_on_a_super_user_in_their_organization(): void
    {
        foreach (['update', 'delete', 'restore', 'updateRole', 'impersonate'] as $ability) {
            $this->assertFalse($this->admin->can($ability, $this->super), "admin must not be able to {$ability} a super user");
        }

        // The super user can still act on the admin.
        foreach (['update', 'delete', 'updateRole', 'impersonate'] as $ability) {
            $this->assertTrue($this->super->can($ability, $this->admin), "super must be able to {$ability} an admin");
        }
    }

    public function test_org_admin_cannot_change_a_super_users_password(): void
    {
        $before = $this->super->password;

        Livewire::actingAs($this->admin)
            ->test(UpdatePasswordForm::class, ['profile' => $this->super])
            ->set('state.password', 'NewPassword123!')
            ->set('state.password_confirmation', 'NewPassword123!')
            ->call('updatePassword')
            ->assertForbidden();

        $this->assertSame($before, $this->super->fresh()->password);
    }

    public function test_nobody_deletes_themselves(): void
    {
        $this->assertFalse($this->admin->can('delete', $this->admin));
        $this->assertFalse($this->super->can('delete', $this->super));
        $this->assertFalse($this->super->can('forceDelete', $this->super));

        $this->actingAs($this->admin)->delete(route('my.users.destroy', $this->admin));

        $this->assertNotNull(User::find($this->admin->id));
    }

    public function test_the_last_admin_of_an_organization_cannot_be_deleted(): void
    {
        // The organization factory mints its owner as an admin of that org.
        $other = Organization::factory()->create();
        $onlyAdmin = $other->owner;
        $driver = User::factory()->create(['organization_id' => $other->id]);

        $this->assertSame(1, User::where('organization_id', $other->id)->where('organization_role', User::ROLE_ADMIN)->count());

        $this->assertFalse($this->super->can('delete', $onlyAdmin));
        $this->assertFalse($this->super->can('forceDelete', $onlyAdmin));
        $this->assertTrue($this->super->can('delete', $driver));

        // Promote a second admin and the first becomes deletable.
        $driver->forceFill(['organization_role' => User::ROLE_ADMIN])->save();

        $this->assertTrue($this->super->can('delete', $onlyAdmin));
    }

    public function test_nobody_impersonates_themselves(): void
    {
        $this->assertFalse($this->admin->can('impersonate', $this->admin));
        $this->assertFalse($this->super->can('impersonate', $this->super));
    }

    public function test_impersonation_requires_a_post_and_writes_an_audit_row(): void
    {
        $driver = User::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($this->admin)
            ->get('/impersonate/'.$driver->id)
            ->assertMethodNotAllowed();

        $this->actingAs($this->admin)
            ->post(route('impersonate', ['user' => $driver->id]))
            ->assertRedirect();

        $this->assertSame($driver->id, auth()->id());

        $event = UserEvent::where('type', UserEvent::TYPE_ACTION)->latest('id')->first();
        $this->assertNotNull($event, 'impersonation must be audited');
        $this->assertSame($this->admin->id, $event->user_id);
        $this->assertSame('impersonate', $event->context['action'] ?? null);
        $this->assertSame($driver->id, $event->context['target_user_id'] ?? null);
    }
}
