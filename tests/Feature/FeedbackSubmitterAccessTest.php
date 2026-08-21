<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * TASK-361: the feedback form is open to every authenticated user and hands
 * back a link to the task it created, but the portal task routes gated on
 * isCustomer/isAdmin/is_super — so a manager or driver who submitted feedback
 * got a 403 on their own submission. TaskPolicy::view() already grants the
 * submitter access; the controller's role check was the thing disagreeing.
 */
class FeedbackSubmitterAccessTest extends TestCase
{
    use RefreshDatabase;

    private function submitFeedbackAs(User $user): Task
    {
        Livewire::actingAs($user)
            ->test('feedback-form')
            ->set('feedback', 'The invoice extras are showing zero.')
            ->set('severity', 'error')
            ->call('submit')
            ->assertHasNoErrors();

        return Task::where('submitter_user_id', $user->id)->firstOrFail();
    }

    public static function submitterRoles(): array
    {
        return [
            'manager' => ['manager'],
            'standard employee' => ['standard'],
            'customer' => ['customerRole'],
        ];
    }

    #[DataProvider('submitterRoles')]
    public function test_submitter_can_open_the_task_the_feedback_form_linked_them_to(string $role): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->{$role}()->create(['organization_id' => $organization->id]);

        $task = $this->submitFeedbackAs($user);

        $this->actingAs($user)
            ->get(route('portal.tasks.show', $task->code))
            ->assertOk()
            ->assertSee($task->code);
    }

    #[DataProvider('submitterRoles')]
    public function test_submitter_can_reach_their_requests_list(string $role): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->{$role}()->create(['organization_id' => $organization->id]);

        $this->submitFeedbackAs($user);

        $this->actingAs($user)
            ->get(route('portal.tasks.index'))
            ->assertOk()
            ->assertSee('My Requests');
    }

    public function test_a_user_still_cannot_open_someone_elses_private_task(): void
    {
        $organization = Organization::factory()->create();
        $submitter = User::factory()->customerRole()->create(['organization_id' => $organization->id]);
        $other = User::factory()->customerRole()->create(['organization_id' => $organization->id]);

        $task = $this->submitFeedbackAs($submitter);

        $this->actingAs($other)
            ->get(route('portal.tasks.show', $task->code))
            ->assertForbidden();
    }
}
