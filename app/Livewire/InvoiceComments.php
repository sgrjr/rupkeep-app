<?php

namespace App\Livewire;

use App\Models\Invoice;
use App\Models\InvoiceComment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

class InvoiceComments extends Component
{
    use AuthorizesRequests;

    public Invoice $invoice;

    #[Validate('required|string|min:3|max:2000')]
    public string $body = '';

    #[Validate('boolean')]
    public bool $flag_for_attention = false;

    protected $listeners = [
        'commentAdded' => '$refresh',
        'commentUpdated' => '$refresh',
    ];

    public function mount(Invoice $invoice): void
    {
        $this->authorize('view', $invoice);
        $this->invoice = $invoice->loadMissing(['comments.user']);
    }

    public function save(): void
    {
        $this->authorize('view', $this->invoice);
        $this->validate();

        $comment = $this->invoice->comments()->create([
            'user_id' => Auth::id(),
            'body' => trim($this->body),
            'is_flagged' => $this->flag_for_attention,
            'flagged_at' => $this->flag_for_attention ? now() : null,
        ]);

        $comment->load('user');

        $this->reset('body', 'flag_for_attention');

        $this->dispatch('commentAdded');
    }

    public function toggleFlag(int $commentId): void
    {
        $comment = $this->invoice->comments()->whereKey($commentId)->firstOrFail();

        if (!Auth::user()->isAdmin() && !Auth::user()->isManager() && $comment->user_id !== Auth::id()) {
            abort(403);
        }

        $comment->is_flagged ? $comment->unflag() : $comment->flag();

        $this->dispatch('commentUpdated');
    }

    public function render()
    {
        return view('livewire.invoice-comments', [
            'comments' => $this->invoice->comments()
                ->with('user')
                ->latest()
                ->get(),
        ]);
    }
}

