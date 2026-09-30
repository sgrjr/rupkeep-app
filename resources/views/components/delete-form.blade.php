@props([
    'title' => 'delete',
    'action' => null,
    'redirect_to_route' => false,
    'buttonClass' => 'btn-base btn-action-danger',
    'message' => null,
    'confirmLabel' => null,
])

{{-- Two clicks to delete (TASK-461). The first click swaps the button for a
     short warning with Yes/Cancel; only the second click submits. Plain
     Alpine, no browser dialog, and a `confirmed` field the server can insist
     on. Used for users, contacts and customers. --}}
<form action="{{ $action }}"
      method="post"
      class="inline-block"
      x-data="{ confirming: false }"
      x-on:submit="if (! confirming) { $event.preventDefault(); confirming = true; }"
      x-on:keydown.escape.window="confirming = false">
    @csrf
    <input type="hidden" name="_method" value="delete" />
    <input type="hidden" name="redirect_to_route" value="{{ $redirect_to_route }}" />
    <input type="hidden" name="confirmed" value="1" />

    <button class="{{ $buttonClass }}" type="submit" x-show="! confirming" value="{{ $title }}">
        <x-svg-delete/>{{ $title }}
    </button>

    <div x-show="confirming" x-cloak data-test="delete-confirm"
         class="inline-flex max-w-md flex-wrap items-center gap-2 rounded-2xl border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
        <span class="font-medium">{{ $message ?? __('This cannot be undone.') }}</span>
        <button type="submit" class="rounded-full bg-red-600 px-3 py-1 font-semibold text-white transition hover:bg-red-700">
            {{ $confirmLabel ?? __('Yes, delete') }}
        </button>
        <button type="button" x-on:click="confirming = false" class="rounded-full border border-red-200 bg-white px-3 py-1 font-semibold text-red-600 transition hover:bg-red-100">
            {{ __('Cancel') }}
        </button>
    </div>
</form>
