<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-bold tracking-tight text-slate-900">{{ __('Task Board') }}</h2>
            <p class="text-xs text-slate-500">{{ __('Kanban view — coming soon in Phase 10.') }}</p>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl border border-dashed border-slate-300 bg-white/60 p-12 text-center text-sm text-slate-500">
                {{ __('Drag-and-drop kanban board will live here.') }}
                <div class="mt-4">
                    <a href="{{ route('tasks.index') }}" class="inline-flex items-center gap-1.5 rounded-full bg-orange-500 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-orange-600">
                        {{ __('Back to list') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
