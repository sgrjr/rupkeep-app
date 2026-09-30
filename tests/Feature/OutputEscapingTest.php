<?php

namespace Tests\Feature;

use App\Livewire\ShowPilotCarJob;
use App\Livewire\TaskShow;
use App\Livewire\TaskThread;
use App\Models\Organization;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fixtures\CreatesMultiTenantFixtures;
use Tests\TestCase;

/**
 * TASK-440 regression. Task descriptions and comments were rendered through
 * Str::markdown() with raw HTML allowed, and the job memo went into an href
 * unescaped. Customers write both, super users and staff read them.
 */
class OutputEscapingTest extends TestCase
{
    use RefreshDatabase, CreatesMultiTenantFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function task(Organization $org, string $description): Task
    {
        return Task::create([
            'code' => Task::nextCode(),
            'title' => 'A request',
            'type' => 'feature',
            'priority' => 'low',
            'status' => 'triage',
            'organization_id' => $org->id,
            'description' => $description,
        ]);
    }

    public function test_task_markdown_strips_raw_html_and_unsafe_links(): void
    {
        $org = Organization::factory()->create();
        $super = User::factory()->superUser()->forOrganization($org)->create();
        // Markdown on its own line: a line that starts a raw HTML block
        // swallows the rest of that line (CommonMark), so a same-line
        // `**bold**` would vanish with the script and prove nothing.
        $task = $this->task($org, "<script>alert(1)</script><img src=x onerror=alert(1)>\n\n**bold** [go](javascript:alert(1))");

        $html = Livewire::actingAs($super)->test(TaskShow::class, ['task' => $task])->html();

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
    }

    public function test_comment_markdown_strips_raw_html(): void
    {
        $org = Organization::factory()->create();
        $super = User::factory()->superUser()->forOrganization($org)->create();
        $task = $this->task($org, 'plain');
        TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $super->id,
            'body' => "<script>alert(2)</script>\n\n_italic_",
            'is_internal' => false,
        ]);

        $html = Livewire::actingAs($super)->test(TaskThread::class, ['task' => $task])->html();

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('<em>italic</em>', $html);
    }

    public function test_job_memo_link_is_escaped_and_validated(): void
    {
        $org = $this->createOrganization('A');
        $manager = $this->createUserForOrganization($org, User::ROLE_EMPLOYEE_MANAGER);
        $customer = $this->createCustomerForOrganization($org);

        $hostile = $this->createJobForOrganization($org, $customer, ['memo' => 'http://x" onmouseover="alert(1)']);
        $html = Livewire::actingAs($manager)->test(ShowPilotCarJob::class, ['job' => $hostile->id])->html();
        $this->assertStringNotContainsString('onmouseover="alert', $html);
        $this->assertStringNotContainsString('href="http://x"', $html);

        $safe = $this->createJobForOrganization($org, $customer, ['memo' => 'https://example.test/invoice/1']);
        $html = Livewire::actingAs($manager)->test(ShowPilotCarJob::class, ['job' => $safe->id])->html();
        $this->assertStringContainsString('href="https://example.test/invoice/1" rel="noopener noreferrer"', $html);
    }
}
