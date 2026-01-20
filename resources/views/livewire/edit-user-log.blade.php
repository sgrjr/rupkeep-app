@props(['log'=>(Object)[], 'car_drivers' => [], 'vehicles' => [], 'customer_contacts' => [], 'vehicle_positions' => []])

<div class="bg-slate-100/80 pb-32">
    <div class="mx-auto max-w-6xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-orange-500 via-orange-400 to-orange-300 p-6 text-white shadow-xl">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.25),_transparent_60%)] opacity-70"></div>
            <div class="relative flex flex-wrap items-center justify-between gap-4">
                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-white/75">{{ __('Edit Daily Log') }}</p>
                    <h1 class="text-3xl font-bold tracking-tight">{{ $log->job->job_no }}</h1>
                    <div class="flex flex-wrap items-center gap-3 text-sm text-white/85">
                        <a href="{{route('my.customers.show', ['customer'=>$log->job->customer_id])}}" class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold transition hover:bg-white/25">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            {{ $log->job->customer->name }}
                        </a>
                        <a href="{{route('my.jobs.show', ['job'=>$log->job_id])}}" class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold transition hover:bg-white/25">
                            {{ __('Job Summary') }}
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
                <div class="grid gap-3 rounded-2xl border border-white/25 bg-white/10 px-4 py-3 text-xs font-semibold uppercase tracking-wider text-white/85 shadow-sm backdrop-blur sm:grid-cols-3">
                    <div>
                        {{ __('Pickup') }}
                        <p class="mt-1 text-sm font-medium normal-case text-white">
                            {{ \Carbon\Carbon::parse($log->job->scheduled_pickup_at)->format('M j, Y g:i A') }}
                        </p>
                    </div>
                    <div>
                        {{ __('Delivery') }}
                        <p class="mt-1 text-sm font-medium normal-case text-white">
                            {{ \Carbon\Carbon::parse($log->job->scheduled_delivery_at)->format('M j, Y g:i A') }}
                        </p>
                    </div>
                    <div>
                        {{ __('Billable Miles') }}
                        <p class="mt-1 text-sm font-medium normal-case text-white">
                            @php
                                $calculatedBillable = $calculatedBillableMiles ?? ($log->total_billable_miles ?? 0);
                                $overrideValue = $form->billable_miles ?? $log->billable_miles;
                                $displayValue = ($overrideValue !== null && $overrideValue !== '' && (float)$overrideValue != (float)$calculatedBillable) 
                                    ? number_format((float)$overrideValue, 1) . ' (override)'
                                    : number_format($calculatedBillable, 1);
                            @endphp
                            {{ $displayValue }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        @if($log->approval_status === 'pending' && $log->car_driver_id && auth()->user()->id === $log->car_driver_id)
            <section class="rounded-3xl border-2 border-amber-200 bg-amber-50 p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-amber-900">{{ __('Log Assignment Pending Approval') }}</h2>
                        <p class="text-sm text-amber-700">{{ __('This log has been assigned to you. Please confirm or deny this assignment before editing.') }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @can('confirm', $log)
                            <button wire:click="confirmLog" class="inline-flex items-center gap-2 rounded-full bg-emerald-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                {{ __('Confirm') }}
                            </button>
                        @endcan
                        @can('deny', $log)
                            <button wire:click="denyLog" class="inline-flex items-center gap-2 rounded-full bg-red-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                {{ __('Deny') }}
                            </button>
                        @endcan
                    </div>
                </div>
            </section>
        @endif

        @if($log->approval_status === 'denied')
            <section class="rounded-3xl border-2 border-red-200 bg-red-50 p-5 shadow-sm sm:p-6">
                <div class="flex items-center gap-3">
                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    <div>
                        <h2 class="text-lg font-semibold text-red-900">{{ __('Log Denied') }}</h2>
                        <p class="text-sm text-red-700">{{ __('This log has been denied and cannot be edited.') }}</p>
                        @if($log->approved_at)
                            <p class="text-xs text-red-600 mt-1">{{ __('Denied on :date', ['date' => $log->approved_at->format('M j, Y g:i A')]) }}</p>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        <section class="rounded-3xl border border-slate-200 bg-white/90 p-5 shadow-sm sm:p-6">
            <header class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Job Overview') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Customer, schedule, and financial information for this job.') }}</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ __('Rate: :code', ['code' => $log->job->rate_code ?? '—']) }}</span>
            </header>

            <div class="grid gap-4 md:grid-cols-2">
                <article class="space-y-3 text-sm text-slate-600">
                    @can('viewAny', new \App\Models\Organization)
                        <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Organization') }}:</span> <span class="text-slate-900">{{ $log->job->organization->name }}</span></p>
                    @endcan
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Job #') }}:</span> <span class="text-slate-900 font-semibold">{{ $log->job->job_no }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Load #') }}:</span> <span class="text-slate-900">{{ $log->job->load_no ?? '—' }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Customer') }}:</span> <span class="text-slate-900">{{ $log->job->customer?->name ?? '—' }}</span></p>
                    @if($log->job->default_driver_id)
                        <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Default Driver') }}:</span> <span class="text-slate-900">{{ $log->job->defaultDriver?->name ?? '—' }}</span></p>
                    @endif
                    @if($log->job->default_truck_driver_id)
                        <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Default Truck Driver') }}:</span> <span class="text-slate-900">{{ $log->job->defaultTruckDriver?->name ?? '—' }}</span></p>
                    @endif
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Check #') }}:</span> <span class="text-slate-900">{{ $log->job->check_no ?? '—' }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Invoice Paid') }}:</span> <span class="font-semibold {{ $log->job->invoice_paid < 1 ? 'text-red-500' : 'text-emerald-600' }}">{{ $log->job->invoice_paid < 1 ? __('No') : __('Yes') }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Invoice #') }}:</span> <span class="text-slate-900">{{ $log->job->invoice_no ?? '—' }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Rate Value') }}:</span> <span class="text-slate-900">{{ $log->job->rate_value !== null ? '$'.number_format((float) $log->job->rate_value, 2) : '—' }}</span></p>
                    @if($log->job->canceled_at)
                        <div class="rounded-xl border border-red-200 bg-red-50 p-3">
                            <p><span class="text-xs font-semibold uppercase tracking-wide text-red-600">{{ __('Canceled At') }}:</span> <span class="text-red-700">{{ optional($log->job->canceled_at)->format('M j, Y g:i A') }}</span></p>
                            @if($log->job->canceled_reason)
                                <p class="mt-2"><span class="text-xs font-semibold uppercase tracking-wide text-red-600">{{ __('Cancellation Reason') }}:</span> <span class="text-red-700">{{ $log->job->canceled_reason }}</span></p>
                            @endif
                        </div>
                    @endif
                </article>

                <article class="space-y-3 text-sm text-slate-600">
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Pickup') }}:</span> <a class="text-orange-600 hover:text-orange-700" target="_blank" href="http://maps.google.com/?daddr={{$log->job->pickup_address}}">{{ $log->job->pickup_address ?? '—' }}</a></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Pickup Time') }}:</span> <span class="text-slate-900">{{ optional($log->job->scheduled_pickup_at)->format('M j, Y g:i A') ?? $log->job->scheduled_pickup_at ?? '—' }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Delivery') }}:</span> <a class="text-orange-600 hover:text-orange-700" target="_blank" href="http://maps.google.com/?daddr={{$log->job->delivery_address}}">{{ $log->job->delivery_address ?? '—' }}</a></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Delivery Time') }}:</span> <span class="text-slate-900">{{ optional($log->job->scheduled_delivery_at)->format('M j, Y g:i A') ?? $log->job->scheduled_delivery_at ?? '—' }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Memo') }}:</span>
                        @if(str_starts_with($log->job->memo ?? '', 'http'))
                            <a target="_blank" href="{!!$log->job->memo!!}" class="text-orange-600 hover:text-orange-700">{{ __('View Link') }}</a>
                        @else
                            <span class="text-slate-900">{{ $log->job->memo ?? '—' }}</span>
                        @endif
                    </p>
                </article>
            </div>
        </section>

        <form id="user_log_form" wire:submit="saveLog" class="space-y-6" @if($log->approval_status === 'pending' && $log->car_driver_id && auth()->user()->id === $log->car_driver_id) onsubmit="event.preventDefault(); alert('{{ __('Please confirm or deny this log assignment before editing.') }}'); return false;" @endif>
            @csrf

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Section Controls') }}</p>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="openAllSections" class="inline-flex items-center gap-1.5 rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        {{ __('Open All') }}
                    </button>
                    <button type="button" wire:click="closeAllSections" class="inline-flex items-center gap-1.5 rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                        {{ __('Close All') }}
                    </button>
                </div>
            </div>

            <!-- Desktop Save Button (sticky at bottom) - Hidden on mobile -->
            <div class="hidden sm:sticky sm:bottom-6 sm:z-30 sm:flex sm:flex-col sm:items-stretch sm:gap-3 sm:rounded-3xl sm:border sm:border-slate-200 sm:bg-white/90 sm:px-6 sm:py-4 sm:shadow-lg sm:backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    @if (session()->has('error'))
                        <div class="inline-flex items-center gap-2 rounded-full border border-red-200 bg-red-50 px-3 py-1 text-xs font-semibold text-red-600">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75M12 17h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ session('error') }}
                        </div>
                    @endif
                    <span wire:loading class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500">
                        <span class="h-2 w-2 animate-pulse rounded-full bg-orange-400"></span>
                        {{ __('Saving…') }}
                    </span>
                    <x-action-message class="inline-flex items-center gap-2 text-xs font-semibold text-emerald-600" on="saved">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        {{ __('Saved!') }}
                    </x-action-message>
                </div>
                <x-button type="submit" class="w-full justify-center sm:w-auto">
                    <span wire:loading wire:target="saveLog" class="h-4 w-4 animate-spin border-2 border-white/80 border-t-transparent rounded-full"></span>
                    {{ __('Save Changes') }}
                </x-button>
            </div>

            <section class="rounded-3xl border border-slate-200 bg-white/90 p-5 shadow-sm sm:p-6">
                <details {{ $isDriverVehicleOpen ? 'open' : '' }} class="group space-y-5">
                    @php
                        $driverName = $log->user?->name ?? __('Unassigned');
                        $vehicleName = $log->vehicle?->name ?? __('No vehicle');
                        $driverVehicleSummary = __('Driver & Vehicle') . ': ' . $driverName . ' • ' . $vehicleName;
                        $tripTimingSummary = implode(' → ', array_filter([
                            optional($log->started_at)->format('M j g:ia'),
                            optional($log->ended_at)->format('M j g:ia'),
                        ]));
                        $mileageSummary = __('Miles') . ': ' . ($log->total_miles ?? '—') . ' • ' . __('Billable') . ': ' . ($log->billable_miles ?? '—');
                        $expenseSummary = __('Tolls') . ': ' . ($log->tolls ?? 0) . ' • ' . __('Hotel') . ': ' . ($log->hotel ?? 0);
                        $loadSummary = __('Truck') . ': ' . ($log->truck_no ?? '—') . ' • ' . __('Trailer') . ': ' . ($log->trailer_no ?? '—');
                        $attachmentsCount = $log->attachments?->count() ?? 0;
                    @endphp
                    <summary wire:click="$toggle('isDriverVehicleOpen')" class="flex flex-wrap cursor-pointer items-center justify-between gap-3 border-b border-slate-100 pb-3 text-lg font-semibold text-slate-900 sm:flex-nowrap">
                        <span>{{ $driverVehicleSummary }}</span>
                        <svg class="h-4 w-4 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                    </summary>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="car_driver_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Escort Driver') }}</label>
                            <select id="car_driver_id" wire:model.blur="form.car_driver_id" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                <option value="">{{ __('Select Driver') }}</option>
                                @foreach($car_drivers as $car_driver)
                                    <option value="{{ $car_driver['value'] }}">{{ $car_driver['name'] }}</option>
                                @endforeach
                            </select>
                            @error('form.car_driver_id') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="vehicle_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Vehicle') }}</label>
                            <select id="vehicle_id" wire:model.blur="form.vehicle_id" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                <option value="">{{ __('Select Vehicle') }}</option>
                                @foreach($vehicles as $vehicle)
                                    <option value="{{ $vehicle['value'] }}">{{ $vehicle['name'] }}</option>
                                @endforeach
                            </select>
                            @error('form.vehicle_id') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="vehicle_position" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Vehicle Position') }}</label>
                            <select id="vehicle_position" wire:model.blur="form.vehicle_position" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                <option value="">{{ __('Select Position') }}</option>
                                @foreach($vehicle_positions as $position)
                                    <option value="{{ $position['value'] }}">{{ $position['name'] }}</option>
                                @endforeach
                            </select>
                            @error('form.vehicle_position') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                </details>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white/90 p-5 shadow-sm sm:p-6">
                <details {{ $isTripTimingOpen ? 'open' : '' }} class="group space-y-5">
                    <summary wire:click="$toggle('isTripTimingOpen')" class="flex cursor-pointer items-center justify-between gap-4 border-b border-slate-100 pb-3 text-lg font-semibold text-slate-900">
                        <span>{{ __('Trip Timing') }} @if($tripTimingSummary) <span class="text-sm font-normal text-slate-500">— {{ $tripTimingSummary }}</span> @endif</span>
                        <svg class="h-4 w-4 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                    </summary>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="started_at" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Started At') }}</label>
                            <input type="datetime-local" id="started_at" wire:model.blur="form.started_at" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('form.started_at') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="ended_at" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Ended At') }}</label>
                            <input type="datetime-local" id="ended_at" wire:model.blur="form.ended_at" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('form.ended_at') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </details>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white/90 p-5 shadow-sm sm:p-6">
                <details {{ $isMileageDetailsOpen ? 'open' : '' }} class="group space-y-5">
                    <summary wire:click="$toggle('isMileageDetailsOpen')" class="flex cursor-pointer items-center justify-between gap-4 border-b border-slate-100 pb-3 text-lg font-semibold text-slate-900">
                        <span>{{ __('Mileage & Stops') }} <span class="text-sm font-normal text-slate-500">— {{ $mileageSummary }}</span></span>
                        <svg class="h-4 w-4 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                    </summary>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label for="start_mileage" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Start Mileage') }}</label>
                            <input type="number" id="start_mileage" wire:model.blur="form.start_mileage" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('form.start_mileage') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="start_job_mileage" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Start Job Mileage') }}</label>
                            <input type="number" id="start_job_mileage" wire:model.blur="form.start_job_mileage" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('form.start_job_mileage') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="end_job_mileage" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('End Job Mileage') }}</label>
                            <input type="number" id="end_job_mileage" wire:model.blur="form.end_job_mileage" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('form.end_job_mileage') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="end_mileage" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('End Mileage') }}</label>
                            <input type="number" id="end_mileage" wire:model.blur="form.end_mileage" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('form.end_mileage') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="billable_miles" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Billable Miles') }}</label>
                            <div class="mt-2 space-y-2">
                                @php
                                    $calculatedBillable = $calculatedBillableMiles ?? ($log->total_billable_miles ?? 0);
                                    $overrideValue = $form->billable_miles ?? $log->billable_miles;
                                    $hasOverride = $overrideValue !== null && $overrideValue !== '' && (float)$overrideValue != (float)$calculatedBillable;
                                    $overrideDiff = $hasOverride ? ((float)$overrideValue - (float)$calculatedBillable) : 0;
                                @endphp
                                
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div class="rounded-lg border {{ $hasOverride ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50' }} px-3 py-2 text-xs">
                                        <p class="font-semibold {{ $hasOverride ? 'text-amber-700' : 'text-slate-700' }}">
                                            {{ $hasOverride ? __('Manual Override') : __('Calculated Value') }}
                                        </p>
                                        <p class="{{ $hasOverride ? 'text-amber-600' : 'text-slate-600' }}">
                                            {{ number_format($calculatedBillable, 1) }} {{ __('miles') }}
                                        </p>
                                    </div>
                                    @if($hasOverride)
                                        <div class="rounded-lg border border-orange-200 bg-orange-50 px-3 py-2 text-xs">
                                            <p class="font-semibold text-orange-700">{{ __('Override Value') }}</p>
                                            <p class="text-orange-600">
                                                {{ number_format((float)$overrideValue, 1) }} {{ __('miles') }}
                                                @if($overrideDiff != 0)
                                                    <span class="ml-1 {{ $overrideDiff > 0 ? 'text-red-600' : 'text-emerald-600' }}">
                                                        ({{ $overrideDiff > 0 ? '+' : '' }}{{ number_format($overrideDiff, 1) }})
                                                    </span>
                                                @endif
                                            </p>
                                        </div>
                                    @endif
                                </div>
                                
                                <div class="flex gap-2">
                                    <input type="number" id="billable_miles" wire:model.blur="form.billable_miles" step="0.1" min="0" placeholder="{{ __('Leave blank for calculated value') }}" class="flex-1 rounded-xl border {{ $hasOverride ? 'border-orange-300' : 'border-slate-200' }} px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                    @if($hasOverride)
                                        <button type="button" wire:click="$set('form.billable_miles', null)" class="inline-flex items-center gap-1 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-slate-400 hover:bg-slate-50" title="{{ __('Clear override and use calculated value') }}">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                            {{ __('Clear') }}
                                        </button>
                                    @endif
                                </div>
                            </div>
                            <p class="mt-1 text-xs text-slate-400">
                                {{ $hasOverride ? __('Override is active. Click "Clear" to use the calculated value.') : __('Enter a value to manually override the calculated billable miles.') }}
                            </p>
                            @error('form.billable_miles') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="is_deadhead" class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2">
                                <input type="checkbox" id="is_deadhead" wire:model.blur="form.is_deadhead" class="h-4 w-4 rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Deadhead Run') }}</span>
                            </label>
                            <p class="mt-1 text-xs text-slate-400">{{ __('Check if this is a deadhead (empty return) trip. Each deadhead log counts as one deadhead leg.') }}</p>
                            @error('form.is_deadhead') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="extra_load_stops_count" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Extra Load Stops') }}</label>
                            <input type="number" id="extra_load_stops_count" wire:model.blur="form.extra_load_stops_count" min="0" step="1" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('form.extra_load_stops_count') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </details>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white/90 p-5 shadow-sm sm:p-6">
                <details {{ $isExpenseDetailsOpen ? 'open' : '' }} class="group space-y-5">
                    <summary wire:click="$toggle('isExpenseDetailsOpen')" class="flex cursor-pointer items-center justify-between gap-4 border-b border-slate-100 pb-3 text-lg font-semibold text-slate-900">
                        <span>{{ __('Expenses') }} <span class="text-sm font-normal text-slate-500">— {{ $expenseSummary }}</span></span>
                        <svg class="h-4 w-4 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                    </summary>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label for="tolls" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Tolls') }}</label>
                            <input type="number" id="tolls" wire:model.blur="form.tolls" step="0.01" min="0" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('form.tolls') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="hotel" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Hotel') }}</label>
                            <input type="number" id="hotel" wire:model.blur="form.hotel" step="0.01" min="0" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('form.hotel') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="wait_time_hours" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Wait Time (hrs)') }}</label>
                            <input type="number" id="wait_time_hours" wire:model.blur="form.wait_time_hours" step="0.25" min="0" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('form.wait_time_hours') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="extra_charge" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Extra Charge') }}</label>
                            <input type="number" id="extra_charge" wire:model.blur="form.extra_charge" step="0.01" min="0" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('form.extra_charge') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </details>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white/90 p-5 shadow-sm sm:p-6">
                <details {{ $isLoadInformationOpen ? 'open' : '' }} class="group space-y-5">
                    <summary wire:click="$toggle('isLoadInformationOpen')" class="flex cursor-pointer items-center justify-between gap-4 border-b border-slate-100 pb-3 text-lg font-semibold text-slate-900">
                        <span>{{ __('Load Information') }} <span class="text-sm font-normal text-slate-500">— {{ $loadSummary }}</span></span>
                        <svg class="h-4 w-4 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                    </summary>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="truck_driver_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Truck Driver') }}</label>
                            <select id="truck_driver_id" wire:model.blur="form.truck_driver_id" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                <option value="">{{ __('Select Truck Driver') }}</option>
                                @foreach($customer_contacts as $contact)
                                    <option value="{{ $contact['value'] }}">{{ $contact['name'] }}</option>
                                @endforeach
                            </select>
                            @error('form.truck_driver_id') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="truck_no" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Truck #') }}</label>
                            <input type="text" id="truck_no" wire:model.blur="form.truck_no" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('form.truck_no') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="trailer_no" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Trailer #') }}</label>
                            <input type="text" id="trailer_no" wire:model.blur="form.trailer_no" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('form.trailer_no') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <details class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <summary class="cursor-pointer text-sm font-semibold text-orange-600 hover:text-orange-700">{{ __('New Truck Driver') }}</summary>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                                    <div>
                                        <label for="new_truck_driver_name" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Name') }}</label>
                                        <input type="text" id="new_truck_driver_name" wire:model.blur="form.new_truck_driver_name" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                        @error('form.new_truck_driver_name') <p class="mt-1 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="new_truck_driver_phone" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Phone') }}</label>
                                        <input type="tel" id="new_truck_driver_phone" wire:model.blur="form.new_truck_driver_phone" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                        @error('form.new_truck_driver_phone') <p class="mt-1 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="md:col-span-3">
                                        <label for="new_truck_driver_memo" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Notes') }}</label>
                                        <textarea id="new_truck_driver_memo" wire:model.blur="form.new_truck_driver_memo" rows="2" class="mt-2 block w-full rounded-2xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"></textarea>
                                        @error('form.new_truck_driver_memo') <p class="mt-1 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            </details>
                        </div>
                        <div class="md:col-span-2">
                            <label for="memo" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Log Memo (Internal)') }}</label>
                            <textarea id="memo" wire:model.blur="form.memo" rows="4" class="mt-2 block w-full rounded-2xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"></textarea>
                            <p class="mt-1 text-xs text-slate-400">{{ __('This memo is internal and private. It will NOT be displayed on invoices. Only organization users can view this. To add notes that appear on invoices, use the job-level public memo field.') }}</p>
                            @error('form.memo') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </details>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white/90 p-5 shadow-sm sm:p-6">
                <details {{ $isAttachmentsOpen ? 'open' : '' }} class="group space-y-5">
                    <summary wire:click="$toggle('isAttachmentsOpen')" class="flex cursor-pointer items-center justify-between gap-4 border-b border-slate-100 pb-3 text-lg font-semibold text-slate-900">
                        <span>{{ __('Attachments & Proof') }} <span class="text-sm font-normal text-slate-500">— {{ trans_choice(':count attachment|:count attachments', $attachmentsCount) }}</span></span>
                        <svg class="h-4 w-4 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                    </summary>
                    <div class="space-y-4">
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <input type="file" wire:model="file" class="grow rounded-xl border border-dashed border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:outline-none" />
                                <x-button type="button" wire:click="uploadFile" wire:loading.attr="disabled" class="w-full justify-center sm:w-auto">
                                    <span wire:loading wire:target="uploadFile" class="h-4 w-4 animate-spin border-2 border-white/80 border-t-transparent rounded-full"></span>
                                    {{ __('Upload Attachment') }}
                                </x-button>
                                <x-action-message class="text-xs font-semibold text-emerald-600" on="uploaded">
                                    {{ __('File Uploaded.') }}
                                </x-action-message>
                                @error('file') <p class="text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2">
                                <input type="checkbox" id="isPublicUpload" wire:model="isPublicUpload" 
                                       class="h-4 w-4 rounded border-slate-300 text-orange-600 focus:ring-orange-500" />
                                <label for="isPublicUpload" class="flex-1 cursor-pointer text-xs text-slate-700">
                                    <span class="font-semibold">{{ __('Make visible to customer') }}</span>
                                    <p class="mt-0.5 text-[10px] text-slate-500">{{ __('This file will be visible in the customer portal') }}</p>
                                </label>
                            </div>
                        </div>
                        <div class="space-y-3">
                            @forelse($log->attachments as $att)
                                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border px-4 py-3 shadow-sm {{ $att->is_public ? 'border-emerald-200 bg-emerald-50/30' : 'border-slate-200 bg-white' }}">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <a class="text-sm font-semibold text-orange-600 hover:text-orange-700" href="{{route('attachments.download', ['attachment'=>$att->id])}}">
                                                {{ $att->file_name }}
                                            </a>
                                            @if($att->is_public)
                                                <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                                    </svg>
                                                    {{ __('Public') }}
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-slate-500">{{ $att->created_at->diffForHumans() }}</p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        @can('updateVisibility', $att)
                                            <livewire:attachment-visibility-toggle :attachment="$att" :key="'log-att-'.$att->id"/>
                                        @else
                                            @if($att->is_public)
                                                <span class="text-xs font-semibold text-emerald-600">{{ __('Visible to customer') }}</span>
                                            @else
                                                <span class="text-xs font-semibold text-slate-500">{{ __('Staff only') }}</span>
                                            @endif
                                        @endcan
                                        <livewire:delete-confirmation-button
                                            :action-url="route('attachments.destroy', ['attachment'=> $att->id])"
                                            button-text="&times;"
                                            :redirect-route="Route::currentRouteName()"
                                            :model-class="\App\Models\Attachment::class"
                                            :record-id="$att->id"
                                            resource="attachments"
                                        />
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-slate-500">{{ __('No attachments yet. Upload photos of permits, route sheets, or receipts above.') }}</p>
                            @endforelse
                        </div>
                    </div>
                </details>
            </section>
        </form>
    </div>

    <!-- Fixed Save Button for Mobile -->
    <button 
        type="button"
        wire:loading.attr="disabled"
        wire:target="saveLog"
        class="fixed bottom-6 right-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-orange-600 text-white shadow-lg transition hover:bg-orange-700 active:scale-95 sm:hidden"
        @if($log->approval_status === 'pending' && $log->car_driver_id && auth()->user()->id === $log->car_driver_id) 
            onclick="event.preventDefault(); alert('{{ __('Please confirm or deny this log assignment before editing.') }}'); return false;"
        @else
            onclick="document.getElementById('user_log_form').requestSubmit();"
        @endif
        title="{{ __('Save Changes') }}"
    >
        <svg wire:loading.remove wire:target="saveLog" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
        </svg>
        <svg wire:loading wire:target="saveLog" class="h-6 w-6 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </button>
</div>

@script
<script>
    document.addEventListener('livewire:init', () => {
        const memo = document.getElementById('memo');
        if (memo) {
            memo.style.height = memo.scrollHeight + 'px';
            memo.addEventListener('input', () => {
                memo.style.height = 'auto';
                memo.style.height = memo.scrollHeight + 'px';
            });
        }
    });
</script>
@endscript