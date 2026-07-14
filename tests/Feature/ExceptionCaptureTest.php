<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Services\ExceptionCaptureService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ExceptionCaptureTest extends TestCase
{
    use RefreshDatabase;

    private function enable(): void
    {
        config([
            'dispatch.auto_capture.enabled' => true,
            'dispatch.auto_capture.environments' => ['testing'],
        ]);
    }

    private function service(): ExceptionCaptureService
    {
        return app(ExceptionCaptureService::class);
    }

    public function test_uncaught_exception_creates_a_bug_task(): void
    {
        $task = $this->service()->report(new RuntimeException('Undefined variable $totalJobs'));

        $this->assertNotNull($task);
        $this->assertSame('bug', $task->type);
        $this->assertSame('triage', $task->status);
        $this->assertFalse($task->is_public);
        $this->assertNotNull($task->exception_signature);
        $this->assertStringContainsString('Undefined variable $totalJobs', $task->title);
        $this->assertTrue($task->labels->pluck('name')->contains('source:exception'));
        $this->assertSame(1, Task::count());
    }

    public function test_identical_exception_is_deduped_onto_one_task(): void
    {
        $e = new RuntimeException('Something exploded 42 times');

        $first = $this->service()->report($e);
        $second = $this->service()->report($e);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Task::count());

        // The recurrence is recorded as an internal occurrence comment.
        $occurrences = $first->fresh()->comments()->where('event_type', 'exception_occurrence')->get();
        $this->assertCount(1, $occurrences);
        $this->assertTrue((bool) $occurrences->first()->is_internal);
        $this->assertStringContainsString('occurrence #2', $occurrences->first()->body);
    }

    public function test_variable_ids_in_the_message_map_to_one_signature(): void
    {
        // Same logical error thrown from the same site, different id — the
        // signature's message normalization collapses the number. Constructing
        // both on one physical line (the loop body) holds the top stack frame
        // constant, as it would be for a real recurrence.
        $signatures = [];
        foreach (['No query results for model [Job] 41', 'No query results for model [Job] 999'] as $message) {
            $signatures[] = $this->service()->signature(new RuntimeException($message));
        }

        $this->assertSame($signatures[0], $signatures[1]);
    }

    public function test_expected_client_errors_are_ignored(): void
    {
        $service = $this->service();

        $this->assertNull($service->report(new NotFoundHttpException('missing')));      // 404
        $this->assertNull($service->report(new AuthorizationException('nope')));         // 403
        $this->assertNull($service->report(ValidationException::withMessages(['x' => 'bad']))); // 422

        $this->assertSame(0, Task::count());
    }

    public function test_disabled_by_default_creates_nothing(): void
    {
        // No config override — auto_capture defaults off, and even if on, the
        // environments list defaults to production, not testing.
        ExceptionCaptureService::capture(new RuntimeException('should be ignored'));

        $this->assertSame(0, Task::count());
        $this->assertFalse(ExceptionCaptureService::enabled());
    }

    public function test_capture_never_throws_even_if_task_creation_fails(): void
    {
        $this->enable();

        // Force the shared task service to blow up mid-capture.
        $this->mock(\App\Services\DispatchTaskService::class, function ($mock) {
            $mock->shouldReceive('create')->andThrow(new RuntimeException('DB down'));
        });

        // Must not bubble — the guard swallows and logs.
        ExceptionCaptureService::capture(new RuntimeException('original boom'));

        $this->assertSame(0, Task::count());
    }

    public function test_end_to_end_a_500_route_opens_a_task_with_request_context(): void
    {
        $this->enable();

        Route::get('/__throw_for_test', function () {
            throw new RuntimeException('boom via http');
        });

        $this->get('/__throw_for_test')->assertStatus(500);

        $task = Task::where('type', 'bug')->first();
        $this->assertNotNull($task, 'a bug task should have been auto-created');
        $this->assertStringContainsString('boom via http', $task->title);
        $this->assertStringContainsString('__throw_for_test', $task->description);
        $this->assertStringContainsString('| Method | `GET`', $task->description);
    }
}
