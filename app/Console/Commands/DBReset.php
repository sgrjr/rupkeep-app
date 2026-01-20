<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use App\Models\Organization;

class DBReset extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Results database to default.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        
      echo 'Fresh Migration. ' . PHP_EOL;
      Artisan::call('migrate:fresh --force');

      echo  PHP_EOL . 'Create Super User.';
      Artisan::call('super:create');

      echo  PHP_EOL . 'Fresh Seeding of Database';
      Artisan::call('db:seed --force');

      echo  PHP_EOL . 'Make Mary Owner.';

      $user = User::where('email', config('setup.cbpc_users')[0]['email'])->first();

      Organization::where('id', $user->organization_id)->update([
        'user_id' => $user->id,
        'primary_contact' => $user->email
      ]);

      echo  PHP_EOL . 'done.';
      return 1;
    }
}
