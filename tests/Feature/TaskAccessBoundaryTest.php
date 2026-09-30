<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Livewire\TaskBoard;
use App\Livewire\TaskList;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-439 regression. The tracker treated every employee as staff, so
 * drivers could work the queue, and tasks with no organization (the dev
 * backlog) were listed and editable for staff of every tenant. The dashboard
 * card showed every tenant's triage queue.
 */
class TaskAccessBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private Task $taskA;
    private Task $taskB;
    private Task $backlog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->orgA = Organization::factory()->create();
        $this->orgB = Organization::factory()->create();
        $this->taskA = $this->task(['title' => 'Org A request', 'organization_id' => $this->orgA->id]);
        $this->taskB = $this->task(['title' => 'Org B request', 'organization_id' => $this->orgB->id]);
        $this->backlog = $this->task(['title' => 'Dev backlog item', 'organization_id' => null]);
    }

    private function task(array $overrides): Task
    {
        return Task::create(array_merge([
            'code' => Task::nextCode(),
            'title' => 'A request',
            'type' => 'feature',
            'priority' => 'low',
            'status' => 'triage',
        ], $overrides));
    }

    public function test_drivers_file_feedback_but_do_not_work_the_queue(): void
    {
        $driver = User::factory()->forOrganization($this->orgA)->create();

        $this->assertTrue($driver->can('create', Task::class));
        $this->assertFalse($driver->can('viewAny', Task::class));
        $this->assertFalse($driver->can('update', $this->taskA));
        $this->assertFalse($driver->can('commentInternal', $this->taskA));

        $this->actingAs($driver)->get(route('tasks.index'))->assertForbidden();
        $this->actingAs($driver)->get(route('tasks.board'))->assertForbidden();
    }

    public function test_org_staff_see_and_work_only_their_own_organizations_tasks(): void
    {
        $manager = User::factory()->manager()->forOrganization($this->orgA)->create();

        $this->assertTrue($manager->can('update', $this->taskA));
        $this->assertFalse($manager->can('update', $this->taskB));
        $this->assertFalse($manager->can('update', $this->backlog));
        $this->assertFalse($manager->can('view', $this->backlog));

        Livewire::actingAs($manager)->test(TaskList::class)
            ->assertSee('Org A request')
            ->assertDontSee('Org B request')
            ->assertDontSee('Dev backlog item');

        Livewire::actingAs($manager)->test(TaskBoard::class)
            ->assertSee('Org A request')
            ->assertDontSee('Org B request')
            ->assertDontSee('Dev backlog item');

        $this->actingAs($manager)->get(route('tasks.show', $this->backlog))->assertForbidden();
    }

    public function test_super_user_sees_everything_including_the_backlog(): void
    {
        $super = User::factory()->superUser()->forOrganization($this->orgA)->create();

        $this->assertTrue($super->can('update', $this->backlog));

        Livewire::actingAs($super)->test(TaskList::class)
            ->assertSee('Org A request')
            ->assertSee('Org B request')
            ->assertSee('Dev backlog item');
    }

    public function test_dashboard_card_is_scoped_to_the_organization(): void
    {
        $manager = User::factory()->manager()->forOrganization($this->orgA)->create();

        Livewire::actingAs($manager)->test(Dashboard::class)
            ->assertViewHas('totalFeedback', 1)
            ->assertViewHas('recentFeedback', fn ($recent) => $recent->pluck('title')->all() === ['Org A request']);

        // A driver sees their own submissions only, like a customer.
        $driver = User::factory()->forOrganization($this->orgA)->create();
        $this->task(['title' => 'Driver request', 'organization_id' => $this->orgA->id, 'submitter_user_id' => $driver->id]);

        Livewire::actingAs($driver)->test(Dashboard::class)
            ->assertViewHas('totalFeedback', 1)
            ->assertViewHas('recentFeedback', fn ($recent) => $recent->pluck('title')->all() === ['Driver request']);
    }
}
