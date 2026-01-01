<div>
    <!-- Modal -->
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" 
             wire:click="closeModal" 
             x-data="{ show: @entangle('showModal') }" 
             x-show="show" 
             x-transition>
            <div class="w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-3xl border border-slate-200 bg-white shadow-xl" wire:click.stop>
                <div class="sticky top-0 z-10 border-b border-slate-200 bg-gradient-to-r from-orange-500 via-orange-400 to-orange-300 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-white">{{ __('Annual Vehicle Report') }}</h3>
                            <p class="mt-1 text-xs text-white/80">{{ __('Mileage breakdown for tax reporting') }}</p>
                        </div>
                        <button type="button" wire:click="closeModal" class="text-white/80 hover:text-white">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    <!-- Year Selection Form -->
                    <div class="mb-6 rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                        <form wire:submit.prevent="generateReport" class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="year_select" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Year') }}</label>
                                <select id="year_select" wire:model="selectedYear" 
                                        class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                    @foreach($this->years as $year)
                                        <option value="{{ $year }}">{{ $year }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-end sm:col-span-2">
                                <button type="submit" 
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-orange-600">
                                    {{ __('Update Report') }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Report Results -->
                    @if(empty($reportData))
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-12 text-center">
                            <p class="text-sm text-slate-500">{{ __('No vehicle data found for :year.', ['year' => $selectedYear ?? now()->year]) }}</p>
                            <div class="mt-4 space-y-2 text-xs text-slate-400">
                                <p>{{ __('This report combines data from driver work logs (UserLog entries) and maintenance records.') }}</p>
                                <p>{{ __('To see data here, you need:') }}</p>
                                <ul class="mt-2 list-inside list-disc space-y-1 text-left">
                                    <li>{{ __('Driver work logs (UserLog) with a vehicle assigned, OR') }}</li>
                                    <li>{{ __('Maintenance records with performed_at date in :year', ['year' => $selectedYear ?? now()->year]) }}</li>
                                </ul>
                            </div>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach($reportData as $data)
                                <div class="rounded-2xl border border-slate-200 bg-white/90 p-5 shadow-sm">
                                    <div class="mb-4">
                                        <h4 class="text-base font-semibold text-slate-900">{{ $data['vehicle']->name }}</h4>
                                        <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                            @if($data['logs_count'] > 0)
                                                <span>{{ trans_choice(':count log entry|:count log entries', $data['logs_count']) }}</span>
                                            @endif
                                            @if($data['maintenance_count'] > 0)
                                                <span>• {{ trans_choice(':count maintenance record|:count maintenance records', $data['maintenance_count']) }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Mileage Data (from UserLogs) -->
                                    @if($data['logs_count'] > 0)
                                    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Total Miles') }}</p>
                                            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($data['total_miles']) }}</p>
                                        </div>
                                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">{{ __('Deadhead Miles') }}</p>
                                            <p class="mt-1 text-2xl font-semibold text-amber-700">{{ number_format($data['deadhead_miles']) }}</p>
                                        </div>
                                        <div class="rounded-xl border border-blue-200 bg-blue-50 p-3">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">{{ __('Personal Miles') }}</p>
                                            <p class="mt-1 text-2xl font-semibold text-blue-700">{{ number_format($data['personal_miles']) }}</p>
                                        </div>
                                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">{{ __('Billable Miles') }}</p>
                                            <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ number_format($data['billable_miles']) }}</p>
                                        </div>
                                    </div>
                                    @endif

                                    <!-- Maintenance Data -->
                                    @if($data['maintenance_count'] > 0)
                                    <div class="mt-4 rounded-xl border border-purple-200 bg-purple-50 p-4">
                                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-purple-600">{{ __('Maintenance Summary') }}</p>
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <p class="text-xs text-purple-700">{{ __('Total Maintenance Cost') }}</p>
                                                <p class="mt-1 text-xl font-semibold text-purple-900">${{ number_format($data['maintenance_total_cost'], 2) }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-purple-700">{{ __('Maintenance Records') }}</p>
                                                <p class="mt-1 text-xl font-semibold text-purple-900">{{ $data['maintenance_count'] }}</p>
                                            </div>
                                        </div>
                                        @if($data['maintenance_by_type']->isNotEmpty())
                                        <div class="mt-3">
                                            <p class="mb-2 text-xs font-semibold text-purple-700">{{ __('By Type') }}</p>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($data['maintenance_by_type'] as $type => $count)
                                                    <span class="inline-flex items-center gap-1 rounded-full border border-purple-200 bg-white px-2 py-1 text-xs font-semibold text-purple-700">
                                                        {{ ucfirst(str_replace('_', ' ', $type)) }}: {{ $count }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
