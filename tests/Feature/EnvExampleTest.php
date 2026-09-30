<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * .env.example is production-shaped (TASK-427): a fresh host that copies it
 * gets safe defaults, and every key the code reads is present so nothing has
 * to be discovered by reading config/.
 */
class EnvExampleTest extends TestCase
{
    /** @return array<string, string> key => raw value (inline comments stripped) */
    private function example(): array
    {
        $pairs = [];

        foreach (file(base_path('.env.example'), FILE_IGNORE_NEW_LINES) as $line) {
            if (! preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $m)) {
                continue;
            }
            $value = preg_replace('/\s+#.*$/', '', $m[2]);
            $pairs[$m[1]] = trim($value, " \t\"'");
        }

        return $pairs;
    }

    public function test_defaults_are_production_safe(): void
    {
        $env = $this->example();

        $this->assertSame('production', $env['APP_ENV']);
        $this->assertSame('false', $env['APP_DEBUG']);
        $this->assertSame('true', $env['SESSION_SECURE_COOKIE']);
        $this->assertSame('daily', $env['LOG_STACK']);
        $this->assertSame('false', $env['SETUP_CONSOLE_ENABLED']);
        $this->assertSame('', $env['SETUP_PASSWORD']);
        $this->assertSame('', $env['DISPATCH_REMOTE_TOKEN']);
        $this->assertSame('true', $env['DISPATCH_AUTO_CAPTURE']);
        $this->assertSame('brevo', $env['MAIL_MAILER']);
        $this->assertStringStartsWith('https://', $env['APP_URL']);
    }

    public function test_every_app_specific_key_is_listed(): void
    {
        $env = $this->example();

        $required = [
            'APP_DISPLAY_TIMEZONE', 'LOG_DAILY_DAYS', 'SANCTUM_STATEFUL_DOMAINS', 'SESSION_SECURE_COOKIE',
            'BREVO_API_KEY', 'MAIL_FROM_ADDRESS',
            'LOGIN_CODE_EXPIRY_MINUTES', 'LOGIN_CODE_LENGTH',
            'VAPID_SUBJECT', 'VAPID_PUBLIC_KEY', 'VAPID_PRIVATE_KEY',
            'SUPER_NAME', 'SUPER_EMAIL', 'SUPER_PASSWORD',
            'u1_email', 'u1_password', 'u2_email', 'u2_password', 'default_password',
            'PRICING_DEFAULT_ORGANIZATION_ID',
            'FEATURE_INVOICE_PDF_DOWNLOADS',
            'DISPATCH_AUTO_CAPTURE', 'DISPATCH_REMOTE_URL', 'DISPATCH_REMOTE_TOKEN',
            'SETUP_CONSOLE_ENABLED', 'SETUP_USERNAME', 'SETUP_PASSWORD',
        ];

        $missing = array_diff($required, array_keys($env));

        $this->assertSame([], array_values($missing), 'keys missing from .env.example: '.implode(', ', $missing));
    }
}
