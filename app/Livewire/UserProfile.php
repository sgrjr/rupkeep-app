<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Http\Request;
use App\Models\User;
use Laravel\Fortify\Features;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Laravel\Jetstream\Http\Controllers\Inertia\Concerns\ConfirmsTwoFactorAuthentication;
use Laravel\Jetstream\Agent;

class UserProfile extends Component
{
    use ConfirmsTwoFactorAuthentication;
    public $profile;

    public function mount(Int $user){
        $this->profile = User::find($user);
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
}
