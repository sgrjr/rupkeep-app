<div class="rounded-2xl border border-yellow-300 bg-yellow-100 p-4 text-yellow-900 shadow-sm" style="transform: rotate(-0.4deg);">
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-2">
            <svg class="h-5 w-5 text-yellow-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m-3-3v.75m0-.75a3 3 0 00-3 3v1.5m3-4.5H18M9 12.75H4.5a2.25 2.25 0 00-2.25 2.25v3A2.25 2.25 0 004.5 20.25h3a2.25 2.25 0 002.25-2.25v-3A2.25 2.25 0 007.5 12.75H9zm0 0V9a3 3 0 013-3h1.5"/></svg>
            <p class="font-semibold">{{ __('Sticky note: where the SSH credentials live') }}</p>
        </div>
        @unless($editing)
            <button type="button" wire:click="edit"
                    class="rounded-full border border-yellow-400 bg-white/70 px-3 py-1 text-xs font-semibold text-yellow-800 transition hover:bg-white">
                {{ $body === '' ? __('Write it down') : __('Edit') }}
            </button>
        @endunless
    </div>

    @if($editing)
        <form wire:submit="save" class="mt-3 space-y-3">
            <textarea wire:model="body" rows="4"
                      placeholder="{{ __('e.g. Bitwarden → Servers → pilotcar.io (user + key). Point to where they are kept; never paste the password or key itself here.') }}"
                      class="block w-full rounded-xl border border-yellow-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-yellow-500 focus:outline-none focus:ring-2 focus:ring-yellow-200"></textarea>
            @error('body')
                <p class="text-xs font-semibold text-red-600">{{ $message }}</p>
            @enderror
            <div class="flex items-center gap-2">
                <button type="submit" class="rounded-full bg-yellow-500 px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-white shadow-sm transition hover:bg-yellow-600">
                    {{ __('Save note') }}
                </button>
                <button type="button" wire:click="cancel" class="rounded-full border border-yellow-400 bg-white/70 px-4 py-1.5 text-xs font-semibold text-yellow-800 transition hover:bg-white">
                    {{ __('Cancel') }}
                </button>
            </div>
        </form>
    @else
        @if($body === '')
            <p class="mt-3 text-sm italic text-yellow-800/80">
                {{ __('Nothing written yet. Say where the SSH login and key are kept so you never have to hunt for them again. Point to the place; do not paste the secret itself.') }}
            </p>
        @else
            <p class="mt-3 whitespace-pre-wrap text-sm">{{ $body }}</p>
            @if($updatedBy || $updatedAt)
                <p class="mt-2 text-[11px] text-yellow-800/70">
                    {{ __('Updated :when by :who', ['when' => $updatedAt ?? '', 'who' => $updatedBy ?? __('unknown')]) }}
                </p>
            @endif
        @endif
    @endif
</div>
