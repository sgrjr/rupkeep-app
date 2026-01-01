<label class="inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-[11px] font-semibold transition {{ $isPublic ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-600' }}">
    <input type="checkbox"
           wire:model="isPublic"
           wire:change="toggle"
           class="h-3.5 w-3.5 rounded border-slate-300 text-orange-600 focus:ring-orange-500" />
    <span class="flex items-center gap-1">
        @if($isPublic)
            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
            </svg>
            {{ __('Visible to customer') }}
        @else
            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
            </svg>
            {{ __('Staff only') }}
        @endif
    </span>
</label>

