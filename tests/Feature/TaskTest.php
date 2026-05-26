<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\UserEvent;
use App\Notifications\TaskUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    /* -------------------- Code sequencing -------------------- */

    public function test_next_code_increments_from_highest_existing(): void
    {
        $org = Organization::factory()->create();

        Task::create(['code' => 'TASK-005', 'title' => 'Five',  'type' => 'feature', 'priority' => 'low', 'status' => 'open', 'organization_id' => $org->id]);
        Task::create(['code' => 'TASK-042', 'title' => 'Forty', 'type' => 'feature', 'priority' => 'low', 'status' => 'open', 'organization_id' => $org->id]);

        $this->assertSame('TASK-043', Task::nextCode());
    }

    /* -------------------- Status transitions -------------------- */

    public function test_status_change_records_system_comment(): void
    {
        $admin = User::factory()->admin()->create();
        $task = Task::create([
            'code' => Task::nextCode(),
            'title' => 'Demo task',
            'type' => 'feature',
            'priority' => 'medium',
            'status' => 'open',
            'organization_id' => $admin->organization_id,
        ]);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\TaskShow::class, ['task' => $task])
            ->set('status', 'in_progress')
            ->call('saveMeta')
            ->assertHasNoErrors();

        $task->refresh();
        $this->assertSame('in_progress', $task->status);

        $event = $task->comments()->where('event_type', TaskComment::EVENT_STATUS_CHANGE)->first();
        $this->assertNotNull($event, 'status_change system comment should exist');
        $this->assertStringContainsString('open', $event->body);
        $this->assertStringContainsString('in_progress', $event->body);
    }

    /* -------------------- Comment visibility -------------------- */

    public function test_customer_cannot_see_internal_comments(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create(['organization_id' => $admin->organization_id]);
        $customerUser = User::factory()->asCustomer($customer)->create();

        $task = Task::create([
            'code' => Task::nextCode(),
            'title' => 'Demo',
            'type' => 'feature',
            'priority' => 'medium',
            'status' => 'open',
            'organization_id' => $admin->organization_id,
            'submitter_user_id' => $customerUser->id,
        ]);

        $task->comments()->create(['user_id' => $admin->id, 'body' => 'Public reply',   'is_internal' => false, 'event_type' => TaskComment::EVENT_COMMENT]);
        $task->comments()->create(['user_id' => $admin->id, 'body' => 'Internal note',  'is_internal' => true,  'event_type' => TaskComment::EVENT_COMMENT]);

        $component = Livewire::actingAs($customerUser)
            ->test(\App\Livewire\TaskThread::class, ['task' => $task, 'portal' => true]);

        $rendered = $component->payload['effects']['html'] ?? '';
        $this->assertStringContainsString('Public reply', $rendered);
        $this->assertStringNotContainsString('Internal note', $rendered);
    }

    /* -------------------- Customer can comment, cannot mutate status -------------------- */

    public function test_customer_cannot_change_status(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create(['organization_id' => $admin->organization_id]);
        $customerUser = User::factory()->asCustomer($customer)->create();

        $task = Task::create([
            'code' => Task::nextCode(),
            'title' => 'Demo',
            'type' => 'feature',
            'priority' => 'medium',
            'status' => 'open',
            'organization_id' => $admin->organization_id,
            'submitter_user_id' => $customerUser->id,
        ]);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        Livewire::actingAs($customerUser)
            ->test(\App\Livewire\TaskShow::class, ['task' => $task, 'portal' => true])
            ->set('status', 'in_progress')
            ->call('saveMeta');
    }

    /* -------------------- Promote from feedback -------------------- */

    public function test_promote_from_feedback_creates_task_with_provenance(): void
    {
        $superOrg = Organization::factory()->create(['is_super' => true]);
        $super = User::factory()->admin()->create(['organization_id' => $superOrg->id]);
        $submitter = User::factory()->create();

        $event = UserEvent::create([
            'user_id' => $submitter->id,
            'url' => '/somewhere',
            'type' => UserEvent::TYPE_FEEDBACK,
            'severity' => 'info',
            'context' => ['feedback' => 'Please add bulk export to QuickBooks'],
            'ip' => '127.0.0.1',
        ]);

        $this->actingAs($super)
            ->post(route('tasks.promote', $event))
            ->assertRedirect();

        $task = $event->refresh()->promotedTask;
        $this->assertNotNull($task);
        $this->assertSame($event->id, $task->promoted_from_user_event_id);
        $this->assertSame('triage', $task->status);
        $this->assertSame($submitter->id, $task->submitter_user_id);

        $this->assertTrue($task->comments()->where('event_type', TaskComment::EVENT_PROMOTED)->exists());
    }

    /* -------------------- Send Customer Update -------------------- */

    public function test_send_customer_update_emails_submitter_and_marks_sent(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create(['organization_id' => $admin->organization_id]);
        $submitter = User::factory()->asCustomer($customer)->create();

        $task = Task::create([
            'code' => Task::nextCode(),
            'title' => 'Demo',
            'type' => 'feature',
            'priority' => 'medium',
            'status' => 'open',
            'organization_id' => $admin->organization_id,
            'submitter_user_id' => $submitter->id,
        ]);

        $comment = $task->comments()->create([
            'user_id' => $admin->id,
            'body' => 'Update for the customer',
            'is_internal' => false,
            'event_type' => TaskComment::EVENT_COMMENT,
        ]);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\TaskThread::class, ['task' => $task])
            ->call('sendCustomerUpdate', $comment->id);

        $this->assertTrue($comment->refresh()->sent_to_customer);
        Notification::assertSentTo($submitter, TaskUpdate::class);
    }

    /* -------------------- Roadmap visibility -------------------- */

    public function test_roadmap_only_shows_public_tasks(): void
    {
        $admin = User::factory()->admin()->create();

        $pub = Task::create([
            'code' => 'TASK-901', 'title' => 'Public roadmap item', 'type' => 'feature', 'priority' => 'medium', 'status' => 'open', 'is_public' => true, 'organization_id' => $admin->organization_id,
        ]);
        $priv = Task::create([
            'code' => 'TASK-902', 'title' => 'Hidden internal item', 'type' => 'feature', 'priority' => 'medium', 'status' => 'open', 'is_public' => false, 'organization_id' => $admin->organization_id,
        ]);

        $this->actingAs($admin)
            ->get(route('documentation.roadmap'))
            ->assertOk()
            ->assertSee('Public roadmap item')
            ->assertDontSee('Hidden internal item');
    }

    /* -------------------- Board move -------------------- */

    public function test_board_move_updates_status_and_position(): void
    {
        $admin = User::factory()->admin()->create();

        $a = Task::create(['code' => 'TASK-501', 'title' => 'A', 'type' => 'feature', 'priority' => 'medium', 'status' => 'open', 'organization_id' => $admin->organization_id]);
        $b = Task::create(['code' => 'TASK-502', 'title' => 'B', 'type' => 'feature', 'priority' => 'medium', 'status' => 'in_progress', 'organization_id' => $admin->organization_id]);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\TaskBoard::class)
            ->call('moveCard', 'TASK-501', 'in_progress', ['TASK-502', 'TASK-501']);

        $a->refresh();
        $b->refresh();

        $this->assertSame('in_progress', $a->status);
        $this->assertSame(0, $b->position);
        $this->assertSame(1, $a->position);

        $this->assertTrue($a->comments()->where('event_type', TaskComment::EVENT_STATUS_CHANGE)->exists());
    }
}
