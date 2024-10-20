<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Organization;

class OrganizationsIndex extends Component
{

    public $organizations;

    public function mount(){
        if($this->authorize('viewAny', Organization::class)){
            $this->organizations = Organization::all();
        }
    }
    public function render()
    {
        return view('livewire.organizations-index');
    }
}