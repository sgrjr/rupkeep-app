<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Organization;
use Laravel\Fortify\Features;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Laravel\Jetstream\Http\Controllers\Inertia\Concerns\ConfirmsTwoFactorAuthentication;
use Laravel\Jetstream\Agent;
use App\Actions\SendUserNotification;

class UserProfile extends Component
{
    use ConfirmsTwoFactorAuthentication;
    public $profile;

    public Int $merged_user;

    public function mount(Int $user){
        $this->profile = User::with('organization')->find($user);

        $this->authorize('update', $this->profile);
    }
    public function render(Request $request)
    {
       $confirmsTwoFactorAuthentication = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');

       if($confirmsTwoFactorAuthentication){
        $this->validateTwoFactorAuthenticationState($request);
       }
       $sessions = $this->sessions($request)->all();

        return view('livewire.user-profile', compact('sessions','confirmsTwoFactorAuthentication'));
    }

    public function sessions(Request $request)
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        return collect(
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                    ->where('user_id', $request->user()->getAuthIdentifier())
                    ->orderBy('last_activity', 'desc')
                    ->get()
        )->map(function ($session) use ($request) {
            $agent = $this->createAgent($session);

            return (object) [
                'agent' => [
                    'is_desktop' => $agent->isDesktop(),
                    'platform' => $agent->platform(),
                    'browser' => $agent->browser(),
                ],
                'ip_address' => $session->ip_address,
                'is_current_device' => $session->id === $request->session()->getId(),
                'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
            ];
        });
    }

    protected function createAgent($session)
    {
        return tap(new Agent(), fn ($agent) => $agent->setUserAgent($session->user_agent));
    }

    public function testNotification(){
        SendUserNotification::to($this->profile, 'This is a test notification from ' . $this->profile->organization?->name . '.', subject: 'test');
    }

    public function mergeToUser(){
        if(!empty($this->merged_user)){
            $user = User::find($this->merged_user);

            if($user && $this->profile->organization_id === $user->organization_id){
                \App\Models\UserLog::where('car_driver_id', $this->profile->id)->update(['car_driver_id'=>$user->id]);
                \App\Models\Vehicle::where('user_id', $this->profile->id)->update(['user_id'=>$user->id]);
                $this->profile->delete();
    
                return redirect()->route('user.profile', ['user'=> $user->id]);
            }else{
                echo 'ERROR. I could not find that user(s)!'; 
            }
        }
       
    }
}
