<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * TASK-424: the /setup console can run migrate:fresh, so it is gated three
 * ways - the SETUP_CONSOLE_ENABLED flag (off by default), a signed-in super
 * user, and the shared SETUP_PASSWORD (throttled).
 */
class SetupConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Config::set('setup-console.enabled', true);
        Config::set('setup-console.username', 'setup');
        Config::set('setup-console.password', 'secret');
    }

    private function super(): User
    {
        return User::factory()->superUser()->create();
    }

    public function test_console_is_disabled_unless_the_env_flag_is_set(): void
    {
        // The config file is the thing under test here, not the runtime override.
        $fresh = require base_path('config/setup-console.php');

        $this->assertFalse($fresh['enabled'], 'SETUP_CONSOLE_ENABLED must default to off');
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('setup.index'))->assertRedirect(route('login'));
        $this->post(route('setup.login'), ['username' => 'setup', 'password' => 'secret'])->assertRedirect(route('login'));
        $this->post(route('setup.run'), ['action' => 'db-reset'])->assertRedirect(route('login'));
    }

    public function test_non_super_user_is_forbidden_even_with_the_password(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('setup.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('setup.login'), ['username' => 'setup', 'password' => 'secret'])->assertForbidden();
        $this->actingAs($admin)->post(route('setup.run'), ['action' => 'db-reset'])->assertForbidden();
    }

    public function test_disabled_console_is_not_found_even_for_a_super_user(): void
    {
        Config::set('setup-console.enabled', false);
        $super = $this->super();

        $this->actingAs($super)->get(route('setup.index'))->assertNotFound();
        $this->actingAs($super)->post(route('setup.login'), ['username' => 'setup', 'password' => 'secret'])->assertNotFound();

        Session::put('setup_console.authorized', true);
        $this->actingAs($super)->post(route('setup.run'), ['action' => 'db-reset'])->assertNotFound();
    }

    public function test_login_screen_displayed_when_not_authorized(): void
    {
        $response = $this->actingAs($this->super())->get(route('setup.index'));

        $response->assertOk()
            ->assertSee(__('Unlock Console'))
            ->assertSee(__('Username'));
    }

    public function test_requires_valid_credentials_to_unlock(): void
    {
        $super = $this->super();

        $this->actingAs($super)->post(route('setup.login'), [
            'username' => 'setup',
            'password' => 'wrong',
        ])->assertSessionHasErrors('password');
        $this->assertFalse(Session::get('setup_console.authorized', false));

        $response = $this->actingAs($super)->post(route('setup.login'), [
            'username' => 'setup',
            'password' => 'secret',
        ]);

        $response->assertRedirect(route('setup.index'));
        $this->assertTrue(Session::get('setup_console.authorized', false));
    }

    public function test_password_attempts_are_throttled(): void
    {
        $super = $this->super();

        foreach (range(1, 5) as $attempt) {
            $this->actingAs($super)
                ->post(route('setup.login'), ['username' => 'setup', 'password' => 'wrong'])
                ->assertSessionHasErrors('password');
        }

        $this->actingAs($super)
            ->post(route('setup.login'), ['username' => 'setup', 'password' => 'secret'])
            ->assertStatus(429);
    }

    public function test_running_db_reset_requires_authorization(): void
    {
        $super = $this->super();

        $this->actingAs($super)->post(route('setup.login'), [
            'username' => 'setup',
            'password' => 'secret',
        ]);

        Artisan::shouldReceive('call')
            ->once()
            ->with('db:reset');
        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('reset output');

        $response = $this->actingAs($super)->post(route('setup.run'), [
            'action' => 'db-reset',
        ]);

        $response->assertRedirect(route('setup.index'));
        $response->assertSessionHas('success');
        $this->assertSame('reset output', Session::get('setup_console.last_output'));
        $this->assertSame('success', Session::get('setup_console.last_status'));
    }

    public function test_running_command_without_password_step_is_forbidden(): void
    {
        Session::forget('setup_console.authorized');

        $this->actingAs($this->super())->post(route('setup.run'), [
            'action' => 'db-reset',
        ])->assertForbidden();
    }
}
