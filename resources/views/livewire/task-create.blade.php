<div>
    <button type="button" wire:click="openModal" class="inline-flex items-center gap-1.5 rounded-full bg-orange-500 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-orange-600">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        {{ __('New Task') }}
    </button>

    <x-dialog-modal wire:model.live="open" maxWidth="2xl">
        <x-slot name="title">{{ __('Create a Task') }}</x-slot>
        <x-slot name="content">
            <div class="space-y-4 text-left">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Title') }}</label>
                    <input type="text" wire:model.blur="title" maxlength="255" class="mt-1 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                    @error('title') <p class="mt-1 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Description (Markdown)') }}</label>
                    <textarea wire:model.blur="description" rows="5" class="mt-1 block w-full rounded-2xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200" placeholder="{{ __('Optional. Markdown supported.') }}"></textarea>
                    @error('description') <p class="mt-1 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Type') }}</label>
                        <select wire:model="type" class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @foreach ($types as $t) <option value="{{ $t }}">{{ $t }}</option> @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Priority') }}</label>
                        <select wire:model="priority" class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @foreach ($priorities as $p) <option value="{{ $p }}">{{ $p }}</option> @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Status') }}</label>
                        <select wire:model="status" class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @foreach ($statuses as $s) <option value="{{ $s }}">{{ str_replace('_',' ',$s) }}</option> @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Labels') }}</label>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($allLabels as $label)
                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold transition hover:border-orange-300 hover:bg-orange-50">
                                <input type="checkbox" value="{{ $label->id }}" wire:model="label_ids" class="h-3 w-3 rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-white" style="background-color: {{ $label->color ?: '#94a3b8' }}">{{ $label->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5">
                    <input type="checkbox" wire:model="is_public" class="h-3.5 w-3.5 rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-700">{{ __('Visible to customer') }}</span>
                </label>
            </div>
        </x-slot>
        <x-slot name="footer">
            <button type="button" wire:click="closeModal" class="mr-2 inline-flex items-center rounded-full border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
                {{ __('Cancel') }}
            </button>
            <button type="button" wire:click="save" wire:loading.attr="disabled" class="inline-flex items-center rounded-full bg-orange-500 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-orange-600">
                <span wire:loading wire:target="save" class="mr-1.5 h-3 w-3 animate-spin rounded-full border-2 border-white/80 border-t-transparent"></span>
                {{ __('Create Task') }}
            </button>
        </x-slot>
    </x-dialog-modal>
</div>
