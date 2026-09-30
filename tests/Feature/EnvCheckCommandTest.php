<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * `php artisan env:check` (TASK-427): the production .env checklist as a
 * command. It reads config, never prints a secret, and only fails the exit
 * code when APP_ENV is production.
 */
class EnvCheckCommandTest extends TestCase
{
    private function productionShapedConfig(): void
    {
        config()->set([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.url' => 'https://pilotcar.io',
            'session.secure' => true,
            'logging.channels.stack.channels' => ['daily'],
            'database.default' => 'mysql',
            'database.connections.mysql.password' => 'db-secret-value',
            'mail.default' => 'brevo',
            'mail.mailers.brevo.username' => 'brevo-user',
            'mail.mailers.brevo.password' => 'brevo-smtp-secret',
            'mail.mailers.brevo.key' => 'brevo-api-secret',
            'mail.from.address' => 'dispatch@pilotcar.io',
            'webpush.vapid.public_key' => 'vapid-public',
            'webpush.vapid.private_key' => 'vapid-private-secret',
            'webpush.vapid.subject' => 'mailto:admin@pilotcar.io',
            'app.display_timezone' => 'America/New_York',
            'dispatch.auto_capture.enabled' => true,
            'setup-console.enabled' => false,
            'setup-console.password' => null,
            'dispatch.remote.url' => null,
            'dispatch.remote.token' => null,
            'setup.super_user.email' => 'super@pilotcar.io',
            'setup.super_user.password' => 'super-secret',
            'pricing.default_organization_id' => null,
            'queue.default' => 'database',
        ]);
    }

    public function test_a_production_shaped_environment_passes(): void
    {
        $this->productionShapedConfig();

        $this->artisan('env:check')
            ->expectsOutputToContain('All checks passed.')
            ->assertSuccessful();
    }

    public function test_debug_on_and_a_setup_password_fail_on_production(): void
    {
        $this->productionShapedConfig();
        config()->set('app.debug', true);
        config()->set('setup-console.password', 'hunter2');

        $this->artisan('env:check')
            ->expectsOutputToContain('[FAIL] APP_DEBUG')
            ->expectsOutputToContain('[FAIL] SETUP_PASSWORD')
            ->expectsOutputToContain('2 check(s) failed')
            ->assertFailed();
    }

    public function test_secrets_are_never_printed(): void
    {
        $this->productionShapedConfig();
        config()->set('setup-console.password', 'hunter2');

        $this->artisan('env:check')
            ->doesntExpectOutputToContain('db-secret-value')
            ->doesntExpectOutputToContain('brevo-smtp-secret')
            ->doesntExpectOutputToContain('brevo-api-secret')
            ->doesntExpectOutputToContain('vapid-private-secret')
            ->doesntExpectOutputToContain('super-secret')
            ->doesntExpectOutputToContain('hunter2')
            ->run();
    }

    public function test_failures_outside_production_are_reported_but_do_not_fail(): void
    {
        $this->productionShapedConfig();
        config()->set('app.env', 'local');
        config()->set('app.debug', true);

        $this->artisan('env:check')
            ->expectsOutputToContain('Not production')
            ->expectsOutputToContain('[FAIL] APP_ENV')
            ->expectsOutputToContain('[FAIL] APP_DEBUG')
            ->assertSuccessful();
    }
}
