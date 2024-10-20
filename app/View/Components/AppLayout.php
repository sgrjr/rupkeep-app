<?php

namespace App\View\Components;

use App\Models\Organization;
use Illuminate\View\Component;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class AppLayout extends Component
{
    public $theme;

    public $organizations = [];
    /**
     * Get the view / contents that represents the component.
     */
    public function mount($theme = null){
        if($theme){
            $this->theme = $theme;
        }else{
            $this->theme = Auth::user()->dashboard_theme;
        }

        //if(auth()->check() && auth()->user()->is_super){
            $this->organizations = Organization::select('id','name')->get();
        //}
    }
    public function render(): View
    {
        return view('layouts.app');
    }
}
