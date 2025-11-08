<?php

namespace App\Livewire;

use App\Models\Attachment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class AttachmentVisibilityToggle extends Component
{
    use AuthorizesRequests;

    public Attachment $attachment;
    public bool $isPublic = false;

    public function mount(Attachment $attachment): void
    {
        $this->attachment = $attachment;
        $this->isPublic = (bool) $attachment->is_public;

        $this->authorize('updateVisibility', $this->attachment);
    }

    public function updatedIsPublic(): void
    {
        $this->toggle();
    }

    public function toggle(): void
    {
        $this->authorize('updateVisibility', $this->attachment);

        $this->attachment->update([
            'is_public' => $this->isPublic,
        ]);

        $this->dispatch('attachmentVisibilityUpdated', id: $this->attachment->id, isPublic: $this->isPublic);
    }

    public function render()
    {
        return view('livewire.attachment-visibility-toggle');
    }
}

