<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AdminToolsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServerManagement extends Component
{
    public $output = [];
    public $isExecuting = false;
    public $selectedWorkflow = null;
    
    public $queueJobsLoaded = false;
    public $queueJobs = [];
    public $failedJobs = [];
    public $queueStats = [
        'total_jobs' => 0,
        'total_failed' => 0,
    ];

    public function mount()
    {
        if (!Auth::user() || !Auth::user()->is_super) {
            abort(403);
        }
        
        // Increase timeout for this request
        set_time_limit(300);
    }

    public function executeCommand($commandKey)
    {
        if ($this->isExecuting) {
            return;
        }

        $this->isExecuting = true;
        $this->output = [];

        try {
            $controller = app(AdminToolsController::class);
            $request = Request::create(route('admin.tools.execute-command'), 'POST', [
                'command' => $commandKey,
            ]);
            $request->setUserResolver(fn() => Auth::user());
            $request->headers->set('X-Requested-With', 'XMLHttpRequest');
            
            $response = $controller->executeCommand($request);
            $data = json_decode($response->getContent(), true);
            
            if ($data && isset($data['result'])) {
                $this->output[] = $data['result'];
            } else {
                $this->output[] = [
                    'command' => $commandKey,
                    'exit_code' => 1,
                    'stdout' => '',
                    'stderr' => $data['error'] ?? 'Unknown error',
                    'timestamp' => now()->toDateTimeString(),
                ];
            }
        } catch (\Exception $e) {
            $this->output[] = [
                'command' => $commandKey,
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => 'Error: ' . $e->getMessage(),
                'timestamp' => now()->toDateTimeString(),
            ];
        } finally {
            $this->isExecuting = false;
        }
    }

    public function executeWorkflow($workflowKey)
    {
        if ($this->isExecuting) {
            return;
        }

        $this->isExecuting = true;
        $this->output = [];
        $this->selectedWorkflow = $workflowKey;

        try {
            $controller = app(AdminToolsController::class);
            $request = Request::create(route('admin.tools.execute-workflow'), 'POST', [
                'workflow' => $workflowKey,
            ]);
            $request->setUserResolver(fn() => Auth::user());
            $request->headers->set('X-Requested-With', 'XMLHttpRequest');
            
            $response = $controller->executeWorkflow($request);
            $data = json_decode($response->getContent(), true);
            
            if ($data && isset($data['results'])) {
                $this->output = $data['results'];
            } else {
                $this->output[] = [
                    'command' => $workflowKey,
                    'exit_code' => 1,
                    'stdout' => '',
                    'stderr' => $data['error'] ?? 'Unknown error',
                    'timestamp' => now()->toDateTimeString(),
                ];
            }
        } catch (\Exception $e) {
            $this->output[] = [
                'command' => $workflowKey,
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => 'Error: ' . $e->getMessage(),
                'timestamp' => now()->toDateTimeString(),
            ];
        } finally {
            $this->isExecuting = false;
            $this->selectedWorkflow = null;
        }
    }

    public function clearOutput()
    {
        $this->output = [];
    }

    public function loadQueueJobs()
    {
        try {
            // Load pending jobs from jobs table
            $this->queueJobs = DB::table('jobs')
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($job) {
                    $payload = json_decode($job->payload, true);
                    return [
                        'id' => $job->id,
                        'queue' => $job->queue,
                        'attempts' => $job->attempts,
                        'reserved_at' => $job->reserved_at ? date('Y-m-d H:i:s', $job->reserved_at) : null,
                        'available_at' => date('Y-m-d H:i:s', $job->available_at),
                        'created_at' => date('Y-m-d H:i:s', $job->created_at),
                        'display_name' => $payload['displayName'] ?? 'Unknown Job',
                        'job' => $payload['job'] ?? null,
                    ];
                })
                ->toArray();

            // Load failed jobs
            $this->failedJobs = DB::table('failed_jobs')
                ->orderBy('failed_at', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($job) {
                    $payload = json_decode($job->payload, true);
                    $exception = json_decode($job->exception, true);
                    return [
                        'id' => $job->id,
                        'uuid' => $job->uuid,
                        'queue' => $job->queue,
                        'connection' => $job->connection,
                        'failed_at' => $job->failed_at,
                        'display_name' => $payload['displayName'] ?? 'Unknown Job',
                        'exception' => $exception['message'] ?? $job->exception,
                        'exception_class' => $exception['exception'] ?? 'Unknown',
                        'exception_trace' => $exception['trace'] ?? [],
                    ];
                })
                ->toArray();

            // Get statistics
            $this->queueStats = [
                'total_jobs' => DB::table('jobs')->count(),
                'total_failed' => DB::table('failed_jobs')->count(),
            ];

            $this->queueJobsLoaded = true;
        } catch (\Exception $e) {
            // If tables don't exist or there's an error, set empty arrays
            $this->queueJobs = [];
            $this->failedJobs = [];
            $this->queueStats = [
                'total_jobs' => 0,
                'total_failed' => 0,
            ];
            $this->queueJobsLoaded = true;
        }
    }

    public function render()
    {
        return view('livewire.server-management');
    }
}
