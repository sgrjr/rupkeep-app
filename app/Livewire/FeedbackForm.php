<?php

namespace App\Livewire;

use App\Models\Label;
use App\Models\Task;
use App\Models\UserEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;

class FeedbackForm extends Component
{
    #[Validate('required|string|min:3|max:5000')]
    public $feedback = '';

    /**
     * Submission severity drives the Task type:
     * - `error` (bug report) → type='bug'
     * - `info`  (suggestion) → type='feature'
     */
    #[Validate('required|string|in:info,error')]
    public $severity = UserEvent::SEVERITY_INFO;

    public $showModal = false;
    public $submitted = false;
    public $hideTrigger = false;
    public $inline = false;
    public $renderInModal = false;

    /** Code of the task created on submit (e.g. 'TASK-318') — shown in the success state. */
    public ?string $createdCode = null;

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
        $this->reset(['feedback', 'severity', 'submitted', 'createdCode']);
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['feedback', 'severity', 'submitted', 'createdCode']);
    }

    public function submit()
    {
        $this->validate();

        $user = Auth::user();
        $body = trim($this->feedback);

        $task = Task::create([
            'code'              => Task::nextCode(),
            'title'             => Str::limit($body, 80, '…'),
            'description'       => $body,
            'type'              => $this->severity === UserEvent::SEVERITY_ERROR ? 'bug' : 'feature',
            'priority'          => 'medium',
            'status'            => 'triage',
            'is_public'         => false,
            'organization_id'   => $user?->organization_id,
            'submitter_user_id' => $user?->id,
        ]);

        $label = Label::firstOrCreate(['name' => 'source:feedback'], ['color' => '#fb923c']);
        $task->labels()->syncWithoutDetaching([$label->id]);

        $this->createdCode = $task->code;
        $this->submitted = true;
        $this->reset(['feedback', 'severity']);

        session()->flash('feedback_success', __('Thanks — we are tracking your request as :code.', ['code' => $task->code]));

        // Dispatch event to close modals (both Livewire modal and footer modal)
        $this->dispatch('feedback-submitted', code: $task->code);
    }

    public function render()
    {
        return view('livewire.feedback-form');
    }
}
