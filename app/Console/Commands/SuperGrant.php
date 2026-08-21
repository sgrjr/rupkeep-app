<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Grant or revoke application-wide super-user access (TASK-366).
 *
 * `is_super` is deliberately absent from User::$fillable so it can never be set
 * through mass assignment, which leaves this command (or a direct DB update) as
 * the only way to change it.
 */
class SuperGrant extends Command
{
    protected $signature = 'super:grant
        {email? : The user to grant. Omit to list current super users.}
        {--revoke : Remove super access instead of granting it}';

    protected $description = 'Grant, revoke, or list application-wide super users.';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (! $email) {
            return $this->listSupers();
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email {$email}.");

            return self::FAILURE;
        }

        $revoking = (bool) $this->option('revoke');

        // Refuse to remove the last super user: nothing else in the app can
        // grant the flag back, so this would lock everyone out of the admin
        // tools permanently.
        if ($revoking && $user->isSuper() && User::where('is_super', true)->count() <= 1) {
            $this->error('Refusing to revoke the last super user — no one would be able to grant it back.');

            return self::FAILURE;
        }

        $user->is_super = ! $revoking;
        $user->save();

        $this->info(sprintf(
            '%s is %s an application-wide super user.',
            $user->email,
            $revoking ? 'no longer' : 'now'
        ));

        return self::SUCCESS;
    }

    private function listSupers(): int
    {
        $supers = User::where('is_super', true)->orderBy('id')->get(['id', 'name', 'email', 'organization_role']);

        if ($supers->isEmpty()) {
            $this->warn('No super users. Grant one with: php artisan super:grant {email}');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Org role'],
            $supers->map(fn ($u) => [$u->id, $u->name, $u->email, $u->organization_role])->all()
        );

        return self::SUCCESS;
    }
}
