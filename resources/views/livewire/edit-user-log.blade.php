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
                <div class="grid gap-3 rounded-2xl border border-white/25 bg-white/10 px-4 py-3 text-xs font-semibold uppercase tracking-wider text-white/85 shadow-sm backdrop-blur sm:grid-cols-2">
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
                </div>
            </div>
        </section>

        <form id="user_log_form" wire:submit="saveLog" class="space-y-6">
            @csrf

            <div class="sticky bottom-6 z-30 flex items-center justify-between gap-3 rounded-3xl border border-slate-200 bg-white/90 px-4 py-4 shadow-lg backdrop-blur sm:px-6">
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
                <x-button type="submit">
                    <span wire:loading wire:target="saveLog" class="h-4 w-4 animate-spin border-2 border-white/80 border-t-transparent rounded-full"></span>
                    {{ __('Save Changes') }}
                </x-button>
            </div>

            <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
                <details {{ $isDriverVehicleOpen ? 'open' : '' }} class="group space-y-5">
                    <summary wire:click="$toggle('isDriverVehicleOpen')" class="flex cursor-pointer items-center justify-between gap-4 border-b border-slate-100 pb-3 text-lg font-semibold text-slate-900">
                        {{ __('Driver & Vehicle') }}
                        <svg class="h-4 w-4 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                    </summary>
                    <div class="grid gap-4 md:grid-cols-2">
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
                    </div>
                    <details class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <summary class="cursor-pointer text-sm font-semibold text-orange-600 hover:text-orange-700">{{ __('New Truck Driver') }}</summary>
                        <div class="mt-3 grid gap-3 md:grid-cols-3">
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
                </details>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
                <details {{ $isTripTimingOpen ? 'open' : '' }} class="group space-y-5">
                    <summary wire:click="$toggle('isTripTimingOpen')" class="flex cursor-pointer items-center justify-between gap-4 border-b border-slate-100 pb-3 text-lg font-semibold text-slate-900">
                        {{ __('Trip Timing') }}
                        <svg class="h-4 w-4 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                    </summary>
                    <div class="grid gap-4 md:grid-cols-2">
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

            <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
                <details {{ $isMileageDetailsOpen ? 'open' : '' }} class="group space-y-5">
                    <summary wire:click="$toggle('isMileageDetailsOpen')" class="flex cursor-pointer items-center justify-between gap-4 border-b border-slate-100 pb-3 text-lg font-semibold text-slate-900">
                        {{ __('Mileage & Stops') }}
                        <svg class="h-4 w-4 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                    </summary>
                    <div class="grid gap-4 md:grid-cols-4">
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
                            <label for="total_miles" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Total Miles') }}</label>
                            <input type="number" id="total_miles" wire:model.blur="form.total_miles" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('form.total_miles') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="billable_miles" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Billable Miles Override') }}</label>
                            <input type="number" id="billable_miles" wire:model.blur="form.billable_miles" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('form.billable_miles') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="dead_head_times" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Deadhead Count') }}</label>
                            <input type="number" id="dead_head_times" wire:model.blur="form.dead_head_times" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('form.dead_head_times') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="extra_load_stops" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Extra Load Stops') }}</label>
                            <input type="number" id="extra_load_stops" wire:model.blur="form.extra_load_stops" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('form.extra_load_stops') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </details>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
                <details {{ $isExpenseDetailsOpen ? 'open' : '' }} class="group space-y-5">
                    <summary wire:click="$toggle('isExpenseDetailsOpen')" class="flex cursor-pointer items-center justify-between gap-4 border-b border-slate-100 pb-3 text-lg font-semibold text-slate-900">
                        {{ __('Expenses') }}
                        <svg class="h-4 w-4 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                    </summary>
                    <div class="grid gap-4 md:grid-cols-4">
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
                            <label for="wait_time" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Wait Time (hrs)') }}</label>
                            <input type="number" id="wait_time" wire:model.blur="form.wait_time" step="0.25" min="0" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('form.wait_time') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="extra_charge" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Extra Charge') }}</label>
                            <input type="number" id="extra_charge" wire:model.blur="form.extra_charge" step="0.01" min="0" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('form.extra_charge') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </details>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
                <details {{ $isLoadInformationOpen ? 'open' : '' }} class="group space-y-5">
                    <summary wire:click="$toggle('isLoadInformationOpen')" class="flex cursor-pointer items-center justify-between gap-4 border-b border-slate-100 pb-3 text-lg font-semibold text-slate-900">
                        {{ __('Load Information') }}
                        <svg class="h-4 w-4 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                    </summary>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="trailer_number" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Trailer #') }}</label>
                            <input type="text" id="trailer_number" wire:model.blur="form.trailer_number" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('form.trailer_number') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="truck_number" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Truck #') }}</label>
                            <input type="text" id="truck_number" wire:model.blur="form.truck_number" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('form.truck_number') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label for="memo" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Job Memo / Notes') }}</label>
                            <textarea id="memo" wire:model.blur="form.memo" rows="4" class="mt-2 block w-full rounded-2xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"></textarea>
                            @error('form.memo') <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </details>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
                <details {{ $isAttachmentsOpen ? 'open' : '' }} class="group space-y-5">
                    <summary wire:click="$toggle('isAttachmentsOpen')" class="flex cursor-pointer items-center justify-between gap-4 border-b border-slate-100 pb-3 text-lg font-semibold text-slate-900">
                        {{ __('Attachments & Proof') }}
                        <svg class="h-4 w-4 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                    </summary>
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <input type="file" wire:model="file" class="grow rounded-xl border border-dashed border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:outline-none" />
                            <x-button type="button" wire:click="uploadFile" wire:loading.attr="disabled">
                                <span wire:loading wire:target="uploadFile" class="h-4 w-4 animate-spin border-2 border-white/80 border-t-transparent rounded-full"></span>
                                {{ __('Upload Attachment') }}
                            </x-button>
                            <x-action-message class="text-xs font-semibold text-emerald-600" on="uploaded">
                                {{ __('File Uploaded.') }}
                            </x-action-message>
                            @error('file') <p class="text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div class="space-y-3">
                            @forelse($log->attachments as $att)
                                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                                    <div class="min-w-0 flex-1">
                                        <a class="text-sm font-semibold text-orange-600 hover:text-orange-700" href="{{route('attachments.download', ['attachment'=>$att->id])}}">
                                            {{ $att->file_name }}
                                        </a>
                                        <p class="text-xs text-slate-500">{{ $att->created_at->diffForHumans() }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @can('updateVisibility', $att)
                                            <livewire:attachment-visibility-toggle :attachment="$att" :key="'log-att-'.$att->id"/>
                                        @else
                                            @if($att->is_public)
                                                <span class="text-xs font-semibold text-emerald-600">{{ __('Visible to customer') }}</span>
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