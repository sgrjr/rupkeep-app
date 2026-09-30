<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminToolsController;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * TASK-470. The in-app deploy carried on after a failed pull, never ran
 * composer or npm ci or queue:restart, rebuilt assets in place while live,
 * rolled back whole migration batches on one unconfirmed click, and talked
 * to GitHub with host-key checking switched off.
 */
class InAppDeployTest extends TestCase
{
    use RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();

        $organization = Organization::factory()->create();
        $this->super = User::factory()->superUser()->create(['organization_id' => $organization->id]);
    }

    /**
     * The controller with the shell replaced by a script: `$exitCodes` maps a
     * substring of the command line to the exit code it should return.
     */
    private function controller(array $exitCodes = []): AdminToolsController
    {
        return new class($exitCodes) extends AdminToolsController {
            public array $ran = [];

            public function __construct(private array $exitCodes)
            {
            }

            protected function runCommand(array $commandDef): array
            {
                $command = is_array($commandDef['command'])
                    ? implode(' ', $commandDef['command'])
                    : 'php artisan '.$commandDef['command'];
                $this->ran[] = $command;

                $exit = 0;
                foreach ($this->exitCodes as $needle => $code) {
                    if (str_contains($command, $needle)) {
                        $exit = $code;
                    }
                }

                return [
                    'command' => $command,
                    'exit_code' => $exit,
                    'stdout' => '',
                    'stderr' => $exit === 0 ? '' : 'boom',
                    'timestamp' => now()->toDateTimeString(),
                ];
            }

            public function gitEnvironmentPublic(): array
            {
                return $this->gitEnvironment();
            }
        };
    }

    private function request(string $route, array $input): Request
    {
        $request = Request::create(route($route), 'POST', $input);
        $request->setUserResolver(fn () => $this->super);
        $this->actingAs($this->super);

        return $request;
    }

    // -----------------------------------------------------------------
    // The deploy sequence
    // -----------------------------------------------------------------

    public function test_a_clean_deploy_runs_the_whole_sequence_in_order(): void
    {
        $controller = $this->controller();

        $response = $controller->executeWorkflow($this->request('admin.tools.execute-workflow', ['workflow' => 'deploy_update']));
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertNull($data['stopped_at']);
        $this->assertSame([
            'php artisan down --retry=30',
            'php artisan db:dump',
            'git pull --ff-only',
            'composer install --no-dev --optimize-autoloader --no-interaction',
            'npm ci --no-audit --no-fund',
            'php artisan assets:build',
            'php artisan migrate --force',
            'php artisan optimize:clear',
            'php artisan optimize',
            'php artisan queue:restart',
            'php artisan up',
        ], $controller->ran);
    }

    public function test_a_failed_pull_stops_the_deploy_and_still_brings_the_site_up(): void
    {
        $controller = $this->controller(['git pull' => 1]);

        $data = $controller->executeWorkflow($this->request('admin.tools.execute-workflow', ['workflow' => 'deploy_update']))->getData(true);

        $this->assertFalse($data['success']);
        $this->assertSame('git_pull', $data['stopped_at']);
        $this->assertSame([
            'php artisan down --retry=30',
            'php artisan db:dump',
            'git pull --ff-only',
            'php artisan up',
        ], $controller->ran, 'nothing is built or cached on top of a failed pull, and maintenance mode ends');
    }

    public function test_a_failed_dump_stops_before_anything_changes(): void
    {
        $controller = $this->controller(['db:dump' => 1]);

        $data = $controller->executeWorkflow($this->request('admin.tools.execute-workflow', ['workflow' => 'deploy_update']))->getData(true);

        $this->assertSame('artisan_db_dump', $data['stopped_at']);
        $this->assertNotContains('git pull --ff-only', $controller->ran);
        $this->assertSame('php artisan up', end($controller->ran));
    }

    public function test_the_server_no_longer_commits_or_pushes(): void
    {
        $commands = $this->controller()->getCommands($this->request('admin.tools.execute-command', []))->getData(true);

        foreach (['git_add_all', 'git_commit', 'git_push'] as $gone) {
            $this->assertArrayNotHasKey($gone, $commands['commands']);
        }
        $this->assertArrayNotHasKey('full_deploy', $commands['workflows']);

        foreach (['composer_install', 'npm_ci', 'artisan_queue_restart', 'artisan_db_dump', 'artisan_down', 'artisan_up'] as $added) {
            $this->assertArrayHasKey($added, $commands['commands']);
        }

        $this->assertFileDoesNotExist(app_path('Http/Controllers/Admin/GitUpdateController.php'), 'the dead duplicate is gone');
        $this->assertFalse(Route::has('admin.git-update'));
    }

    // -----------------------------------------------------------------
    // Guard rails
    // -----------------------------------------------------------------

    public function test_rollback_needs_the_word_typed_and_rolls_back_one_step(): void
    {
        $controller = $this->controller();

        $response = $controller->executeCommand($this->request('admin.tools.execute-command', ['command' => 'artisan_migrate_rollback']));
        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Type ROLLBACK', $response->getData(true)['error']);
        $this->assertSame([], $controller->ran);

        $response = $controller->executeCommand($this->request('admin.tools.execute-command', ['command' => 'artisan_migrate_rollback', 'confirmation' => 'rollback']));
        $this->assertSame(422, $response->getStatusCode(), 'case matters; this is not a button');
        $this->assertSame([], $controller->ran);

        $response = $controller->executeCommand($this->request('admin.tools.execute-command', ['command' => 'artisan_migrate_rollback', 'confirmation' => 'ROLLBACK']));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['php artisan migrate:rollback --force --step=1'], $controller->ran);
    }

    public function test_only_one_server_command_runs_at_a_time(): void
    {
        $held = Cache::lock(AdminToolsController::LOCK_KEY, 60);
        $this->assertTrue($held->get());

        $controller = $this->controller();

        $response = $controller->executeWorkflow($this->request('admin.tools.execute-workflow', ['workflow' => 'deploy_update']));
        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame([], $controller->ran);

        $response = $controller->executeCommand($this->request('admin.tools.execute-command', ['command' => 'artisan_optimize']));
        $this->assertSame(409, $response->getStatusCode());

        $held->release();

        $response = $controller->executeCommand($this->request('admin.tools.execute-command', ['command' => 'artisan_optimize']));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['php artisan optimize'], $controller->ran);

        // And the lock is released afterwards, even on failure.
        $this->assertTrue(Cache::lock(AdminToolsController::LOCK_KEY, 1)->get());
    }

    public function test_git_talks_to_github_over_a_pinned_host_key(): void
    {
        $env = $this->controller()->gitEnvironmentPublic();

        $this->assertStringContainsString('StrictHostKeyChecking=yes', $env['GIT_SSH_COMMAND']);
        $this->assertStringNotContainsString('StrictHostKeyChecking=no', $env['GIT_SSH_COMMAND']);
        $this->assertStringNotContainsString('/dev/null', $env['GIT_SSH_COMMAND']);

        $knownHosts = AdminToolsController::knownHostsPath();
        $this->assertFileExists($knownHosts);
        $this->assertStringContainsString('github.com ssh-ed25519 ', file_get_contents($knownHosts));
    }

    public function test_the_dashboard_reset_needs_confirmation_and_says_what_it_does(): void
    {
        $this->actingAs($this->super)->get(route('dashboard'))
            ->assertSee('Reset server to GitHub master')
            ->assertSee('name="confirmed" value="1"', false)
            ->assertDontSee('Pull Latest Code');

        $controller = $this->controller();

        $controller->updateFromGit($this->request('admin.tools.update_from_git', []));
        $this->assertSame([], $controller->ran, 'unconfirmed: nothing runs');

        $controller->updateFromGit($this->request('admin.tools.update_from_git', ['confirmed' => 1]));
        $this->assertSame(['git fetch origin', 'git reset --hard origin/master', 'git clean -fd'], $controller->ran);
    }

    public function test_the_server_management_page_reflects_the_new_deploy(): void
    {
        $this->actingAs($this->super)->get(route('admin.server-management'))
            ->assertOk()
            ->assertDontSee('Full Deploy')
            ->assertDontSee('Commit (server:update)')
            ->assertSee('ROLLBACK')
            ->assertSee('Restart Queue Worker')
            ->assertSee('Deploy');
    }

    // -----------------------------------------------------------------
    // db:dump
    // -----------------------------------------------------------------

    public function test_db_dump_copies_a_sqlite_database_and_prunes_old_dumps(): void
    {
        Storage::fake('local');

        $file = tempnam(sys_get_temp_dir(), 'rupkeep-db-');
        file_put_contents($file, 'SQLite format 3');
        // A named connection, never the default: swapping the default mid-test
        // breaks the test transaction's teardown for every test after it.
        config(['database.connections.sqlite_file' => ['driver' => 'sqlite', 'database' => $file]]);

        Storage::disk('local')->put('backups/db/20260101-000000.sqlite', 'old');
        Storage::disk('local')->put('backups/db/20260102-000000.sqlite', 'old');

        $this->artisan('db:dump --connection=sqlite_file --keep=2')->assertSuccessful();

        $dumps = Storage::disk('local')->files('backups/db');
        $this->assertCount(2, $dumps, 'the oldest dump beyond --keep is pruned');
        $this->assertStringContainsString('SQLite format 3', Storage::disk('local')->get(end($dumps)));

        unlink($file);
    }

    public function test_db_dump_refuses_an_in_memory_database(): void
    {
        Storage::fake('local');

        $this->artisan('db:dump')->assertFailed();
        $this->assertSame([], Storage::disk('local')->files('backups/db'));
    }
}
