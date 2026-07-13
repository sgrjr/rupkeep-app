<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchApiTest extends TestCase
{
    use RefreshDatabase;

    private function superUser(): User
    {
        // is_super is derived from the organization name.
        $org = Organization::factory()->create(['name' => 'Reynolds Upkeep']);

        return User::factory()->admin()->create(['organization_id' => $org->id]);
    }

    public function test_apply_requires_super_user(): void
    {
        $org = Organization::factory()->create(['name' => 'Some Customer Co']);
        $user = User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->actingAs($user)
            ->postJson(route('api.dispatch.apply'), ['tasks' => []])
            ->assertStatus(403);
    }

    public function test_apply_merges_comments_and_preserves_production_only_replies(): void
    {
        $super = $this->superUser();

        // Production already has a task with a customer reply made since last pull.
        $task = Task::create([
            'code' => 'TASK-800', 'title' => 'Existing', 'type' => 'feature',
            'priority' => 'medium', 'status' => 'open', 'organization_id' => $super->organization_id,
        ]);
        $task->comments()->create([
            'body' => 'Customer reply made on production',
            'event_type' => TaskComment::EVENT_COMMENT,
            'is_internal' => false,
        ]);

        $createdAt = now()->subWeek();
        $payload = [
            'tasks' => [[
                'code' => 'TASK-800', 'title' => 'Existing', 'type' => 'feature',
                'priority' => 'medium', 'status' => 'open',
                'comments' => [[
                    'body' => 'Staff note pushed from local',
                    'author' => $super->email,
                    'isInternal' => true,
                    'sentToCustomer' => false,
                    'eventType' => TaskComment::EVENT_COMMENT,
                    'createdAt' => $createdAt->toIso8601String(),
                ]],
            ]],
            'labels' => [],
        ];

        $this->actingAs($super)
            ->postJson(route('api.dispatch.apply'), $payload)
            ->assertOk()
            ->assertJsonPath('summary.comments_added', 1);

        $task->refresh();
        $this->assertSame(2, $task->comments()->count(),
            'Production-only customer reply must survive; pushed staff note must be added.');
        $this->assertTrue($task->comments()->where('body', 'Customer reply made on production')->exists(),
            'Push must not delete comments that exist only on production.');

        $pushed = $task->comments()->where('body', 'Staff note pushed from local')->firstOrFail();
        $this->assertSame($super->id, $pushed->user_id, 'Author must be resolved from the pushed email.');
        $this->assertSame($createdAt->toIso8601String(), $pushed->created_at->toIso8601String(),
            'Pushed comment timestamp must be preserved, not reset to now.');
    }

    public function test_apply_is_idempotent_on_comments(): void
    {
        $super = $this->superUser();

        $payload = [
            'tasks' => [[
                'code' => 'TASK-801', 'title' => 'Idem', 'type' => 'feature',
                'priority' => 'medium', 'status' => 'open',
                'comments' => [[
                    'body' => 'Only note',
                    'eventType' => TaskComment::EVENT_COMMENT,
                ]],
            ]],
            'labels' => [],
        ];

        $this->actingAs($super)->postJson(route('api.dispatch.apply'), $payload)->assertOk();
        $this->actingAs($super)->postJson(route('api.dispatch.apply'), $payload)->assertOk();

        $task = Task::where('code', 'TASK-801')->firstOrFail();
        $this->assertSame(1, $task->comments()->count(),
            'Re-applying the same push must not duplicate comments.');
    }
}
