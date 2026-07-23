<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminToolsController;
use Illuminate\Support\Facades\Artisan;
use ReflectionMethod;
use Tests\TestCase;

/**
 * TASK-338 — Command "queue:status" is not defined.
 *
 * The Server Management ops page runs artisan commands from a whitelist. A
 * whitelisted command that does not exist (the historical `queue:status`, whose
 * real counterpart is `queue:health`) surfaced as an uncaught
 * CommandNotFoundException. These tests pin the invariant that every whitelisted
 * artisan command is real, and that running an unknown command fails gracefully
 * instead of throwing / 500-ing.
 */
class AdminToolsQueueCommandTest extends TestCase
{
    public function test_admin_tools_whitelist_only_references_registered_commands(): void
    {
        $registered = array_keys(Artisan::all());

        $method = new ReflectionMethod(AdminToolsController::class, 'getAllowedCommands');
        $method->setAccessible(true);
        $commands = $method->invoke(app(AdminToolsController::class));

        foreach ($commands as $key => $def) {
            if (($def['type'] ?? null) !== 'artisan') {
                continue;
            }

            $base = explode(' ', $def['command'])[0];

            $this->assertContains(
                $base,
                $registered,
                "Whitelisted artisan command '{$base}' (key: {$key}) is not a registered command."
            );
        }

        // The specific regression: queue health uses the real command name.
        $this->assertContains('queue:health', $registered);
        $this->assertNotContains('queue:status', $registered);
    }

    public function test_running_unknown_artisan_command_does_not_throw(): void
    {
        $controller = new class extends AdminToolsController {
            public function runCommandPublic(array $def): array
            {
                return $this->runCommand($def);
            }
        };

        $result = $controller->runCommandPublic([
            'type' => 'artisan',
            'command' => 'queue:status',
            'description' => 'bogus command that must not 500',
        ]);

        $this->assertSame(1, $result['exit_code']);
        $this->assertStringContainsString('queue:status', $result['stderr']);
        $this->assertStringContainsString('not defined', $result['stderr']);
        $this->assertSame('', $result['stdout']);
    }

    public function test_running_a_registered_command_is_not_rejected_by_the_guard(): void
    {
        $controller = new class extends AdminToolsController {
            public function guardCheck(string $name): bool
            {
                return $this->artisanCommandExists($name);
            }
        };

        $this->assertTrue($controller->guardCheck('queue:health'));
        $this->assertFalse($controller->guardCheck('queue:status'));
        $this->assertFalse($controller->guardCheck(''));
    }
}
