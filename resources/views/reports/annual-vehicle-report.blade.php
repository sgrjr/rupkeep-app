<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <a href="{{ route('my.reports.index') }}" class="text-xs font-semibold text-slate-400 hover:text-orange-600">
                    {{ __('← Back to Reports') }}
                </a>
                <h1 class="mt-1 text-xl font-semibold text-slate-900">{{ __('Annual Vehicle Report') }}</h1>
                <p class="text-xs text-slate-500">{{ __('Mileage breakdown per vehicle for tax reporting.') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <!-- Date Range Form -->
        <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
            <form method="GET" action="{{ route('my.reports.annual-vehicle-report') }}" class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="start_date" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Start Date') }}</label>
                    <input type="date" id="start_date" name="start_date" value="{{ $startDate }}" 
                           class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                </div>
                <div>
                    <label for="end_date" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('End Date') }}</label>
                    <input type="date" id="end_date" name="end_date" value="{{ $endDate }}" 
                           class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-orange-600">
                        {{ __('Generate Report') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Report Results -->
        @if(empty($reportData))
            <div class="rounded-3xl border border-slate-200 bg-white/90 p-12 text-center shadow-sm">
                <p class="text-sm text-slate-500">{{ __('No vehicle data found for the selected date range.') }}</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($reportData as $data)
                    <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">{{ $data['vehicle']->name }}</h3>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ __('Report Period') }}: {{ $start->format('M j, Y') }} - {{ $end->format('M j, Y') }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ trans_choice(':count log entry|:count log entries', $data['logs_count']) }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Total Miles') }}</p>
                                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($data['total_miles']) }}</p>
                            </div>
                            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">{{ __('Deadhead Miles') }}</p>
                                <p class="mt-2 text-3xl font-semibold text-amber-700">{{ number_format($data['deadhead_miles']) }}</p>
                            </div>
                            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">{{ __('Personal Miles') }}</p>
                                <p class="mt-2 text-3xl font-semibold text-blue-700">{{ number_format($data['personal_miles']) }}</p>
                            </div>
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">{{ __('Billable Miles') }}</p>
                                <p class="mt-2 text-3xl font-semibold text-emerald-700">{{ number_format($data['billable_miles']) }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>

