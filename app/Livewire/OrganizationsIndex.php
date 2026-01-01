<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Organization;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class OrganizationsIndex extends Component
{
    use AuthorizesRequests;

    public $organizations;

    public function mount(){
        $this->authorize('viewAny', Organization::class);
        $this->organizations = Organization::all();
    }
    public function render()
    {
        return view('livewire.organizations-index');
    }
}