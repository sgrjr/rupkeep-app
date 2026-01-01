<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;

class QueueHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check queue worker health, configuration, and status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $output = [];
        $output[] = "=== Queue Health Check ===";
        $output[] = "";

        // Queue Configuration
        $output[] = "=== Queue Configuration ===";
        $defaultConnection = config('queue.default', 'sync');
        $output[] = "Default Queue Connection: " . $defaultConnection;
        
        $queueConfig = config("queue.connections.{$defaultConnection}", []);
        if (!empty($queueConfig)) {
            $driver = $queueConfig['driver'] ?? 'unknown';
            $output[] = "Queue Driver: " . $driver;
            
            if ($driver === 'database') {
                $connection = $queueConfig['connection'] ?? null;
                $table = $queueConfig['table'] ?? 'jobs';
                $queue = $queueConfig['queue'] ?? 'default';
                $output[] = "Database Connection: " . ($connection ?? 'default');
                $output[] = "Jobs Table: " . $table;
                $output[] = "Queue Name: " . $queue;
            } elseif ($driver === 'redis') {
                $connection = $queueConfig['connection'] ?? 'default';
                $queue = $queueConfig['queue'] ?? 'default';
                $output[] = "Redis Connection: " . $connection;
                $output[] = "Queue Name: " . $queue;
            }
        }
        $output[] = "";

        // Check Queue Connection Status
        $output[] = "=== Queue Connection Status ===";
        try {
            $size = Queue::size();
            $output[] = "Queue Size (Pending Jobs): " . number_format($size);
        } catch (\Exception $e) {
            $output[] = "ERROR: Cannot connect to queue: " . $e->getMessage();
            $output[] = "";
            $output[] = "Check your queue configuration in config/queue.php";
            $output[] = "and verify your queue driver (database/redis) is properly configured.";
        }
        $output[] = "";

        // Database Queue Statistics
        if ($defaultConnection === 'database' || ($queueConfig['driver'] ?? '') === 'database') {
            $output[] = "=== Database Queue Statistics ===";
            try {
                $jobsTable = $queueConfig['table'] ?? 'jobs';
                $pendingJobs = DB::table($jobsTable)->count();
                $output[] = "Pending Jobs: " . number_format($pendingJobs);
                
                // Get oldest pending job
                $oldestJob = DB::table($jobsTable)->orderBy('id', 'asc')->first();
                if ($oldestJob) {
                    $output[] = "Oldest Pending Job ID: " . $oldestJob->id;
                    $output[] = "Oldest Job Queue: " . ($oldestJob->queue ?? 'default');
                    if (isset($oldestJob->created_at)) {
                        $output[] = "Oldest Job Created: " . $oldestJob->created_at;
                    }
                } else {
                    $output[] = "No pending jobs";
                }
            } catch (\Exception $e) {
                $output[] = "ERROR: Cannot access jobs table: " . $e->getMessage();
                $output[] = "Table: " . ($jobsTable ?? 'jobs');
            }
            $output[] = "";
        }

        // Failed Jobs
        $output[] = "=== Failed Jobs ===";
        try {
            $failedJobsCount = DB::table('failed_jobs')->count();
            $output[] = "Total Failed Jobs: " . number_format($failedJobsCount);
            
            if ($failedJobsCount > 0) {
                $recentFailed = DB::table('failed_jobs')
                    ->orderBy('failed_at', 'desc')
                    ->limit(5)
                    ->get();
                
                $output[] = "";
                $output[] = "Recent Failed Jobs (last 5):";
                foreach ($recentFailed as $failed) {
                    $output[] = "  - ID: {$failed->id}, Queue: " . ($failed->queue ?? 'default') . 
                                ", Failed: " . ($failed->failed_at ?? 'unknown');
                }
            }
        } catch (\Exception $e) {
            $output[] = "ERROR: Cannot access failed_jobs table: " . $e->getMessage();
        }
        $output[] = "";

        // Check for Running Queue Workers
        $output[] = "=== Queue Worker Process Status ===";
        try {
            // Check for artisan queue:work processes
            $process = new Process(['ps', 'aux'], base_path());
            $process->run();
            $psOutput = $process->getOutput();
            
            $workerProcesses = [];
            $lines = explode("\n", $psOutput);
            foreach ($lines as $line) {
                if (str_contains($line, 'artisan') && str_contains($line, 'queue:work')) {
                    $workerProcesses[] = $line;
                }
            }
            
            if (empty($workerProcesses)) {
                $output[] = "WARNING: No queue worker processes detected";
                $output[] = "";
                $output[] = "Queue workers should be running via Supervisor or manually.";
                $output[] = "To start a worker manually: php artisan queue:work";
                $output[] = "Or check Supervisor status: sudo supervisorctl status";
            } else {
                $output[] = "Found " . count($workerProcesses) . " queue worker process(es):";
                foreach ($workerProcesses as $idx => $proc) {
                    // Extract PID and other info
                    $parts = preg_split('/\s+/', trim($proc));
                    $pid = $parts[1] ?? 'unknown';
                    $cpu = $parts[2] ?? 'unknown';
                    $mem = $parts[3] ?? 'unknown';
                    $output[] = "  Worker #" . ($idx + 1) . ": PID $pid, CPU: {$cpu}%, Mem: {$mem}%";
                }
            }
        } catch (\Exception $e) {
            $output[] = "WARNING: Cannot check process status: " . $e->getMessage();
            $output[] = "This is normal if 'ps' command is not available.";
        }
        $output[] = "";

        // Check Supervisor Status (if available)
        $output[] = "=== Supervisor Status ===";
        try {
            $supervisorProcess = new Process(['supervisorctl', 'status'], base_path());
            $supervisorProcess->setTimeout(5);
            $supervisorProcess->run();
            
            if ($supervisorProcess->isSuccessful()) {
                $supervisorOutput = $supervisorProcess->getOutput();
                if (!empty(trim($supervisorOutput))) {
                    $output[] = "Supervisor processes:";
                    $lines = explode("\n", trim($supervisorOutput));
                    foreach ($lines as $line) {
                        if (!empty(trim($line))) {
                            $output[] = "  " . trim($line);
                        }
                    }
                } else {
                    $output[] = "Supervisor is running but no processes found";
                }
            } else {
                $output[] = "Supervisor command failed (may require sudo or not installed)";
                $output[] = "Error: " . $supervisorProcess->getErrorOutput();
            }
        } catch (\Exception $e) {
            $output[] = "Supervisor status check failed: " . $e->getMessage();
            $output[] = "This is normal if Supervisor is not installed or requires sudo.";
        }
        $output[] = "";

        // Recommendations
        $output[] = "=== Recommendations ===";
        $hasIssues = false;
        
        try {
            $queueSize = Queue::size();
            if ($queueSize > 100) {
                $output[] = "⚠ High queue size detected ({$queueSize} jobs). Consider:";
                $output[] = "  - Starting additional queue workers";
                $output[] = "  - Checking for stuck/failing jobs";
                $hasIssues = true;
            }
        } catch (\Exception $e) {
            // Already logged above
        }

        try {
            $failedCount = DB::table('failed_jobs')->count();
            if ($failedCount > 0) {
                $output[] = "⚠ Failed jobs detected ({$failedCount} total). Review with:";
                $output[] = "  - php artisan queue:failed";
                $output[] = "  - php artisan queue:retry all (to retry)";
                $hasIssues = true;
            }
        } catch (\Exception $e) {
            // Already logged above
        }

        if (!$hasIssues) {
            $output[] = "✓ No obvious issues detected";
        }
        $output[] = "";

        $output[] = "=== Status: " . ($hasIssues ? "NEEDS ATTENTION" : "HEALTHY") . " ===";
        
        $this->info(implode("\n", $output));
        return $hasIssues ? 1 : 0;
    }
}
