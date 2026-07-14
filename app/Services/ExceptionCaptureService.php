<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Turns an uncaught (500-level) application exception into a Dispatch `bug`
 * task in `triage`, so runtime failures land in the backlog instead of only in
 * the logs. Recurring identical exceptions are deduped onto the existing open
 * task. Gated by config so only production auto-captures (TASK-337).
 */
class ExceptionCaptureService
{
    /** Re-entry guard: a failure while capturing must not capture itself. */
    private static bool $capturing = false;

    /** Expected client-side failures that are noise, not bugs to follow up. */
    private const IGNORED = [
        AuthenticationException::class,   // 401
        AuthorizationException::class,    // 403
        ValidationException::class,       // 422
        TokenMismatchException::class,    // 419
        ModelNotFoundException::class,    // 404 (route-model binding)
    ];

    public function __construct(private DispatchTaskService $tasks)
    {
    }

    /**
     * Entry point for the framework's report() hook. Never throws.
     */
    public static function capture(Throwable $e): void
    {
        if (self::$capturing || ! self::enabled()) {
            return;
        }

        self::$capturing = true;

        try {
            app(self::class)->report($e);
        } catch (Throwable $inner) {
            // Never let the capture path turn one 500 into two — log and move on.
            Log::warning('Dispatch exception capture failed', [
                'capture_error' => $inner->getMessage(),
                'original_error' => $e->getMessage(),
            ]);
        } finally {
            self::$capturing = false;
        }
    }

    /**
     * Whether auto-capture is switched on for the current environment.
     */
    public static function enabled(): bool
    {
        return (bool) config('dispatch.auto_capture.enabled')
            && in_array(app()->environment(), (array) config('dispatch.auto_capture.environments', []), true);
    }

    /**
     * Create (or bump) the Dispatch task for this exception. Returns the task,
     * or null when the exception is ignored.
     */
    public function report(Throwable $e): ?Task
    {
        if ($this->shouldIgnore($e)) {
            return null;
        }

        $signature = $this->signature($e);

        // Dedupe against a still-open task for the same signature. A previously
        // resolved (done/declined) task is left alone — a recurrence there is a
        // regression that deserves a fresh task.
        $existing = Task::where('exception_signature', $signature)
            ->whereNotIn('status', ['done', 'declined'])
            ->orderBy('id')
            ->first();

        if ($existing) {
            $this->recordOccurrence($existing, $e);

            return $existing;
        }

        return $this->tasks->create([
            'title'               => $this->title($e),
            'description'         => $this->description($e),
            'type'                => 'bug',
            'priority'            => 'medium',
            'status'              => 'triage',
            'is_public'           => false,
            'exception_signature' => $signature,
        ], [(string) config('dispatch.auto_capture.label', 'source:exception')]);
    }

    /**
     * Expected client errors (4xx) are not bugs — skip them.
     */
    public function shouldIgnore(Throwable $e): bool
    {
        foreach (self::IGNORED as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }

        // Any HTTP exception below 500 (404, 403, 405, 419, 429, …) is a client
        // problem, not a server bug.
        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            return true;
        }

