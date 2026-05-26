<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-bold tracking-tight text-slate-900">{{ __('My Requests') }}</h2>
            <p class="text-xs text-slate-500">{{ __('Your submitted feedback and tasks the team is tracking publicly.') }}</p>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            <livewire:task-list :portal="true" />
        </div>
    </div>
</x-app-layout>
