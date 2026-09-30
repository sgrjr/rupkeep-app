<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class DBReset extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:reset {--force-production : Allow the reset to run when APP_ENV=production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset the database to a fresh install: migrate:fresh, super:create, db:seed.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // This is migrate:fresh in disguise. Production refuses it unless the
        // operator says so explicitly on the command line (TASK-424) - the web
        // setup console never passes the flag, so it cannot wipe production.
        if (app()->isProduction() && ! $this->option('force-production')) {
            $this->error('db:reset refused: APP_ENV is production. Re-run with --force-production if you really mean to wipe it.');

            return self::FAILURE;
        }

        $this->info('Fresh migration.');
        Artisan::call('migrate:fresh --force', [], $this->output);

        $this->info('Create super user.');
        if (Artisan::call('super:create', [], $this->output) !== self::SUCCESS) {
            $this->error('super:create failed; stopping before seeding.');

            return self::FAILURE;
        }

        $this->info('Fresh seeding of database.');
        Artisan::call('db:seed --force', [], $this->output);

        $this->info('Make the first configured user the organization owner.');
        $ownerEmail = config('setup.cbpc_users.0.email');
        $owner = $ownerEmail ? User::where('email', $ownerEmail)->first() : null;

        if ($owner === null) {
            $this->warn('No user found for setup.cbpc_users[0] (u1_email); organization owner left unchanged.');
        } else {
            Organization::where('id', $owner->organization_id)->update([
                'user_id' => $owner->id,
                'primary_contact' => $owner->email,
            ]);
        }

        $this->info('done.');

        return self::SUCCESS;
    }
}
