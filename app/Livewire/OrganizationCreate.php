<?php

namespace App\Livewire;

use App\Models\Organization;
use Livewire\Component;

class OrganizationCreate extends Component
{
    public function mount(): void
    {
        // Same gate as OrganizationsController::store (TASK-432).
        $this->authorize('create', Organization::class);
    }

    public function render()
    {
        return view('livewire.organization-create');
    }
}
