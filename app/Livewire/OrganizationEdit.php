<?php

namespace App\Livewire;

use App\Models\Organization;
use Livewire\Component;

class OrganizationEdit extends Component
{
    public $organization;

    public function mount(int $organization): void
    {
        $organization = Organization::findOrFail($organization)->append('owner_email');

        $this->authorize('update', $organization);

        $this->organization = $organization;
    }

    public function render()
    {
        return view('livewire.organization-edit');
    }
}
