<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\User;
use App\Models\Organization;

use Illuminate\Support\Facades\Hash;
use App\Actions\Jetstream\CreateTeam;

class SuperCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'super:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bootstrap the application-wide super user and its organization from config/setup.php.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        
        $super = config('setup.super_user');

        if (empty($super['email'])) {
            $this->error('setup.super_user.email is not configured — set SUPER_EMAIL in .env.');

            return self::FAILURE;
        }

        $user = User::where('email', $super['email'])->first();
        if(!$user){

            $user = User::create([
                'name' => $super['name'],
                'email' => $super['email'],
                'password' => Hash::make($super['password']),
                'organization_role' => $super['organization_role']
            ]);
        }

        // The application-wide super flag (TASK-366). This is the only thing
        // that mints one on a fresh install: `migrate:fresh` runs the migration
        // against an empty users table, so its backfill has nothing to promote,
        // and is_super is not fillable so the create() above cannot set it.
        // Without this the admin tools are unreachable after a db:reset.
        if (! $user->is_super) {
            $user->forceFill(['is_super' => true])->save();
        }

        $org = config('setup.organization');

        $organization = Organization::where('name', $org['name'])->first();

        if(!$organization){
            $organization = Organization::create(array_merge($org,['user_id'=>$user->id]));
        }

        if(empty($user->organization_id)){
            $user->update(['organization_id' => $organization->id]);
        }

        echo json_encode($user->toArray());
    }
}
