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

class MyUserProfile extends UserProfile
{
        public function mount(Int $user = 0){
            $this->profile = auth()->user();
        }
}
