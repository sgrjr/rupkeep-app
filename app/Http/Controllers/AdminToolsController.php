<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

class AdminToolsController extends Controller
{
    /**
     * Command whitelist for security
     */
    private function getAllowedCommands(): array
    {
        return [
            'git_pull' => [
                'type' => 'git',
                'command' => ['git', 'pull'],
                'description' => 'Pull latest code from remote',
            ],
            'git_add_all' => [
                'type' => 'git',
                'command' => ['git', 'add', '.'],
                'description' => 'Stage all changes',
            ],
            'git_commit' => [
                'type' => 'git',
                'command' => ['git', 'commit', '-m', 'server:update'],
                'description' => 'Commit changes with message "server:update"',
            ],
            'git_push' => [
                'type' => 'git',
                'command' => ['git', 'push'],
                'description' => 'Push changes to remote',
            ],
            'artisan_assets_build' => [
                'type' => 'artisan',
                'command' => 'assets:build',
                'description' => 'Build JavaScript assets',
            ],
            'artisan_optimize_clear' => [
                'type' => 'artisan',
                'command' => 'optimize:clear',
                'description' => 'Clear all caches',
            ],
            'artisan_optimize' => [
                'type' => 'artisan',
                'command' => 'optimize',
                'description' => 'Optimize application',
            ],
            'artisan_config_clear' => [
                'type' => 'artisan',
                'command' => 'config:clear',
                'description' => 'Clear config cache',
            ],
            'artisan_cache_clear' => [
                'type' => 'artisan',
                'command' => 'cache:clear',
                'description' => 'Clear application cache',
            ],
            'artisan_view_clear' => [
                'type' => 'artisan',
                'command' => 'view:clear',
                'description' => 'Clear view cache',
            ],
        ];
    }

    /**
     * Workflow definitions
     */
    private function getWorkflows(): array
    {
        return [
            'deploy_update' => [
                'description' => 'Deploy Update (Pull + Build + Optimize)',
                'commands' => ['git_pull', 'artisan_assets_build', 'artisan_optimize_clear', 'artisan_optimize'],
            ],
            'full_deploy' => [
                'description' => 'Full Deploy (Pull + Commit + Push + Build + Optimize)',
                'commands' => ['git_pull', 'git_add_all', 'git_commit', 'git_push', 'artisan_assets_build', 'artisan_optimize'],
            ],
            'clear_all' => [
                'description' => 'Clear All Caches',
                'commands' => ['artisan_optimize_clear', 'artisan_config_clear', 'artisan_cache_clear', 'artisan_view_clear'],
            ],
        ];
    }

    /**
     * Execute a single command
     */
    public function executeCommand(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->is_super) {
            abort(403);
        }

        $commandKey = $request->input('command');
        $allowedCommands = $this->getAllowedCommands();

        if (!isset($allowedCommands[$commandKey])) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid command',
            ], 400);
        }

        $commandDef = $allowedCommands[$commandKey];
        $result = $this->runCommand($commandDef);

        return response()->json([
            'success' => $result['exit_code'] === 0,
            'result' => $result,
        ]);
    }

    /**
     * Execute a workflow (multiple commands in sequence)
     */
    public function executeWorkflow(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->is_super) {
            abort(403);
        }

        $workflowKey = $request->input('workflow');
        $workflows = $this->getWorkflows();

        if (!isset($workflows[$workflowKey])) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid workflow',
            ], 400);
        }

        $workflow = $workflows[$workflowKey];
        $allowedCommands = $this->getAllowedCommands();
        $results = [];

        foreach ($workflow['commands'] as $commandKey) {
            if (!isset($allowedCommands[$commandKey])) {
                $results[] = [
                    'command' => $commandKey,
                    'exit_code' => 1,
                    'stdout' => '',
                    'stderr' => "Command not found: {$commandKey}",
                    'timestamp' => now()->toDateTimeString(),
                ];
                continue;
            }

            $commandDef = $allowedCommands[$commandKey];
            $result = $this->runCommand($commandDef);
            $results[] = $result;

            // Stop on first failure if needed (optional - could continue)
            // if ($result['exit_code'] !== 0) {
            //     break;
            // }
        }

        $allSuccessful = collect($results)->every(fn($r) => $r['exit_code'] === 0);

        return response()->json([
            'success' => $allSuccessful,
            'results' => $results,
        ]);
    }

    /**
     * Run a command and capture full output buffer
     */
    private function runCommand(array $commandDef): array
    {
        $startTime = now();
        $commandString = '';

        if ($commandDef['type'] === 'artisan') {
            // Execute artisan command via Process to capture full output
            $commandString = "php artisan {$commandDef['command']}";
            
            // Use Process to execute artisan command and capture all output
            // Passing null for env allows Process to inherit parent environment variables
            // Laravel will read .env file during bootstrap, so no need to pass env explicitly
            $process = new Process(['php', 'artisan', $commandDef['command']], base_path(), null);
            
            // Set timeout for long-running processes (5 minutes)
            $process->setTimeout(300);
            
            // Run and capture output
            $process->run();
            
            $exitCode = $process->getExitCode();
            $stdout = $process->getOutput();
            $stderr = $process->getErrorOutput();
        } else {
            // Execute shell command
            $cmd = $commandDef['command'];
            $commandString = implode(' ', $cmd);
            
            $process = new Process($cmd, base_path());
            
            // Set timeout for long-running processes (5 minutes)
            $process->setTimeout(300);
            
            // Run and capture output
            $process->run();
            
            $exitCode = $process->getExitCode();
            $stdout = $process->getOutput();
            $stderr = $process->getErrorOutput();
        }

        return [
            'command' => $commandString,
            'exit_code' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'timestamp' => $startTime->toDateTimeString(),
        ];
    }

    /**
     * Get available commands and workflows
     */
    public function getCommands(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->is_super) {
            abort(403);
        }

        return response()->json([
            'commands' => $this->getAllowedCommands(),
            'workflows' => $this->getWorkflows(),
        ]);
    }

    public function updateFromGit(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->is_super) {
            abort(403);
        }

        $commands = [
            ['git', 'fetch', 'origin'],
            ['git', 'reset', '--hard', 'origin/master'],
            ['git', 'clean', '-fd'],
        ];

        $output = [];
        foreach ($commands as $cmd) {
            $process = new Process($cmd, base_path());
            $process->setTimeout(300);
            $process->run();
            $output[] = [
                'command' => implode(' ', $cmd),
                'exit_code' => $process->getExitCode(),
                'stdout' => $process->getOutput(),
                'stderr' => $process->getErrorOutput(),
            ];
            if (!$process->isSuccessful()) {
                session()->flash('error', 'Git update failed on: '.implode(' ', $cmd));
                return back()->with('git_output', $output);
            }
        }

        session()->flash('success', 'Successfully pulled latest code from master.');
        return back()->with('git_output', $output);
    }
}

