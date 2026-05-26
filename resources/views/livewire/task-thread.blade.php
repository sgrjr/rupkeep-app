<section class="rounded-3xl border border-slate-200 bg-white/90 p-5 shadow-sm sm:p-6">
    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-600">{{ __('Discussion & activity') }}</h2>

    <ul class="mt-4 space-y-3">
        @forelse ($comments as $c)
            @php $isSystem = $c->isSystem(); @endphp
            <li @class([
                'rounded-2xl border px-4 py-3 text-sm shadow-sm',
                'border-slate-200 bg-white' => !$isSystem && !$c->is_internal,
                'border-amber-200 bg-amber-50/60' => !$isSystem && $c->is_internal,
                'border-slate-100 bg-slate-50 text-slate-500' => $isSystem,
            ])>
                <div class="flex flex-wrap items-center justify-between gap-2 text-[11px] uppercase tracking-wide">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-slate-700">{{ $c->user?->name ?? ($isSystem ? __('System') : __('Anonymous')) }}</span>
                        @if ($isSystem)
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 font-semibold text-slate-500">{{ str_replace('_',' ',$c->event_type) }}</span>
                        @endif
                        @if ($c->is_internal)
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-700">{{ __('internal') }}</span>
                        @endif
                        @if ($c->sent_to_customer)
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-700" title="{{ __('Customer was emailed') }}">{{ __('✉ sent') }}</span>
                        @endif
                    </div>
                    <span class="text-slate-400">{{ $c->created_at?->diffForHumans() }}</span>
                </div>
                @if ($c->body !== '')
                    <div class="prose prose-sm mt-2 max-w-none text-slate-700">{!! \Illuminate\Support\Str::markdown($c->body) !!}</div>
                @endif
                @if ($canSendCustomerUpdate && !$isSystem && !$c->is_internal && !$c->sent_to_customer)
                    <div class="mt-2">
                        <button type="button" wire:click="sendCustomerUpdate({{ $c->id }})" class="inline-flex items-center gap-1.5 rounded-full border border-orange-200 bg-orange-50 px-2.5 py-1 text-[11px] font-semibold text-orange-700 transition hover:bg-orange-100">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M3 8v8a2 2 0 002 2h14a2 2 0 002-2V8M3 8l9-5 9 5"/></svg>
                            {{ __('Send customer update') }}
                        </button>
                    </div>
                @endif
            </li>
        @empty
            <li class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                {{ __('No comments yet. Start the conversation.') }}
            </li>
        @endforelse
    </ul>

    {{-- Comment form --}}
    @can('comment', $task)
        <div class="mt-5 space-y-2">
            <label for="task-comment-body" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Add a comment') }}</label>
            <textarea id="task-comment-body" wire:model.blur="body" rows="3" class="block w-full rounded-2xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200" placeholder="{{ __('Markdown supported.') }}"></textarea>
            @error('body') <p class="text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
            <div class="flex flex-wrap items-center justify-between gap-2">
                @if ($canCommentInternal)
                    <label class="inline-flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800">
                        <input type="checkbox" wire:model="is_internal" class="h-3.5 w-3.5 rounded border-amber-300 text-amber-600 focus:ring-amber-500">
                        {{ __('Internal — hide from customer') }}
                    </label>
                @else
                    <span></span>
                @endif
                <button type="button" wire:click="save" class="inline-flex items-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-orange-600">
                    <span wire:loading wire:target="save" class="h-4 w-4 animate-spin rounded-full border-2 border-white/80 border-t-transparent"></span>
                    {{ __('Post comment') }}
                </button>
            </div>
        </div>
    @endcan
</section>
