<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Reports') }}</p>
                <h1 class="text-xl font-semibold text-slate-900">{{ __('Vehicle & Mileage Reports') }}</h1>
                <p class="text-xs text-slate-500">{{ __('Generate reports for tax purposes and fleet management.') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Annual Vehicle Report Card -->
            <a href="{{ route('my.reports.annual-vehicle-report') }}" 
               class="group relative overflow-hidden rounded-3xl border border-orange-100 bg-white/80 p-6 shadow-sm transition hover:-translate-y-1 hover:border-orange-200 hover:shadow-xl">
                <div class="absolute right-0 top-0 h-32 w-32 -translate-y-14 translate-x-10 rounded-full bg-orange-100 opacity-60 blur-3xl transition group-hover:opacity-70"></div>
                <div class="relative">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-orange-100">
                        <svg class="h-6 w-6 text-orange-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m12.75 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900">{{ __('Annual Vehicle Report') }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ __('Total miles, deadhead, personal, and billable miles per vehicle for tax reporting.') }}</p>
                </div>
            </a>
        </div>
    </div>
</x-app-layout>

