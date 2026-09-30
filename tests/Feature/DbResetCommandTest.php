<?php

namespace Tests\Feature;

use App\Console\Commands\DBReset;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * TASK-424: `db:reset` is migrate:fresh in disguise. Production refuses it
 * unless --force-production is passed, so the web setup console (which never
 * passes the flag) cannot wipe the live database.
 *
 * The command is driven directly rather than through $this->artisan() because
 * mocking the Artisan facade swaps the console kernel, which is what
 * $this->artisan() dispatches through.
 */
class DbResetCommandTest extends TestCase
{
    use RefreshDatabase;

    private function runCommand(array $input): array
    {
        $command = new DBReset();
        $command->setLaravel($this->app);

        // Hand the command a ready-made OutputStyle. RefreshDatabase's own
        // artisan call leaves a console-output mock bound in the container, and
        // Command::run would otherwise resolve that mock and swallow the output.
        $input = new ArrayInput($input);
        $buffer = new BufferedOutput();
        $status = $command->run($input, new OutputStyle($input, $buffer));

        return [$status, $buffer->fetch()];
    }

    public function test_refuses_on_production_without_the_flag(): void
    {
        $this->app['env'] = 'production';

        Artisan::shouldReceive('call')->never();

        [$status, $output] = $this->runCommand([]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('refused', $output);
        $this->assertStringContainsString('--force-production', $output);
    }

    public function test_runs_on_production_with_the_flag(): void
    {
        $this->app['env'] = 'production';

        Artisan::shouldReceive('call')->once()->with('migrate:fresh --force', [], Mockery::any())->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('super:create', [], Mockery::any())->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('db:seed --force', [], Mockery::any())->andReturn(0);

        [$status] = $this->runCommand(['--force-production' => true]);

        $this->assertSame(0, $status);
    }

    public function test_runs_outside_production_and_hands_the_organization_to_the_first_configured_user(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->admin()->create(['email' => 'mary@example.test', 'organization_id' => $organization->id]);
        config()->set('setup.cbpc_users.0.email', 'mary@example.test');

        Artisan::shouldReceive('call')->once()->with('migrate:fresh --force', [], Mockery::any())->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('super:create', [], Mockery::any())->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('db:seed --force', [], Mockery::any())->andReturn(0);

        [$status, $output] = $this->runCommand([]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('done.', $output);
        $this->assertSame($owner->id, $organization->fresh()->user_id);
        $this->assertSame('mary@example.test', $organization->fresh()->primary_contact);
    }

    public function test_missing_first_configured_user_warns_instead_of_crashing(): void
    {
        config()->set('setup.cbpc_users.0.email', null);

        Artisan::shouldReceive('call')->times(3)->andReturn(0);

        [$status, $output] = $this->runCommand([]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('owner left unchanged', $output);
    }

    public function test_stops_when_super_create_fails(): void
    {
        Artisan::shouldReceive('call')->once()->with('migrate:fresh --force', [], Mockery::any())->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('super:create', [], Mockery::any())->andReturn(1);
        Artisan::shouldReceive('call')->never()->with('db:seed --force', [], Mockery::any());

        [$status, $output] = $this->runCommand([]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('super:create failed', $output);
    }
}
