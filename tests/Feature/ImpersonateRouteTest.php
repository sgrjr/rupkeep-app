<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-373 — GET /impersonate/{user} was registered outside every auth group,
 * so an anonymous request reached MyUsersController::impersonate(), which calls
 * auth()->user()->can(...) on a null user. That is a 500, not a redirect to
 * login. The authorization check itself was correct for signed-in users.
 */
class ImpersonateRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_anonymous_visitor_is_sent_to_login_not_a_500(): void
    {
        $organization = Organization::factory()->create();
        $target = User::factory()->create(['organization_id' => $organization->id]);

        $this->get(route('impersonate', ['user' => $target->id]))
            ->assertRedirect(route('login'));
    }

    public function test_a_signed_in_user_without_permission_is_refused_not_logged_in_as_the_target(): void
    {
        $organization = Organization::factory()->create();
        $driver = User::factory()->create(['organization_id' => $organization->id]);
        $target = User::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($driver)
            ->get(route('impersonate', ['user' => $target->id]))
            ->assertRedirect('/');

        $this->assertSame($driver->id, auth()->id(), 'The refused user must stay themselves.');
    }

    /**
     * The same null-dereference one step further in: a stale or hand-typed id
     * reached the failure branch and read ->name off a null user.
     */
    public function test_an_unknown_user_id_is_a_404_not_a_500(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);

        $this->actingAs($admin)
            ->get(route('impersonate', ['user' => 999999]))
            ->assertNotFound();
    }

    /**
     * The path TASK-373 never covered: impersonation actually working.
     *
     * Every other test here asserts a REFUSAL, so all four passed while the
     * success path threw a 500 in production. Moving the route inside the
     * auth:sanctum group made that middleware call Auth::shouldUse('sanctum'),
     * which swaps the default guard to Sanctum's RequestGuard for the rest of
     * the request -- and RequestGuard has no logoutCurrentDevice(), that lives
     * on SessionGuard. auth()->guard() with no argument therefore stopped
     * resolving to the session guard the moment the route became authenticated.
     */
    public function test_an_admin_can_actually_impersonate_someone(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);
        $target = User::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($admin)
            ->get(route('impersonate', ['user' => $target->id]))
            ->assertRedirect(route('my.profile'));

        // Named guard here for the same reason the controller names it: after
        // auth:sanctum has run, the DEFAULT guard is Sanctum's, and asking it
        // who is logged in does not answer the question. The session guard is
        // what the next request will read.
        $this->assertAuthenticatedAs($target, 'web');
    }

    /**
     * The navigation menu shows an impersonation banner from this session key,
     * so without it an admin has no indication they are wearing someone else's
     * account. It was written with session('impersonate', $id) -- which is the
     * two-argument GETTER, reading the key with a default, never writing it.
     */
    public function test_the_session_remembers_who_is_impersonating(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);
        $target = User::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($admin)
            ->get(route('impersonate', ['user' => $target->id]))
            ->assertSessionHas('impersonate', $admin->id);
    }

    /**
     * The whole point of moving the route: no route may sit outside the
     * authenticated group by accident again.
     */
    public function test_impersonate_carries_the_authentication_middleware(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'impersonate');

        $this->assertNotNull($route);
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
    }
}
