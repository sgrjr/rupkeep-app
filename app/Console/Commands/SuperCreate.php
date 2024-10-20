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
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        
        $super = config('setup.super_user');
        $user = User::where('email', $super['email'])->first();
        if(!$user){

            $user = User::create([
                'name' => $super['name'],
                'email' => $super['email'],
                'password' => Hash::make($super['password']),
                'organization_role' => $super['organization_role']
            ]);
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
