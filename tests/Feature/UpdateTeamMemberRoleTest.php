<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Laravel\Jetstream\Http\Livewire\TeamMemberManager;
use Livewire\Livewire;
use Tests\TestCase;

class UpdateTeamMemberRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Features::hasTeamFeatures()) {
            $this->markTestSkipped('Team features are disabled.');
        }
    }

    public function test_team_member_roles_can_be_updated(): void
    {
        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

        $user->currentTeam->users()->attach(
            $otherUser = User::factory()->create(), ['role' => User::ROLE_ADMIN]
        );

        Livewire::test(TeamMemberManager::class, ['team' => $user->currentTeam])
            ->set('managingRoleFor', $otherUser)
            ->set('currentRole', User::ROLE_EMPLOYEE_MANAGER)
            ->call('updateRole');

        $this->assertTrue($otherUser->fresh()->hasTeamRole(
            $user->currentTeam->fresh(), User::ROLE_EMPLOYEE_MANAGER
        ));
    }

    public function test_only_team_owner_can_update_team_member_roles(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $user->currentTeam->users()->attach(
            $otherUser = User::factory()->create(), ['role' => User::ROLE_ADMIN]
        );

        $this->actingAs($otherUser);

        Livewire::test(TeamMemberManager::class, ['team' => $user->currentTeam])
            ->set('managingRoleFor', $otherUser)
            ->set('currentRole', User::ROLE_EMPLOYEE_MANAGER)
            ->call('updateRole')
            ->assertStatus(403);

        $this->assertTrue($otherUser->fresh()->hasTeamRole(
            $user->currentTeam->fresh(), User::ROLE_ADMIN
        ));
    }
}
