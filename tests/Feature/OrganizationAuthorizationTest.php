<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-432 regression. POST /organization had no authorization and
 * mass-assigned the raw request (user_id and deleted_at are fillable), so any
 * signed-in user could create an organization with any owner, and an org
 * admin could hand their org to someone else or soft-delete it through the
 * edit form. Creating is super-only; owner changes go through updateOwner.
 */
class OrganizationAuthorizationTest extends TestCase
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

    /**
     * Page checks live apart from the POST checks: a 403 thrown inside a
     * Livewire mount leaves Livewire's redirector bound in the test app, and
     * a later controller redirect()->with() in the same test then breaks.
     */
    public function test_create_page_is_super_only(): void
    {
        $customer = $this->user($this->orgA, User::ROLE_CUSTOMER);
        $admin = $this->user($this->orgA, User::ROLE_ADMIN);
        $super = User::factory()->superUser()->create(['organization_id' => $this->orgA->id]);

        // The staff middleware bounces customers to their portal (TASK-434);
        // staff without the permission get a plain 403.
        $this->actingAs($customer)->get(route('organizations.create'))->assertRedirect(route('customer.invoices.index'));
        $this->actingAs($admin)->get(route('organizations.create'))->assertForbidden();
        $this->actingAs($super)->get(route('organizations.create'))->assertOk();
    }

    public function test_only_a_super_user_can_create_an_organization(): void
    {
        $customer = $this->user($this->orgA, User::ROLE_CUSTOMER);
        $admin = $this->user($this->orgA, User::ROLE_ADMIN);

        $this->actingAs($customer)
            ->post(route('organizations.store'), ['name' => 'Rogue', 'user_id' => $customer->id])
            ->assertRedirect(route('customer.invoices.index'));
        $this->actingAs($admin)
            ->post(route('organizations.store'), ['name' => 'Rogue', 'user_id' => $admin->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('organizations', ['name' => 'Rogue']);

        $super = User::factory()->superUser()->create(['organization_id' => $this->orgA->id]);
        $owner = $this->user($this->orgB, User::ROLE_ADMIN, ['email' => 'owner@example.test']);

        $this->actingAs($super)
            ->post(route('organizations.store'), [
                'name' => 'New Co',
                'owner_email' => 'owner@example.test',
                'user_id' => $super->id,        // ignored: owner comes from owner_email
                'deleted_at' => now()->toDateTimeString(), // ignored
            ])
            ->assertRedirect(route('organizations.index'));

        $created = Organization::where('name', 'New Co')->firstOrFail();
        $this->assertSame($owner->id, $created->user_id);
        $this->assertNull($created->deleted_at);
    }

    public function test_unknown_owner_email_falls_back_to_the_super_user(): void
    {
        $super = User::factory()->superUser()->create(['organization_id' => $this->orgA->id]);

        $this->actingAs($super)
            ->post(route('organizations.store'), ['name' => 'Orphan', 'owner_email' => 'nobody@example.test'])
            ->assertRedirect();

        $this->assertSame($super->id, Organization::where('name', 'Orphan')->firstOrFail()->user_id);
    }

    public function test_org_admin_can_edit_their_org_but_not_its_owner_or_deletion(): void
    {
        $admin = $this->user($this->orgA, User::ROLE_ADMIN);
        $originalOwner = $this->orgA->user_id;
        $this->user($this->orgB, User::ROLE_ADMIN, ['email' => 'other@example.test']);

        $this->actingAs($admin)->get(route('organizations.edit', $this->orgA))->assertOk();

        $this->actingAs($admin)
            ->patch(route('organizations.update', $this->orgA), [
                'name' => 'A renamed',
                'user_id' => $admin->id,
                'deleted_at' => now()->toDateTimeString(),
            ])
            ->assertRedirect(route('organizations.index'));

        $this->orgA->refresh();
        $this->assertSame('A renamed', $this->orgA->name);
        $this->assertSame($originalOwner, $this->orgA->user_id);
        $this->assertNull($this->orgA->deleted_at);

        // A different owner email is the one field with its own (super-only) policy.
        $this->actingAs($admin)
            ->patch(route('organizations.update', $this->orgA), ['name' => 'A', 'owner_email' => 'other@example.test'])
            ->assertForbidden();

        $this->assertSame($originalOwner, $this->orgA->fresh()->user_id);
    }

    public function test_super_user_can_change_the_owner(): void
    {
        $super = User::factory()->superUser()->create(['organization_id' => $this->orgA->id]);
        $newOwner = $this->user($this->orgB, User::ROLE_ADMIN, ['email' => 'other@example.test']);

        $this->actingAs($super)
            ->patch(route('organizations.update', $this->orgA), ['name' => 'A', 'owner_email' => 'other@example.test'])
            ->assertRedirect(route('organizations.index'));

        $this->assertSame($newOwner->id, $this->orgA->fresh()->user_id);

        $this->actingAs($super)
            ->patch(route('organizations.update', $this->orgA), ['name' => 'A', 'owner_email' => 'ghost@example.test'])
            ->assertSessionHasErrors('owner_email');
    }

    public function test_org_admin_cannot_touch_another_organization(): void
    {
        $admin = $this->user($this->orgA, User::ROLE_ADMIN);

        $this->actingAs($admin)->get(route('organizations.edit', $this->orgB))->assertForbidden();
        $this->actingAs($admin)
            ->patch(route('organizations.update', $this->orgB), ['name' => 'Hijacked'])
            ->assertForbidden();
        $this->actingAs($admin)->delete(route('organizations.delete', $this->orgB))->assertForbidden();

        $this->assertSame('B', $this->orgB->fresh()->name);
        $this->assertNull($this->orgB->fresh()->deleted_at);
    }

    public function test_customer_cannot_edit_their_own_organization(): void
    {
        $customer = $this->user($this->orgA, User::ROLE_CUSTOMER);

        $this->actingAs($customer)->get(route('organizations.edit', $this->orgA))->assertRedirect(route('customer.invoices.index'));
        $this->actingAs($customer)
            ->patch(route('organizations.update', $this->orgA), ['name' => 'Hijacked'])
            ->assertRedirect(route('customer.invoices.index'));

        $this->assertSame('A', $this->orgA->fresh()->name);
    }
}
