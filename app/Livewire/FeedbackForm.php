<?php

namespace App\Livewire;

use App\Models\UserEvent;
use App\Services\ExperienceTrackerService;
use Livewire\Component;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;

class FeedbackForm extends Component
{
    #[Validate('required|string|min:3|max:5000')]
    public $feedback = '';

    #[Validate('required|string|in:info,error')]
    public $severity = UserEvent::SEVERITY_INFO;

    public $showModal = false;
    public $submitted = false;
    public $hideTrigger = false;
    public $inline = false;
    public $renderInModal = false;

    public function mount($hideTrigger = false, $inline = false, $showModal = false)
    {
        $this->hideTrigger = $hideTrigger;
        $this->inline = $inline;
        $this->showModal = $showModal;
        $this->renderInModal = $hideTrigger && !$inline && !$showModal; // Render form in external modal
        
        // Only authenticated users can submit feedback
        if (! Auth::check()) {
            abort(403);
        }
    }

    public function openModal()
    {
        $this->showModal = true;
        $this->reset(['feedback', 'severity', 'submitted']);
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['feedback', 'severity', 'submitted']);
    }

    public function submit()
    {
        $this->validate();

        ExperienceTrackerService::track(
            type: UserEvent::TYPE_FEEDBACK,
            severity: $this->severity,
            context: [
                'feedback' => $this->feedback,
                'submitted_at' => now()->toIso8601String(),
            ]
        );

        $this->submitted = true;
        $this->reset(['feedback', 'severity']);

        session()->flash('feedback_success', __('Thank you for your feedback! We appreciate your input.'));
        
        // Dispatch event to close modals (both Livewire modal and footer modal)
        $this->dispatch('feedback-submitted');
    }

    public function render()
    {
        return view('livewire.feedback-form');
    }
}
