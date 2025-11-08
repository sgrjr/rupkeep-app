<div class="bg-white shadow-sm rounded-lg overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="text-lg font-semibold text-gray-900">
            {{ __('Comments & Flags') }}
        </h2>
    </div>

    <div class="p-6 space-y-6">
        <form wire:submit.prevent="save" class="space-y-4">
            <div>
                <label for="comment_body" class="block text-sm font-medium text-gray-700">
                    {{ __('Add a comment') }}
                </label>
                <textarea id="comment_body" wire:model.defer="body"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50"
                          rows="3" placeholder="{{ __('Share details or ask a question…') }}"></textarea>
                @error('body') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </div>

            <div class="flex items-center gap-2">
                <input id="flag_for_attention" type="checkbox" wire:model.defer="flag_for_attention"
                       class="rounded border-gray-300 text-primary focus:ring-primary">
                <label for="flag_for_attention" class="text-sm text-gray-700">
                    {{ __('Flag this invoice for staff attention') }}
                </label>
            </div>

            <div class="flex justify-end">
                <x-button type="submit" wire:loading.attr="disabled">
                    <span wire:loading wire:target="save" class="animate-spin mr-2">&#9696;</span>
                    {{ __('Post comment') }}
                </x-button>
            </div>
        </form>

        <div class="space-y-4">
            @forelse($comments as $comment)
                <div class="border border-gray-200 rounded-md p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">
                                {{ $comment->user->name ?? __('Unknown user') }}
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ $comment->created_at->diffForHumans() }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($comment->is_flagged)
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-700">
                                    {{ __('Flagged') }}
                                </span>
                            @endif

                            @if(auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isManager() || auth()->id() === $comment->user_id))
                                <button type="button" wire:click="toggleFlag({{ $comment->id }})"
                                        class="text-xs underline text-primary">
                                    {{ $comment->is_flagged ? __('Clear flag') : __('Flag') }}
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="mt-3 text-sm text-gray-700 whitespace-pre-line">
                        {{ $comment->body }}
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-600">
                    {{ __('No comments yet. Be the first to leave one!') }}
                </p>
            @endforelse
        </div>
    </div>
</div>

