<div>
    <!-- Restore Button -->
    <button wire:click="confirmRestore" class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700 transition hover:bg-emerald-100 hover:border-emerald-300">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
        </svg>
        {!! $buttonText ?? __('Restore') !!}
    </button>

    <!-- Confirmation Modal -->
    @if (isset($confirmingRestore) && $confirmingRestore)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm" wire:click="$set('confirmingRestore', false)">
            <div class="relative w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-xl" wire:click.stop>
                <div class="mb-4 flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100">
                        <svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">
                            {{ __('Confirm Restoration') }}
                        </h3>
                        <p class="text-xs text-slate-500">{{ __('Restore this archived record') }}</p>
                    </div>
                </div>
                
                <p class="mb-6 text-sm text-slate-700">
                    {{ __('This will restore the archived record and make it available again.') }}
                </p>
                
                <div class="flex justify-end gap-3">
                    <button wire:click="$set('confirmingRestore', false)" 
                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        {{ __('Cancel') }}
                    </button>
                    <button wire:click="restore" 
                            class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                        </svg>
                        {{ __('Confirm Restore') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
