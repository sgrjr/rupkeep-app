<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Route; // To get the current route name for redirection
use Illuminate\Support\Str;

class DeleteConfirmationButton extends Component
{
    public $actionUrl;
    public $buttonText;
    public $redirectRoute; // New property for redirection

    public $confirmingDelete = false; // State to control modal visibility

    /**
     * Mount the component with necessary properties.
     *
     * @param string $actionUrl The URL to send the DELETE request to.
     * @param string $buttonText The text to display on the delete button.
     * @param string|null $redirectRoute The route name to redirect to after successful deletion.
     * If null, it will try to redirect to the previous page or current route.
     */
    public function mount($actionUrl, $buttonText = 'Delete', $redirectRoute = null)
    {
        $this->actionUrl = $actionUrl;
        $this->buttonText = $buttonText;
        $this->redirectRoute = $redirectRoute;
    }

    /**
     * Show the confirmation modal.
     */
    public function confirmDelete()
    {
        $this->confirmingDelete = true;
    }

    /**
     * Perform the actual delete operation.
     */
    public function delete()
    {
  try {
            // Parse the URL to extract the model ID and type.
            // This assumes your actionUrl is in a format like /my/jobs/{id}, /attachments/{id}, /logs/{id}
            $path = parse_url($this->actionUrl, PHP_URL_PATH);
            $segments = array_filter(explode('/', $path)); // Remove empty segments

            $id = null;
            $modelIdentifier = null; // e.g., 'jobs', 'attachments', 'logs'

            // Get the last segment as ID and the second to last as model identifier
            if (count($segments) >= 1) {
                $id = end($segments);
                if (count($segments) >= 2) {
                    $modelIdentifier = prev($segments); // Get the segment before the ID
                }
            }

            // Map the model identifier to the actual Eloquent model class
            $modelClass = null;
            switch ($modelIdentifier) {
                case 'jobs':
                    $modelClass = \App\Models\PilotCarJob::class;
                    break;
                case 'attachments':
                    $modelClass = \App\Models\Attachment::class;
                    break;
                case 'logs':
                    $modelClass = \App\Models\UserLog::class;
                    break;
                // Add more cases here for other models you might delete with this component
                default:
                    // If the model identifier is part of a longer path (e.g., 'my/jobs'),
                    // we might need a more specific check.
                    if (Str::contains($this->actionUrl, '/my/jobs/')) {
                        $modelClass = \App\Models\PilotCarJob::class;
                    }
                    // Fallback if no specific model is identified
                    break;
            }

            if ($modelClass && $id) {
                $model = $modelClass::find($id);

                if ($model && auth()->user()?->can('delete', $model)) {
                    $model->delete();
                    session()->flash('message', 'Item deleted successfully!');
                } else {
                    session()->flash('error', 'Item not found for deletion.');
                }
            } else {
                session()->flash('error', 'Could not determine item to delete from URL: ' . $this->actionUrl);
            }

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete item: ' . $e->getMessage());
        }

        // Close the modal
        $this->confirmingDelete = false;

        // Redirect after deletion
        if ($this->redirectRoute) {
            return redirect()->route($this->redirectRoute);
        } else {
            // Attempt to redirect to the previous page or a default route
            if (url()->previous() !== url()->current()) {
                return redirect()->back();
            } else {
                // Fallback if no previous page (e.g., direct access)
                return redirect('/'); // Or a more appropriate default route
            }
        }
    }

    public function render()
    {
        return view('livewire.delete-confirmation-button');
    }
}
