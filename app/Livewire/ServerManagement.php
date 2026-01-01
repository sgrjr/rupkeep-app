<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AdminToolsController;
use Illuminate\Http\Request;

class ServerManagement extends Component
{
    public $output = [];
    public $isExecuting = false;
    public $selectedWorkflow = null;

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

    public function render()
    {
        return view('livewire.server-management');
    }
}
