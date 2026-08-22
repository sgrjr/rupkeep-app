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
     * Impersonation has to survive the redirect, and asserting the guard in
     * memory does not prove that -- the previous fix passed that assertion
     * while production logged the admin straight back out to /login.
     *
     * Jetstream's AuthenticateSession middleware keeps the signed-in user's
     * password hash in the session and logs everyone out when it stops matching
     * the current user, which is how a password change kills other sessions.
     * It re-stores that hash AFTER the response from $request->user() -- and
     * $request->user() resolves through the DEFAULT guard, which is Sanctum's
     * RequestGuard, which memoised the ADMIN when auth:sanctum ran at the top
     * of the request. So the session ended up logged in as the target while
     * carrying the impersonator's password hash, and the next request threw it
     * away as a stale session.
     */
    public function test_the_impersonated_session_survives_the_next_request(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);

        // Distinct hashes: the User factory reuses one cached bcrypt hash for
        // every user, which makes the admin's and the target's indistinguishable
        // and the assertion below vacuous.
        $target = User::factory()->create([
            'organization_id' => $organization->id,
            'password' => bcrypt('a-different-password'),
        ]);

        $this->actingAs($admin)->get(route('impersonate', ['user' => $target->id]));

        $session = app('session.store')->all();
        $hashKey = 'password_hash_'.auth()->getDefaultDriver();

        $this->assertArrayHasKey($hashKey, $session);
        $this->assertSame(
            $target->password,
            $session[$hashKey],
            'The session must carry the password hash of the user being impersonated. '
            .'Holding the one belonging to the impersonator makes AuthenticateSession '
            .'discard the session on the very next request.'
        );

        // And the session really does point at the target, not merely the guard
        // we happened to leave in memory.
        $this->assertSame(
            $target->id,
            $session['login_web_'.sha1(\Illuminate\Auth\SessionGuard::class)] ?? null
        );
    }

    /**
     * The same failure asserted end to end rather than through the mechanism.
     *
     * Without the fix this is a 302 to /login with a null guard, which is
     * exactly what the admin saw: click Impersonate, get logged out.
     */
    public function test_a_following_request_is_still_the_impersonated_user(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);
        $target = User::factory()->create([
            'organization_id' => $organization->id,
            'password' => bcrypt('a-different-password'),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('impersonate', ['user' => $target->id]));

        $session = app('session.store')->all();

        // Drop every in-memory guard and the actingAs user resolver, so the next
        // request has to reconstruct identity from the session alone -- which is
        // the only thing a real browser carries between the redirect and the
        // page it lands on.
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('sanctum');

        $this->withSession($session)->get(route('my.profile'))->assertOk();

        $this->assertSame($target->id, auth()->guard('web')->id());
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
