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
        $adminOrg = Organization::factory()->create();
        $admin = User::factory()->admin()->forOrganization($adminOrg)->create();
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
        $adminOrg = Organization::factory()->create();
        $admin = User::factory()->admin()->forOrganization($adminOrg)->create();
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

        Livewire::actingAs($customerUser)
            ->test(\App\Livewire\TaskThread::class, ['task' => $task, 'portal' => true])
            ->assertSee('Public reply')
            ->assertDontSee('Internal note');
    }

    /* -------------------- Customer can comment, cannot mutate status -------------------- */

    public function test_customer_cannot_change_status(): void
    {
        $adminOrg = Organization::factory()->create();
        $admin = User::factory()->admin()->forOrganization($adminOrg)->create();
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

        Livewire::actingAs($customerUser)
            ->test(\App\Livewire\TaskShow::class, ['task' => $task, 'portal' => true])
            ->set('status', 'in_progress')
            ->call('saveMeta')
            ->assertStatus(403);

        $this->assertSame('open', $task->fresh()->status, 'Customer must not be able to change status');
    }

    /* -------------------- Feedback IS a task (no promote step) -------------------- */

    public function test_feedback_form_creates_task_directly(): void
    {
        $org = Organization::factory()->create();
        $submitter = User::factory()->admin()->forOrganization($org)->create();

        Livewire::actingAs($submitter)
            ->test(\App\Livewire\FeedbackForm::class, ['hideTrigger' => true])
            ->set('feedback', 'Please add bulk export to QuickBooks')
            ->set('severity', 'info')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $task = \App\Models\Task::where('description', 'Please add bulk export to QuickBooks')->first();
        $this->assertNotNull($task, 'A task should be created from the feedback submission.');
        $this->assertSame('triage', $task->status);
        $this->assertSame('feature', $task->type);
        $this->assertSame($submitter->id, $task->submitter_user_id);
        $this->assertSame($org->id, $task->organization_id);
        $this->assertFalse((bool) $task->is_public);
        $this->assertTrue($task->labels->contains('name', 'source:feedback'),
            'Feedback-sourced tasks should carry the source:feedback label.');
    }

    public function test_feedback_with_error_severity_creates_bug_typed_task(): void
    {
        $org = Organization::factory()->create();
        $submitter = User::factory()->admin()->forOrganization($org)->create();

        Livewire::actingAs($submitter)
            ->test(\App\Livewire\FeedbackForm::class, ['hideTrigger' => true])
            ->set('feedback', 'Invoice 1023 print is broken')
            ->set('severity', 'error')
            ->call('submit');

        $task = \App\Models\Task::where('description', 'Invoice 1023 print is broken')->first();
        $this->assertNotNull($task);
        $this->assertSame('bug', $task->type);
    }

    public function test_admin_feedback_url_redirects_to_triage_filter(): void
    {
        $org = Organization::factory()->create(['name' => 'Reynolds Upkeep']);
        $super = User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->actingAs($super)
            ->get(route('admin.feedback.index'))
            ->assertRedirect(route('tasks.index', [
                'statusFilter' => 'triage',
                'labelFilter' => 'source:feedback',
            ]));
    }

    public function test_backfill_command_converts_legacy_feedback_events_to_tasks(): void
    {
        $org = Organization::factory()->create();
        $submitter = User::factory()->forOrganization($org)->create();

        $event = UserEvent::create([
            'user_id' => $submitter->id,
            'url' => '/somewhere',
            'type' => UserEvent::TYPE_FEEDBACK,
            'severity' => 'info',
            'context' => ['feedback' => 'Legacy submission'],
            'ip' => '127.0.0.1',
        ]);

        $this->artisan('dispatch:backfill-feedback')->assertSuccessful();

        $task = \App\Models\Task::where('promoted_from_user_event_id', $event->id)->first();
        $this->assertNotNull($task);
        $this->assertSame('triage', $task->status);
        $this->assertTrue($task->labels->contains('name', 'source:feedback'));

        // Idempotent: second run skips it.
        $this->artisan('dispatch:backfill-feedback')->assertSuccessful();
        $this->assertSame(1, \App\Models\Task::where('promoted_from_user_event_id', $event->id)->count());
    }

    /* -------------------- Send Customer Update -------------------- */

    public function test_send_customer_update_emails_submitter_and_marks_sent(): void
    {
        Notification::fake();

        $adminOrg = Organization::factory()->create();
        $admin = User::factory()->admin()->forOrganization($adminOrg)->create();
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
        $adminOrg = Organization::factory()->create();
        $admin = User::factory()->admin()->forOrganization($adminOrg)->create();

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
        $adminOrg = Organization::factory()->create();
        $admin = User::factory()->admin()->forOrganization($adminOrg)->create();

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

    /* -------------------- tasks:import preserves unpushed local state (TASK-332) -------------------- */

    public function test_import_preserves_local_status_when_transition_is_newer_than_snapshot(): void
    {
        $org = Organization::factory()->create();

        // Local task was closed locally *after* the snapshot was taken.
        $task = Task::create([
            'code' => 'TASK-700', 'title' => 'Closed locally', 'type' => 'feature',
            'priority' => 'high', 'status' => 'done', 'organization_id' => $org->id,
        ]);
        $transition = $task->comments()->create([
            'body' => 'Status changed from `open` to `done`.',
            'event_type' => TaskComment::EVENT_STATUS_CHANGE,
            'meta' => ['from' => 'open', 'to' => 'done'],
        ]);
        $transition->created_at = now();
        $transition->save();

        // Snapshot still thinks it is open, and predates the local transition.
        $path = $this->writeSnapshot([[
            'code' => 'TASK-700', 'title' => 'Closed locally', 'type' => 'feature',
            'priority' => 'high', 'status' => 'open',
            'updatedAt' => now()->subDay()->toIso8601String(),
        ]]);

        $this->artisan('tasks:import', ['--path' => $path])->assertSuccessful();

        $this->assertSame('done', $task->fresh()->status,
            'Import must not revert a status the local side changed after the snapshot was taken.');
    }

    public function test_import_applies_snapshot_status_when_no_local_transition_is_newer(): void
    {
        $org = Organization::factory()->create();

        $task = Task::create([
            'code' => 'TASK-701', 'title' => 'Stale local', 'type' => 'feature',
            'priority' => 'high', 'status' => 'open', 'organization_id' => $org->id,
        ]);

        // Snapshot advances the task; no competing local transition exists.
        $path = $this->writeSnapshot([[
            'code' => 'TASK-701', 'title' => 'Stale local', 'type' => 'feature',
            'priority' => 'high', 'status' => 'done',
            'updatedAt' => now()->toIso8601String(),
        ]]);

        $this->artisan('tasks:import', ['--path' => $path])->assertSuccessful();

        $this->assertSame('done', $task->fresh()->status,
            'Import should still apply production status when there is no newer local transition.');
    }

    public function test_import_merges_comments_without_destroying_local_or_metadata(): void
    {
        $org = Organization::factory()->create();
        $author = User::factory()->admin()->forOrganization($org)->create(['email' => 'author@example.com']);

        $task = Task::create([
            'code' => 'TASK-702', 'title' => 'Threaded', 'type' => 'feature',
            'priority' => 'medium', 'status' => 'open', 'organization_id' => $org->id,
        ]);
        $task->comments()->create([
            'body' => 'Local-only note that was never pushed',
            'event_type' => TaskComment::EVENT_COMMENT,
            'is_internal' => true,
        ]);

        $createdAt = now()->subMonth();
        $path = $this->writeSnapshot([[
            'code' => 'TASK-702', 'title' => 'Threaded', 'type' => 'feature',
            'priority' => 'medium', 'status' => 'open',
            'updatedAt' => now()->toIso8601String(),
            'comments' => [[
                'body' => 'Comment from production',
                'author' => 'author@example.com',
                'isInternal' => false,
                'sentToCustomer' => true,
                'eventType' => TaskComment::EVENT_COMMENT,
                'createdAt' => $createdAt->toIso8601String(),
            ]],
        ]]);

        $this->artisan('tasks:import', ['--path' => $path])->assertSuccessful();

        $task->refresh();
        $this->assertSame(2, $task->comments()->count(),
            'Local-only comment must survive; snapshot comment must be added.');

        $this->assertTrue($task->comments()->where('body', 'Local-only note that was never pushed')->exists(),
            'Replace-all import used to destroy unpushed local comments.');

        $imported = $task->comments()->where('body', 'Comment from production')->firstOrFail();
        $this->assertSame($author->id, $imported->user_id, 'Author must be preserved from the snapshot.');
        $this->assertTrue($imported->sent_to_customer, 'sent_to_customer must be preserved.');
        // ISO-8601 truncates sub-second precision on both sides, so compare there.
        $this->assertSame($createdAt->toIso8601String(), $imported->created_at->toIso8601String(),
            'Original timestamp must be preserved, not reset to now.');

        // Idempotent: re-importing the same snapshot adds nothing.
        $this->artisan('tasks:import', ['--path' => $path])->assertSuccessful();
        $this->assertSame(2, $task->fresh()->comments()->count(),
            'Re-import must not duplicate the snapshot comment.');
    }

    /**
     * Write a minimal tasks.jsonld snapshot to a temp path under the project
     * and return the base_path-relative path the import command expects.
     */
    private function writeSnapshot(array $tasks): string
    {
        $relative = 'storage/framework/testing/import-snapshot.jsonld';
        $full = base_path($relative);

        if (!is_dir(dirname($full))) {
            mkdir(dirname($full), 0775, true);
        }

        file_put_contents($full, json_encode([
            '@type' => 'TaskCollection',
            'schemaVersion' => '1.0',
            'tasks' => $tasks,
            'labels' => [],
        ]));

        return $relative;
    }
}
