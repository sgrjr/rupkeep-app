<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold tracking-tight text-slate-900">{{ __('Dispatch') }}</h2>
                <p class="text-xs text-slate-500">{{ __('Feature requests, bugs, tech debt — every piece of work flows through here.') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <livewire:task-create />
                <a href="{{ route('tasks.board') }}" class="inline-flex items-center gap-1.5 rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h6v12H4zM14 6h6v8h-6zM14 16h6v2h-6z"/></svg>
                    {{ __('Board') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            <livewire:task-list :portal="false" />
        </div>
    </div>
</x-app-layout>
