<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Is the queue actually being worked? (TASK-469)
 *
 * `queue:health` used to print "No worker process found" and then report
 * HEALTHY, because only a backlog over 100 or a non-empty failed_jobs table
 * counted. A dead worker is the outage that matters: every driver text,
 * invoice email and log-complete notice waits in the jobs table until
 * someone notices. This puts the judgement in one place so the command, the
 * scheduled alert and the /up endpoint all agree.
 */
class QueueHealthCheck
{
    /** A pending job older than this means nothing is draining the queue. */
    public const STALE_JOB_MINUTES = 5;

    public const BACKLOG_LIMIT = 100;

    /**
     * Test seam: a callable returning the number of worker processes, or
     * null when it cannot be known. Production uses `ps`.
     *
     * @var (callable(): ?int)|null
     */
    public static $workerProbe = null;

    /**
     * @return array{
     *   healthy: bool,
     *   problems: list<string>,
     *   facts: array{
     *     connection: string, driver: string, pending: ?int, oldest_pending_minutes: ?int,
     *     failed: ?int, recent_failed: list<object>, workers: ?int, errors: list<string>
     *   }
     * }
     */
    public function run(bool $probeWorkers = true): array
    {
        $connection = (string) config('queue.default', 'sync');
        $driver = (string) config("queue.connections.{$connection}.driver", $connection);

        $facts = [
            'connection' => $connection,
            'driver' => $driver,
            'pending' => null,
            'oldest_pending_minutes' => null,
            'failed' => null,
            'recent_failed' => [],
            'workers' => null,
            'errors' => [],
        ];
        $problems = [];

        if ($driver === 'database') {
            $table = config("queue.connections.{$connection}.table", 'jobs');

            try {
                $facts['pending'] = DB::table($table)->count();

                $oldest = DB::table($table)->whereNull('reserved_at')->orderBy('created_at')->first();
                if ($oldest) {
                    $facts['oldest_pending_minutes'] = (int) floor((now()->getTimestamp() - (int) $oldest->created_at) / 60);
                }
            } catch (Throwable $e) {
                $facts['errors'][] = 'Cannot read the jobs table: '.$e->getMessage();
                $problems[] = 'The jobs table cannot be read: '.$e->getMessage();
            }

            if ($facts['oldest_pending_minutes'] !== null && $facts['oldest_pending_minutes'] >= self::STALE_JOB_MINUTES) {
                $problems[] = sprintf(
                    'The oldest pending job has waited %d minutes; nothing is draining the queue.',
                    $facts['oldest_pending_minutes']
                );
            }

            if (($facts['pending'] ?? 0) > self::BACKLOG_LIMIT) {
                $problems[] = sprintf('%d jobs are queued; the worker is not keeping up.', $facts['pending']);
            }

            if ($probeWorkers) {
                $facts['workers'] = $this->workerCount();

                if ($facts['workers'] === 0) {
                    $problems[] = 'No queue worker process is running (expected a supervised `php artisan queue:work`).';
                }
            }
        }

        try {
            $facts['failed'] = DB::table('failed_jobs')->count();

            if ($facts['failed'] > 0) {
                $facts['recent_failed'] = DB::table('failed_jobs')
                    ->orderByDesc('failed_at')
                    ->limit(5)
                    ->get(['id', 'queue', 'failed_at', 'exception'])
                    ->map(function ($row) {
                        $row->summary = strtok((string) $row->exception, "\n") ?: '';

                        return $row;
                    })
                    ->all();

                $problems[] = sprintf('%d failed job(s) are waiting in failed_jobs; review with `php artisan queue:failed`.', $facts['failed']);
            }
        } catch (Throwable $e) {
            $facts['errors'][] = 'Cannot read the failed_jobs table: '.$e->getMessage();
        }

        return [
            'healthy' => $problems === [],
            'problems' => $problems,
            'facts' => $facts,
        ];
    }

    /**
     * How many `artisan queue:work` processes are running, or null when the
     * host cannot tell us (no `ps`, as on the Windows dev box).
     */
    public function workerCount(): ?int
    {
        if (static::$workerProbe !== null) {
            return (static::$workerProbe)();
        }

        try {
            $process = new Process(['ps', 'aux']);
            $process->setTimeout(5);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $count = 0;
            foreach (explode("\n", $process->getOutput()) as $line) {
                if (str_contains($line, 'artisan') && str_contains($line, 'queue:work')) {
                    $count++;
                }
            }

            return $count;
        } catch (Throwable) {
            return null;
        }
    }
}
