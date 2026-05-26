<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('portal.tasks.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-orange-600 hover:text-orange-700">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ __('Back to my requests') }}
        </a>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <livewire:task-show :task="$task" :portal="true" :key="'portal-task-show-'.$task->id"/>
        </div>
    </div>
</x-app-layout>
