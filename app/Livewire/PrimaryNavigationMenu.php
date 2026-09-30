<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Session;
use App\Models\Organization; // Import the Organization model

class PrimaryNavigationMenu extends Component
{
    /**
     * The navigation history, loaded from the session.
     * @var array
     */
    public $history = [];

    /**
     * A collection of organizations to display in the navigation.
     * @var \Illuminate\Support\Collection
     */
    public $organizations;

    /**
     * Mounts the component, initializing properties.
     * This method is called once when the component is first rendered.
     */
    public function mount()
    {
        // Load navigation history from the session.
        // If no history exists, it defaults to an empty array.
        $this->history = Session::get('navigation_history', []);

        // Fetch organizations to pass to the view.
        // This prevents direct database calls within the Blade template.
        // Only super users get the organization switcher; loading every
        // organization's name for every role leaked the tenant list (TASK-434).
        $this->organizations = auth()->user()?->can('viewAny', Organization::class)
            ? Organization::select('id', 'name')->get()
            : collect();
    }

    /**
     * Renders the Livewire component's view.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        // Pass the history and organizations data to the view.
        return view('livewire.primary-navigation-menu', [
            'history' => $this->history,
            'organizations' => $this->organizations,
        ]);
    }
}