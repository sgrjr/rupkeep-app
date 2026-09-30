<?php

namespace App\Listeners;

use App\Services\QueueHealthCheck;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Make /up mean something (TASK-469). Laravel's health route proved only
 * that the framework boots; an external uptime ping on it would stay green
 * with the database down and the queue worker dead. Now it answers 503 in
 * either case, which is what a monitor can act on.
 *
 * Deliberately cheap: a database ping and one query on the jobs table. No
 * process probe, no failed-jobs count (failed jobs are a problem, not an
 * outage; queue:health --notify covers them).
 */
class CheckApplicationHealth
{
    public function __construct(private readonly QueueHealthCheck $queue)
    {
    }

    public function handle(DiagnosingHealth $event): void
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');
        } catch (\Throwable $e) {
            throw new HttpException(503, 'Database unavailable: '.$e->getMessage(), $e);
        }

        $report = $this->queue->run(probeWorkers: false);
        $age = $report['facts']['oldest_pending_minutes'];

        if ($age !== null && $age >= QueueHealthCheck::STALE_JOB_MINUTES) {
            throw new HttpException(503, sprintf('Queue is not being drained: oldest pending job is %d minutes old.', $age));
        }
    }
}
