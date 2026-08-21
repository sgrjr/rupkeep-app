<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\CreatesMultiTenantFixtures;
use Tests\TestCase;

/**
 * Sanctum token boundary for the Dispatch API (TASK-306).
 *
 * `/api/dispatch/snapshot` and `/api/dispatch/apply` sit behind `auth:sanctum`
 * and additionally require a SUPER-user token — DispatchController::ensureSuper()
 * checks `$user->isSuper()` (a per-user flag, TASK-366) and
 * aborts 403 otherwise. The existing DispatchApiTest exercises this via the
 * session guard (actingAs); this class exercises the real personal-access-token
 * path with an `Authorization: Bearer` header, which is how production actually
 * authenticates dispatch:pull/push.
 *
 * NOTE: exactly ONE bearer request per test method. Laravel reuses the same
 * application instance across requests within a single test, and the Sanctum
 * RequestGuard memoizes the resolved user — a second bearer request in the same
 * method would be authenticated as the FIRST method's token holder. Splitting
 * each scenario into its own method (fresh app) is required for correctness.
 */
class DispatchApiTokenBoundaryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMultiTenantFixtures;

    private function superUserToken(): string
    {
        $org = $this->createOrganization('Platform Org');
        $user = $this->createUserForOrganization($org, User::ROLE_ADMIN);
        $user->forceFill(['is_super' => true])->save();

        return $user->createToken('dispatch-test')->plainTextToken;
    }

    private function nonSuperUserToken(): string
    {
        $org = $this->createOrganization('Some Customer Co');
        $user = $this->createUserForOrganization($org, User::ROLE_ADMIN);

        return $user->createToken('dispatch-test')->plainTextToken;
    }

    public function test_non_super_token_is_forbidden_on_snapshot(): void
    {
        $token = $this->nonSuperUserToken();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('api.dispatch.snapshot'))
            ->assertStatus(403);
    }

    public function test_non_super_token_is_forbidden_on_apply(): void
    {
        $token = $this->nonSuperUserToken();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('api.dispatch.apply'), ['tasks' => [], 'labels' => []])
            ->assertStatus(403);
    }

    public function test_super_token_is_allowed_on_snapshot(): void
    {
        $token = $this->superUserToken();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('api.dispatch.snapshot'))
            ->assertOk()
            ->assertJsonPath('@type', 'TaskCollection');
    }

    public function test_super_token_is_allowed_on_apply(): void
    {
        $token = $this->superUserToken();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(route('api.dispatch.apply'), ['tasks' => [], 'labels' => []])
            ->assertOk()
            ->assertJsonPath('status', 'applied');
    }

    public function test_missing_token_is_unauthorized_on_snapshot(): void
    {
        $this->getJson(route('api.dispatch.snapshot'))
            ->assertUnauthorized();
    }

    public function test_missing_token_is_unauthorized_on_apply(): void
    {
        $this->postJson(route('api.dispatch.apply'), ['tasks' => []])
            ->assertUnauthorized();
    }
}
