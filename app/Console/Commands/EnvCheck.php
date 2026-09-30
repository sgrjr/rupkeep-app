<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Checks the live environment against the production checklist in
 * docs/DEPLOYMENT.md ("Required on production", TASK-427).
 *
 * Reads through config() so it sees what the app sees (including a cached
 * config), and NEVER prints a value that could be a secret: those rows only
 * say "set" or "unset". Safe to run from the Server Management page.
 */
class EnvCheck extends Command
{
    protected $signature = 'env:check';

    protected $description = 'Check the environment against the production .env checklist (never prints secrets).';

    private int $failures = 0;

    public function handle(): int
    {
        $env = (string) config('app.env');
        $isProduction = $env === 'production';

        $this->line('=== Environment check ('.$env.') ===');
        if (! $isProduction) {
            $this->line('Not production: rows are reported but the exit code is always 0 here.');
        }
        $this->line('');

        $this->equals('APP_ENV', $env, 'production');
        $this->equals('APP_DEBUG', config('app.debug') ? 'true' : 'false', 'false');
        $this->mustBeSet('APP_KEY', config('app.key'));
        $this->matches('APP_URL', (string) config('app.url'), '/^https:\/\//', 'must start with https://');
        $this->equals('SESSION_SECURE_COOKIE', config('session.secure') ? 'true' : 'false', 'true');
        $this->contains('LOG_STACK', (array) config('logging.channels.stack.channels', []), 'daily');
        $this->equals('DB_CONNECTION', (string) config('database.default'), 'mysql');
        $this->mustBeSet('DB_PASSWORD', config('database.connections.'.config('database.default').'.password'));
        $this->equals('MAIL_MAILER', (string) config('mail.default'), 'brevo');
        $this->mustBeSet('MAIL_USERNAME', config('mail.mailers.brevo.username'));
        $this->mustBeSet('MAIL_PASSWORD', config('mail.mailers.brevo.password'));
        $this->mustBeSet('BREVO_API_KEY', config('mail.mailers.brevo.key'));
        $this->fromAddress((string) config('mail.from.address'));
        $this->mustBeSet('VAPID_PUBLIC_KEY', config('webpush.vapid.public_key'));
        $this->mustBeSet('VAPID_PRIVATE_KEY', config('webpush.vapid.private_key'));
        $this->mustBeSet('VAPID_SUBJECT', config('webpush.vapid.subject'));
        $this->equals('APP_DISPLAY_TIMEZONE', (string) config('app.display_timezone'), 'America/New_York');
        $this->equals('DISPATCH_AUTO_CAPTURE', config('dispatch.auto_capture.enabled') ? 'true' : 'false', 'true');
        $this->equals('SETUP_CONSOLE_ENABLED', config('setup-console.enabled') ? 'true' : 'false', 'false');
        $this->mustBeUnset('SETUP_PASSWORD', config('setup-console.password'));
        $this->mustBeUnset('DISPATCH_REMOTE_URL', config('dispatch.remote.url'));
        $this->mustBeUnset('DISPATCH_REMOTE_TOKEN', config('dispatch.remote.token'));
        $this->mustBeSet('SUPER_EMAIL', config('setup.super_user.email'));
        $this->mustBeSet('SUPER_PASSWORD', config('setup.super_user.password'));
        $this->infoRow('PRICING_DEFAULT_ORGANIZATION_ID', config('pricing.default_organization_id')
            ? 'set' : 'blank (falls back to the org named "Casco Bay Pilot Car")');
        $this->equals('QUEUE_CONNECTION', (string) config('queue.default'), 'database');

        $this->line('');
        if ($this->failures === 0) {
            $this->info('All checks passed.');

            return self::SUCCESS;
        }

        $this->error($this->failures.' check(s) failed. See docs/DEPLOYMENT.md, "Required on production".');

        return $isProduction ? self::FAILURE : self::SUCCESS;
    }

    private function equals(string $key, string $actual, string $expected): void
    {
        $this->row($actual === $expected, $key, "is {$actual}", "must be {$expected} (is {$actual})");
    }

    private function matches(string $key, string $actual, string $pattern, string $why): void
    {
        $this->row((bool) preg_match($pattern, $actual), $key, "is {$actual}", "{$why} (is ".($actual === '' ? 'blank' : $actual).')');
    }

    private function contains(string $key, array $actual, string $needle): void
    {
        $shown = implode(',', $actual);
        $this->row(in_array($needle, $actual, true), $key, "is {$shown}", "must include {$needle} (is {$shown})");
    }

    /** A value that may be a secret: report presence only. */
    private function mustBeSet(string $key, mixed $value): void
    {
        $set = $value !== null && $value !== '';
        $this->row($set, $key, 'set', 'must be set (is unset)');
    }

    /** A value that must NOT be present on the host. */
    private function mustBeUnset(string $key, mixed $value): void
    {
        $set = $value !== null && $value !== '';
        $this->row(! $set, $key, 'unset', 'must be unset on the host (is set)');
    }

    private function fromAddress(string $address): void
    {
        $ok = $address !== '' && ! str_ends_with($address, '@example.com');
        $this->row($ok, 'MAIL_FROM_ADDRESS', "is {$address} (confirm it is a Brevo-verified sender)", 'must be a Brevo-verified sender (is '.($address === '' ? 'blank' : $address).')');
    }

    private function infoRow(string $key, string $note): void
    {
        $this->line(sprintf('[INFO] %-32s %s', $key, $note));
    }

    private function row(bool $ok, string $key, string $passNote, string $failNote): void
    {
        if ($ok) {
            $this->line(sprintf('[PASS] %-32s %s', $key, $passNote));

            return;
        }

        $this->failures++;
        $this->line(sprintf('[FAIL] %-32s %s', $key, $failNote));
    }
}
