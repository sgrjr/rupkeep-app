<?php

namespace App\Console\Commands;

use App\Listeners\Concerns\SendsNotificationMail;
use App\Mail\UserNotification;
use App\Models\User;
use App\Services\QueueHealthCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Report on the queue and, when scheduled with --notify, tell the super
 * users the moment it needs attention (TASK-469). The judgement lives in
 * {@see QueueHealthCheck}; this prints it, adds the host-level colour
 * (supervisor, worker log) and mails it.
 */
class QueueHealth extends Command
{
    use SendsNotificationMail;

    /** Do not repeat the same alert more often than this. */
    public const REALERT_HOURS = 6;

    public const ALERT_CACHE_KEY = 'queue-health:last-alert';

    protected $signature = 'queue:health
                            {--notify : Email the super users when the queue needs attention (throttled) and when it recovers}
                            {--json : Machine-readable report}';

    protected $description = 'Check queue worker health, configuration, and status; --notify alerts the super users';

    public function handle(QueueHealthCheck $check): int
    {
        $report = $check->run();
        $facts = $report['facts'];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->printReport($report);
        }

        if ($this->option('notify')) {
            $this->notify($report);
        }

        return $report['healthy'] ? self::SUCCESS : self::FAILURE;
    }

    protected function printReport(array $report): void
    {
        $facts = $report['facts'];

        $this->info('=== Queue Health Check ===');
        $this->line('');
        $this->line('Connection: '.$facts['connection'].'  Driver: '.$facts['driver']);

        if ($facts['driver'] === 'database') {
            $this->line('Pending jobs: '.($facts['pending'] ?? 'unknown'));
            $this->line('Oldest pending job: '.($facts['oldest_pending_minutes'] === null ? 'none' : $facts['oldest_pending_minutes'].' minute(s) old'));
            $this->line('Worker processes: '.($facts['workers'] === null ? 'unknown (no `ps` on this host)' : $facts['workers']));
        }

        $this->line('Failed jobs: '.($facts['failed'] ?? 'unknown'));
        foreach ($facts['recent_failed'] as $failed) {
            $this->line(sprintf('  - #%s on %s at %s: %s', $failed->id, $failed->queue ?? 'default', $failed->failed_at ?? 'unknown', $failed->summary));
        }
        foreach ($facts['errors'] as $error) {
            $this->warn($error);
        }

        $this->line('');
        $this->info('=== Supervisor ===');
        foreach ($this->supervisorStatus() as $line) {
            $this->line('  '.$line);
        }

        $this->line('');
        $this->info('=== Worker log (last 10 lines) ===');
        foreach ($this->workerLogTail() as $line) {
            $this->line('  '.$line);
        }

        $this->line('');
        if ($report['healthy']) {
            $this->info('=== Status: HEALTHY ===');
        } else {
            $this->error('=== Status: NEEDS ATTENTION ===');
            foreach ($report['problems'] as $problem) {
                $this->line('  - '.$problem);
            }
            $this->line('');
            $this->line('Fix: sudo supervisorctl status rupkeep-worker; php artisan queue:failed; php artisan queue:retry all');
        }
    }

    /**
     * Mail the super users about a problem, at most once per REALERT_HOURS
     * for the same set of problems, and once more when it clears.
     */
    protected function notify(array $report): void
    {
        $last = Cache::get(self::ALERT_CACHE_KEY);

        if ($report['healthy']) {
            if ($last) {
                $this->mailSuperUsers('Queue recovered', "The queue on ".config('app.url')." is healthy again.\n\nPrevious problems:\n- ".implode("\n- ", $last['problems'] ?? []));
                Cache::forget(self::ALERT_CACHE_KEY);
            }

            return;
        }

        $signature = md5(implode('|', $report['problems']));

        $lastAt = $last ? Carbon::parse($last['at']) : null;

        if ($lastAt && ($last['signature'] ?? null) === $signature && $lastAt->gt(now()->subHours(self::REALERT_HOURS))) {
            $this->line('Alert already sent '.$lastAt->diffForHumans().'; not repeating.');

            return;
        }

        $body = "The queue on ".config('app.url')." needs attention.\n\n- ".implode("\n- ", $report['problems']);

        if ($report['facts']['recent_failed'] !== []) {
            $body .= "\n\nMost recent failures:";
            foreach ($report['facts']['recent_failed'] as $failed) {
                $body .= sprintf("\n- #%s at %s: %s", $failed->id, $failed->failed_at ?? 'unknown', $failed->summary);
            }
        }

        $body .= "\n\nOn the host: sudo supervisorctl status rupkeep-worker; php artisan queue:health; php artisan queue:failed; php artisan queue:retry all";

        $sent = $this->mailSuperUsers('Queue needs attention: '.count($report['problems']).' problem(s)', $body);

        if ($sent > 0) {
            Cache::put(self::ALERT_CACHE_KEY, [
                'signature' => $signature,
                'problems' => $report['problems'],
                'at' => now()->toIso8601String(),
            ], now()->addDays(2));
        }
    }

    /** @return int how many super users were actually sent to */
    protected function mailSuperUsers(string $subject, string $body): int
    {
        $recipients = User::query()
            ->where('is_super', true)
            ->get()
            ->map(fn (User $user) => trim($user->email ?: ''))
            ->filter()
            ->unique();

        if ($recipients->isEmpty()) {
            Log::warning('queue:health --notify: no super user has an email address; nobody was told');
            $this->warn('No super user has an email address; nobody was told.');

            return 0;
        }

        $sent = 0;
        foreach ($recipients as $address) {
            if ($this->mailSafely($address, new UserNotification($body, $subject))) {
                $sent++;
            }
        }

        $this->line(sprintf('Notified %d of %d super user(s): %s', $sent, $recipients->count(), $subject));

        return $sent;
    }

    /** @return list<string> */
    protected function supervisorStatus(): array
    {
        try {
            $process = new Process(['supervisorctl', 'status']);
            $process->setTimeout(5);
            $process->run();

            if (! $process->isSuccessful()) {
                return ['supervisorctl unavailable: '.trim($process->getErrorOutput() ?: 'not installed or needs sudo')];
            }

            $lines = array_values(array_filter(array_map('trim', explode("\n", $process->getOutput()))));

            return $lines ?: ['Supervisor is running but manages no processes'];
        } catch (\Throwable $e) {
            return ['supervisorctl unavailable: '.$e->getMessage()];
        }
    }

    /** @return list<string> */
    protected function workerLogTail(): array
    {
        $path = storage_path('logs/worker.log');

        if (! is_readable($path)) {
            return ['No worker.log yet at '.$path];
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        return array_slice($lines, -10) ?: ['worker.log is empty'];
    }
}
