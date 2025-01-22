<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\User;
use App\Models\UserLog;
use App\Models\Vehicle;
class MergeUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:merge {keep} {old}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        
        $user = User::find($this->argument('keep'));
        $old_user = User::find($this->argument('old'));

        if($user && $old_user && $user->organization_id === $old_user->organization_id){
            UserLog::where('car_driver_id', $old_user->id)->update(['car_driver_id'=>$user->id]);
            Vehicle::where('user_id', $old_user->id)->update(['user_id'=>$user->id]);
            $old_user->delete();
        }else{
            echo 'ERROR. I could not find that user(s)!'; 
        }
    }
}