        return false;
    }

    /**
     * Stable fingerprint: class + normalized message + top application frame.
     * Volatile bits (ids, hashes) are collapsed so the same logical failure
     * maps to one signature regardless of the specific value that triggered it.
     */
    public function signature(Throwable $e): string
    {
        $raw = get_class($e)
            . '|' . $this->normalizeMessage($e->getMessage())
            . '|' . $this->topAppFrame($e);

        return sha1($raw);
    }

    private function normalizeMessage(string $message): string
    {
        $message = preg_replace('/0x[0-9a-f]+/i', '#', $message);
        $message = preg_replace('/\d+/', '#', $message);
        $message = preg_replace('/\s+/', ' ', $message);

        return trim(Str::limit((string) $message, 200, ''));
    }

    /**
     * First stack frame inside the app (not vendor). Falls back to where the
     * exception was thrown.
     */
    private function topAppFrame(Throwable $e): string
    {
        $base = base_path();

        foreach ($e->getTrace() as $frame) {
            $file = $frame['file'] ?? null;
            if (! $file || ! str_starts_with($file, $base)) {
                continue;
            }
            if (str_contains($file, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
                continue;
            }

            return $this->relative($file, $base) . ':' . ($frame['line'] ?? 0);
        }

        return $this->relative($e->getFile(), $base) . ':' . $e->getLine();
    }

    private function relative(string $file, string $base): string
    {
        if (str_starts_with($file, $base)) {
            $file = ltrim(substr($file, strlen($base)), '\\/');
        } else {
            $file = basename($file);
        }

        return str_replace('\\', '/', $file);
    }

    private function title(Throwable $e): string
    {
        $class = class_basename($e);
        $message = trim((string) preg_replace('/\s+/', ' ', $e->getMessage()));

        $title = $message !== '' ? "{$class}: {$message}" : $class;

        return Str::limit($title, 200, '…');
    }

    private function description(Throwable $e): string
    {
        $lines = [
            '**Auto-captured from an uncaught exception.** Follow up: reproduce, fix, add a regression test.',
            '',
            '| Field | Value |',
            '|---|---|',
            '| Exception | `' . get_class($e) . '` |',
            '| Message | ' . $this->cell($e->getMessage()) . ' |',
            '| Location | `' . $this->relative($e->getFile(), base_path()) . ':' . $e->getLine() . '` |',
        ];

        if ($request = $this->currentRequest()) {
            $lines[] = '| Method | `' . $request->method() . '` |';
            $lines[] = '| URL | `' . $this->cell($this->safeUrl($request)) . '` |';
            if ($route = optional($request->route())->getName()) {
                $lines[] = '| Route | `' . $this->cell($route) . '` |';
            }
        }

        if ($user = Auth::user()) {
            $lines[] = '| User | #' . $user->id . ' · ' . $this->cell((string) ($user->organization_role ?? 'n/a')) . ' |';
        }

        $lines[] = '';
        $lines[] = '## Stack (top frames)';
        $lines[] = '```';
        $lines[] = $this->topFrames($e);
        $lines[] = '```';

        return implode("\n", $lines);
    }

    private function recordOccurrence(Task $task, Throwable $e): void
    {
        // Creation counts as occurrence #1; each recorded comment is a later one.
        $number = $task->comments()->where('event_type', 'exception_occurrence')->count() + 2;

        $task->comments()->create([
            'user_id'     => Auth::id(),
            'body'        => "Recurred (occurrence #{$number}) — " . Str::limit(trim($e->getMessage()), 140, '…'),
            'event_type'  => 'exception_occurrence',
            'meta'        => ['url' => $this->currentRequest()?->fullUrl()],
            'is_internal' => true,
        ]);
    }

    private function currentRequest(): ?Request
    {
        try {
            if (! app()->bound('request')) {
                return null;
            }
            $request = app('request');

            return $request instanceof Request && $request->path() !== '' ? $request : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The request URL with sensitive query values redacted. The request body is
     * deliberately never captured, so no form payloads are stored.
     */
    private function safeUrl(Request $request): string
    {
        $query = $request->query();

        array_walk_recursive($query, function (&$value, $key) {
            if (preg_match('/pass|token|secret|api[_-]?key|authorization|otp|(^|_)code$/i', (string) $key)) {
                $value = '[redacted]';
            }
        });

        $qs = http_build_query($query);

        return $request->url() . ($qs !== '' ? '?' . $qs : '');
    }

    private function topFrames(Throwable $e, int $limit = 12): string
    {
        $lines = explode("\n", $e->getTraceAsString());
        $slice = array_slice($lines, 0, $limit);

        if (count($lines) > $limit) {
            $slice[] = '... (' . (count($lines) - $limit) . ' more frames)';
        }

        return implode("\n", $slice);
    }

    private function cell(string $value): string
    {
        return str_replace(['|', "\r", "\n"], ['\|', ' ', ' '], trim($value));
    }
}
