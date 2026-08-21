<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-366: "super user" is an application-wide flag on a USER, not a property
 * inferred from their organization's name.
 *
 * The old arrangement conflated two ideas — platform-wide access and
 * organization-scoped admin rights — and meant membership of one specifically
 * named organization decided cross-tenant access.
 */
class SuperUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_user_is_identified_by_the_flag(): void
    {
        $user = User::factory()->superUser()->create([
            'organization_id' => Organization::factory()->create()->id,
        ]);

        $this->assertTrue($user->isSuper());
    }

    public function test_an_org_admin_is_not_automatically_a_super_user(): void
    {
        // The whole point of the split: running your own organization does not
        // grant access to everyone else's.
        $admin = User::factory()->admin()->create([
            'organization_id' => Organization::factory()->create()->id,
        ]);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isSuper());
    }

    public function test_organization_name_no_longer_confers_super_access(): void
    {
        // Previously any member of an org named 'Reynolds Upkeep' was treated as
        // super — a driver or customer account included.
        $org = Organization::factory()->create(['name' => 'Reynolds Upkeep']);

        $driver = User::factory()->standard()->create(['organization_id' => $org->id]);
        $customer = User::factory()->customerRole()->create(['organization_id' => $org->id]);
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->assertFalse($driver->isSuper());
        $this->assertFalse($customer->isSuper());
        $this->assertFalse($admin->isSuper(), 'even an admin needs the flag explicitly');
    }

    public function test_renaming_the_organization_does_not_revoke_super_access(): void
    {
        $org = Organization::factory()->create(['name' => 'Reynolds Upkeep']);
        $user = User::factory()->superUser()->create(['organization_id' => $org->id]);

        $org->update(['name' => 'Something Else Entirely']);

        $this->assertTrue($user->fresh()->isSuper());
    }

    public function test_super_cannot_be_granted_through_mass_assignment(): void
    {
        // is_super is a privilege flag and is deliberately absent from
        // User::$fillable, so no request payload can ever set it.
        $user = User::create([
            'name' => 'Sneaky',
            'email' => 'sneaky@example.test',
            'password' => 'secret-password',
            'organization_role' => User::ROLE_CUSTOMER,
            'is_super' => true,
        ]);

        $this->assertFalse($user->fresh()->isSuper());
    }

    public function test_super_cannot_be_granted_by_updating_a_user(): void
    {
        $user = User::factory()->standard()->create([
            'organization_id' => Organization::factory()->create()->id,
        ]);

        $user->update(['is_super' => true]);

        $this->assertFalse($user->fresh()->isSuper());
    }

    public function test_the_grant_command_promotes_a_user(): void
    {
        $user = User::factory()->admin()->create([
            'organization_id' => Organization::factory()->create()->id,
            'email' => 'promote@example.test',
        ]);

        $this->artisan('super:grant promote@example.test')->assertSuccessful();

        $this->assertTrue($user->fresh()->isSuper());
    }

    public function test_the_grant_command_can_revoke(): void
    {
        $org = Organization::factory()->create();
        User::factory()->superUser()->create(['organization_id' => $org->id, 'email' => 'keep@example.test']);
        $drop = User::factory()->superUser()->create(['organization_id' => $org->id, 'email' => 'drop@example.test']);

        $this->artisan('super:grant drop@example.test --revoke')->assertSuccessful();

        $this->assertFalse($drop->fresh()->isSuper());
    }

    public function test_the_grant_command_refuses_to_revoke_the_last_super_user(): void
    {
        // Nothing else in the app can set the flag, so this would be a permanent
        // lockout of every admin tool.
        $only = User::factory()->superUser()->create([
            'organization_id' => Organization::factory()->create()->id,
            'email' => 'only@example.test',
        ]);

        $this->artisan('super:grant only@example.test --revoke')->assertFailed();

        $this->assertTrue($only->fresh()->isSuper());
    }

    public function test_the_grant_command_reports_an_unknown_email(): void
    {
        $this->artisan('super:grant nobody@example.test')->assertFailed();
    }

    public function test_a_super_user_reaches_super_gated_admin_surfaces(): void
    {
        // admin/server-management sits behind the IsSuperAdmin middleware, so
        // it exercises the flag end to end through the HTTP stack.
        $org = Organization::factory()->create();
        $super = User::factory()->superUser()->create(['organization_id' => $org->id]);
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->actingAs($super)->get(route('admin.server-management'))->assertOk();
        $this->actingAs($admin)->get(route('admin.server-management'))->assertForbidden();
    }
}
