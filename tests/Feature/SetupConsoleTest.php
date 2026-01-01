<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

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

    public function test_login_screen_displayed_when_not_authorized(): void
    {
        $response = $this->get(route('setup.index'));

        $response->assertOk()
            ->assertSee(__('Unlock Console'))
            ->assertSee(__('Username'));
    }

    public function test_requires_valid_credentials_to_unlock(): void
    {
        $response = $this->post(route('setup.login'), [
            'username' => 'setup',
            'password' => 'secret',
        ]);

        $response->assertRedirect(route('setup.index'));
        $this->assertTrue(Session::get('setup_console.authorized', false));
    }

    public function test_running_db_reset_requires_authorization(): void
    {
        $this->post(route('setup.login'), [
            'username' => 'setup',
            'password' => 'secret',
        ]);

        Artisan::shouldReceive('call')
            ->once()
            ->with('db:reset');
        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('reset output');

        $response = $this->post(route('setup.run'), [
            'action' => 'db-reset',
        ]);

        $response->assertRedirect(route('setup.index'));
        $response->assertSessionHas('success');
        $this->assertSame('reset output', Session::get('setup_console.last_output'));
        $this->assertSame('success', Session::get('setup_console.last_status'));
    }

    public function test_running_command_without_login_is_forbidden(): void
    {
        Session::forget('setup_console.authorized');

        $this->post(route('setup.run'), [
            'action' => 'db-reset',
        ])->assertForbidden();
    }
}
