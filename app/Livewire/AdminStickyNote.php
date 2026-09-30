<?php

namespace App\Livewire;

use App\Models\AdminNote;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * The "where are the SSH credentials" sticky note in Super Admin Tools.
 *
 * Super users only, and checked in every action rather than trusted to the
 * blade: the dashboard section it sits in is wrapped in an isSuper() check,
 * but each Livewire action is its own callable endpoint.
 */
class AdminStickyNote extends Component
{
    public string $noteKey = AdminNote::SSH_CREDENTIALS;

    #[Validate('nullable|string|max:2000')]
    public string $body = '';

    public bool $editing = false;

    public ?string $updatedBy = null;

    public ?string $updatedAt = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isSuper(), 403);

        $this->load();
    }

    public function edit(): void
    {
        abort_unless(auth()->user()?->isSuper(), 403);

        $this->editing = true;
    }

    public function cancel(): void
    {
        $this->load();
        $this->editing = false;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->isSuper(), 403);

        $this->validate();

        AdminNote::updateOrCreate(
            ['key' => $this->noteKey],
            ['body' => trim($this->body), 'updated_by_user_id' => auth()->id()],
        );

        $this->load();
        $this->editing = false;
    }

    private function load(): void
    {
        $note = AdminNote::with('updatedBy')->where('key', $this->noteKey)->first();

        $this->body = $note?->body ?? '';
        $this->updatedBy = $note?->updatedBy?->name;
        $this->updatedAt = $note?->updated_at?->format('M j, Y');
    }

    public function render()
    {
        return view('livewire.admin-sticky-note');
    }
}
