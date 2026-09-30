<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

/**
 * The in-app deploy and the other server buttons (TASK-470).
 *
 * Every command is whitelisted here. A workflow stops at the first failure
 * and always ends by bringing the site back up; one lock keeps two tabs from
 * deploying at once; the destructive buttons need a typed confirmation; and
 * git talks to GitHub over a pinned host key rather than no host key at all.
 */
class AdminToolsController extends Controller
{
    public const LOCK_KEY = 'admin-tools:server-command';

    /** Longer than the slowest honest deploy; a crashed one expires on its own. */
    public const LOCK_SECONDS = 900;

    public const PROCESS_TIMEOUT = 600;

    public const ROLLBACK_CONFIRMATION = 'ROLLBACK';

    /**
     * Command whitelist for security
     */
    private function getAllowedCommands(): array
    {
        return [
            // Fast-forward only: the server never merges, so a pull that would
            // have to merge is a sign something was edited on the host.
            'git_pull' => [
                'type' => 'git',
                'command' => ['git', 'pull', '--ff-only'],
                'description' => 'Pull latest code from GitHub (fast-forward only)',
            ],
            'composer_install' => [
                'type' => 'shell',
                'command' => ['composer', 'install', '--no-dev', '--optimize-autoloader', '--no-interaction'],
                'description' => 'Install PHP dependencies from composer.lock',
            ],
            'npm_ci' => [
                'type' => 'shell',
                'command' => ['npm', 'ci', '--no-audit', '--no-fund'],
                'description' => 'Install JavaScript dependencies from package-lock.json',
            ],
            // Read-only. Explains a dashboard invoice count that disagrees with
            // the jobs list, which needs answering on the machine holding the
            // data rather than guessed at from a laptop.
            'artisan_jobs_diagnose' => [
                'type' => 'artisan',
                'command' => 'jobs:diagnose',
                'description' => 'Diagnose jobs missing from the jobs list (read-only)',
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
            'artisan_migrate' => [
                'type' => 'artisan',
                'command' => 'migrate --force',
                'description' => 'Run database migrations',
            ],
            // One step, never a whole batch: a batch can carry is_super or the
            // Dispatch tables, and one migration has an empty down().
            'artisan_migrate_rollback' => [
                'type' => 'artisan',
                'command' => 'migrate:rollback --force --step=1',
                'description' => 'Roll back the most recent migration (one step)',
                'confirm' => self::ROLLBACK_CONFIRMATION,
            ],
            'artisan_db_dump' => [
                'type' => 'artisan',
                'command' => 'db:dump',
                'description' => 'Dump the database to storage/app/private/backups/db',
            ],
            'artisan_queue_restart' => [
                'type' => 'artisan',
                'command' => 'queue:restart',
                'description' => 'Tell the queue worker to reload the new code after its current job',
            ],
            'artisan_down' => [
                'type' => 'artisan',
                'command' => 'down --retry=30',
                'description' => 'Put the site into maintenance mode',
            ],
            'artisan_up' => [
                'type' => 'artisan',
                'command' => 'up',
                'description' => 'Bring the site out of maintenance mode',
            ],
            'artisan_redis_health' => [
                'type' => 'artisan',
                'command' => 'redis:health',
                'description' => 'Check Redis server health and status',
            ],
            'artisan_queue_health' => [
                'type' => 'artisan',
                'command' => 'queue:health',
                'description' => 'Check queue worker health and status',
            ],
            'artisan_env_check' => [
                'type' => 'artisan',
                'command' => 'env:check',
                'description' => 'Check the production .env against the go-live checklist (never prints secrets)',
            ],
        ];
    }

    /**
     * Workflow definitions. `ensure` names the step that must run last no
     * matter where the sequence stopped.
     */
    private function getWorkflows(): array
    {
        return [
            'deploy_update' => [
                'description' => 'Deploy (down, dump DB, pull, composer install, npm ci, build, migrate, optimize, queue:restart, up)',
                'commands' => [
                    'artisan_down',
                    'artisan_db_dump',
                    'git_pull',
                    'composer_install',
                    'npm_ci',
                    'artisan_assets_build',
                    'artisan_migrate',
                    'artisan_optimize_clear',
                    'artisan_optimize',
                    'artisan_queue_restart',
                    'artisan_up',
                ],
                'ensure' => 'artisan_up',
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
        if (!$user || !$user->isSuper()) {
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

        // A destructive button needs its word typed, not just clicked.
        if (isset($commandDef['confirm']) && (string) $request->input('confirmation') !== $commandDef['confirm']) {
            return response()->json([
                'success' => false,
                'error' => sprintf('Type %s to confirm this command. Nothing was run.', $commandDef['confirm']),
            ], 422);
        }

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        if (! $lock->get()) {
            return response()->json([
                'success' => false,
                'error' => 'Another server command is still running. Wait for it to finish.',
            ], 409);
        }

        try {
            $result = $this->runCommand($commandDef);
        } finally {
            $lock->release();
        }

        return response()->json([
            'success' => $result['exit_code'] === 0,
            'result' => $result,
        ]);
    }

    /**
     * Execute a workflow: the steps in order, stopping at the first failure,
     * then the `ensure` step whatever happened.
     */
    public function executeWorkflow(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isSuper()) {
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

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        if (! $lock->get()) {
            return response()->json([
                'success' => false,
                'error' => 'Another server command is still running. Wait for it to finish.',
            ], 409);
        }

        $workflow = $workflows[$workflowKey];
        $allowedCommands = $this->getAllowedCommands();
        $results = [];
        $stoppedAt = null;

        try {
            foreach ($workflow['commands'] as $commandKey) {
                if (!isset($allowedCommands[$commandKey])) {
                    $results[] = [
                        'command' => $commandKey,
                        'exit_code' => 1,
                        'stdout' => '',
                        'stderr' => "Command not found: {$commandKey}",
                        'timestamp' => now()->toDateTimeString(),
                    ];
                    $stoppedAt = $commandKey;
                    break;
                }

                $result = $this->runCommand($allowedCommands[$commandKey]);
                $results[] = $result;

                // A failed pull must not be followed by a build of the old code
                // and a cache of the new config.
                if ($result['exit_code'] !== 0) {
                    $stoppedAt = $commandKey;
                    break;
                }
            }
        } finally {
            // The site went down as the first step; whatever happened, it comes back.
            $ensure = $workflow['ensure'] ?? null;
            if ($ensure && $stoppedAt !== null && $stoppedAt !== $ensure && isset($allowedCommands[$ensure])) {
                $results[] = $this->runCommand($allowedCommands[$ensure]);
            }

            $lock->release();
        }

        return response()->json([
            'success' => $stoppedAt === null,
            'stopped_at' => $stoppedAt,
            'results' => $results,
        ]);
    }

    /**
     * Run a command and capture full output buffer
     */
    protected function runCommand(array $commandDef): array
    {
        $startTime = now();

        if ($commandDef['type'] === 'artisan') {
            $commandString = "php artisan {$commandDef['command']}";
            $commandParts = explode(' ', $commandDef['command']);

            // Guard (TASK-338): never attempt to run an artisan command that
            // isn't registered. The historical `queue:status` typo surfaced as
            // an uncaught CommandNotFoundException. Fail fast with a clean,
            // handled result instead of shelling out to a doomed subprocess.
            $baseCommand = $commandParts[0] ?? '';
            if (! $this->artisanCommandExists($baseCommand)) {
                return [
                    'command' => $commandString,
                    'exit_code' => 1,
                    'stdout' => '',
                    'stderr' => "Command \"{$baseCommand}\" is not defined.",
                    'timestamp' => $startTime->toDateTimeString(),
                ];
            }

            $process = new Process(array_merge(['php', 'artisan'], $commandParts), base_path(), null);
            $env = null;
        } else {
            $cmd = $commandDef['command'];
            $commandString = implode(' ', $cmd);
            $process = new Process($cmd, base_path());
            $env = $commandDef['type'] === 'git' ? $this->gitEnvironment() : null;
        }

        $process->setTimeout(self::PROCESS_TIMEOUT);
        $process->run(null, $env);

        return [
            'command' => $commandString,
            'exit_code' => $process->getExitCode(),
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
            'timestamp' => $startTime->toDateTimeString(),
        ];
    }

    /**
     * Git over SSH trusts exactly the GitHub host keys committed in
     * resources/ssh/github_known_hosts. This used to disable host-key
     * checking altogether, which is the one thing SSH is for.
     *
     * @return array<string, string>
     */
    protected function gitEnvironment(): array
    {
        return [
            'GIT_SSH_COMMAND' => sprintf(
                'ssh -o UserKnownHostsFile=%s -o StrictHostKeyChecking=yes',
                escapeshellarg(self::knownHostsPath())
            ),
        ];
    }

    public static function knownHostsPath(): string
    {
        return base_path('resources/ssh/github_known_hosts');
    }

    /**
     * Determine whether an artisan command is actually registered.
     */
    protected function artisanCommandExists(string $name): bool
    {
        if ($name === '') {
            return false;
        }

        return array_key_exists($name, Artisan::all());
    }

    /**
     * Get available commands and workflows
     */
    public function getCommands(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isSuper()) {
            abort(403);
        }

        return response()->json([
            'commands' => $this->getAllowedCommands(),
            'workflows' => $this->getWorkflows(),
        ]);
    }

    /**
     * The dashboard's reset button: discard whatever is on the server and
     * match GitHub's master exactly. Needs the form's confirmation.
     */
    public function updateFromGit(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isSuper()) {
            abort(403);
        }

        if (! $request->boolean('confirmed')) {
            session()->flash('error', 'Resetting the server to GitHub needs confirmation. Nothing was run.');

            return back();
        }

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        if (! $lock->get()) {
            session()->flash('error', 'Another server command is still running. Wait for it to finish.');

            return back();
        }

        $steps = [
            ['type' => 'git', 'command' => ['git', 'fetch', 'origin']],
            ['type' => 'git', 'command' => ['git', 'reset', '--hard', 'origin/master']],
            ['type' => 'git', 'command' => ['git', 'clean', '-fd']],
        ];

        $output = [];

        try {
            foreach ($steps as $step) {
                $result = $this->runCommand($step);
                $output[] = $result;

                if ($result['exit_code'] !== 0) {
                    session()->flash('error', 'Git reset failed on: '.$result['command']);

                    return back()->with('git_output', $output);
                }
            }
        } finally {
            $lock->release();
        }

        session()->flash('success', 'Server reset to GitHub master. Run Deploy on the Server Management page to install, build and restart.');

        return back()->with('git_output', $output);
    }
}
