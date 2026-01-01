<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">{{ __('Send Feedback') }}</h1>
                <p class="text-xs text-slate-500">{{ __('Share your thoughts, report issues, or suggest improvements.') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-center">
            <div class="flex-1 lg:max-w-2xl">
                <livewire:feedback-form :inline="true" />
            </div>
            <div class="flex-1 lg:max-w-md lg:flex-shrink-0">
                <img src="/images/feedback.png" alt="{{ __('Feedback illustration') }}" class="w-full h-auto rounded-2xl shadow-sm" />
            </div>
        </div>
    </div>
</x-app-layout>

