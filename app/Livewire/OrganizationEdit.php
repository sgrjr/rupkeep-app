<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Organization;

class OrganizationEdit extends Component
{
    public $organization;

    public function mount(Int $organization){
        $organization = Organization::find($organization)->append('owner_email');

        if($this->authorize('view', $organization)){
            $this->organization = $organization;
        }
    }
    public function render()
    {
        return view('livewire.organization-edit');
    }
}
