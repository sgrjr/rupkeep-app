<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Organization;
use App\Models\Vehicle;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->withPersonalTeam()->create();
        $org = config('setup.cbpc');

        $super_user = User::superUser();

        $org_values = [
            'name'=> $org['name'],
            'primary_contact'=> $org['primary_contact'],
            'telephone'=> $org['telephone'],
            'fax'=>$org['fax'],
            'email'=>$org['email'],
            'street'=>$org['street'],
            'city'=>$org['city'],
            'state'=>$org['state'],
            'zip'=>$org['zip'],
            'user_id'=> $super_user? $super_user->id:1,
            'logo_url'=>$org['logo_url'],
            'website_url'=>$org['website_url'],
            //'facebook_url' => 'https://www.facebook.com/cascobaypc'
        ];

        $organization = Organization::where('name', $org_values['name'])->first();
    
        if(!$organization) $organization = Organization::create($org_values);

        foreach(config('setup.cbpc_users') as $user){
            if(User::where('email', $user['email'])->count() < 1){
                User::create([
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'password' => Hash::make($user['password']),
                    'organization_id' => $organization->id,
                    'organization_role' => $user['organization_role'],
                    'theme' => 'default',
                    'notification_address' => $user['notification_address']
                ]);
            }
        }

        foreach(config('setup.cbpc_vehicles') as $v){

            if(Vehicle::where('name', $v['name'])->where('organization_id', $organization->id)->count() < 1 ){
                Vehicle::create([
                    'name'=> $v['name'],
                    'odometer' => $v['odometer'],
                    'odometer_updated_at' => now()->toDateTimeString(),
                    'organization_id' => $organization->id,
                ]);
            }
        }
        

    }
}
