<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-366 deploy safety.
 *
 * `php artisan db:reset` runs migrate:fresh -> super:create -> db:seed. The
 * migration's backfill therefore runs against an EMPTY users table and promotes
 * nobody, and is_super is not fillable so the seeder cannot set it either.
 * `super:create` is the only thing that mints a super user on a fresh install —
 * if it stops doing so, every admin tool becomes unreachable after a reset.
 */
class FreshInstallSuperUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('setup.super_user', [
            'name' => 'Bootstrap Super',
            'email' => 'bootstrap@example.test',
            'password' => 'bootstrap-password',
            'organization_role' => 'admin',
        ]);
    }

    public function test_super_create_mints_an_application_wide_super_user(): void
    {
        // Nothing is super on a fresh database.
        $this->assertSame(0, User::where('is_super', true)->count());

        $this->artisan('super:create');

        $user = User::where('email', 'bootstrap@example.test')->firstOrFail();

        $this->assertTrue($user->isSuper(), 'a fresh install must produce a reachable super user');
        $this->assertTrue($user->isAdmin());
    }

    public function test_super_create_promotes_an_existing_user_that_predates_the_flag(): void
    {
        // The upgrade path: the account already exists from before the column.
        $existing = User::factory()->admin()->create(['email' => 'bootstrap@example.test']);

        $this->assertFalse($existing->isSuper());

        $this->artisan('super:create');

        $this->assertTrue($existing->fresh()->isSuper());
    }

    public function test_super_create_is_idempotent(): void
    {
        $this->artisan('super:create');
        $this->artisan('super:create');

        $this->assertSame(1, User::where('email', 'bootstrap@example.test')->count());
        $this->assertSame(1, User::where('is_super', true)->count());
    }

    public function test_super_create_fails_loudly_when_no_email_is_configured(): void
    {
        config()->set('setup.super_user.email', null);

        $this->artisan('super:create')->assertFailed();
    }

    public function test_super_user_lookup_prefers_the_flag_over_the_configured_email(): void
    {
        $configured = User::factory()->admin()->create(['email' => 'bootstrap@example.test']);
        $flagged = User::factory()->superUser()->create(['email' => 'real-super@example.test']);

        $this->assertSame($flagged->id, User::superUser()->id);
        $this->assertNotSame($configured->id, User::superUser()->id);
    }

    public function test_super_user_lookup_falls_back_to_the_configured_email(): void
    {
        // Before super:create has run, nobody carries the flag yet.
        $configured = User::factory()->admin()->create(['email' => 'bootstrap@example.test']);

        $this->assertSame($configured->id, User::superUser()->id);
    }

    public function test_seeded_organization_users_are_not_super(): void
    {
        // The seeder creates org admins (Mary, Matthew). Organization-scoped
        // admin must not confer application-wide access.
        $this->artisan('super:create');

        $orgAdmin = User::factory()->admin()->create(['email' => 'mary@example.test']);

        $this->assertTrue($orgAdmin->isAdmin());
        $this->assertFalse($orgAdmin->isSuper());
        $this->assertSame(1, User::where('is_super', true)->count());
    }
}
