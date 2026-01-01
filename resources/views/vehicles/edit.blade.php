@php use Illuminate\Support\Str; @endphp

<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-orange-500 via-orange-400 to-orange-300 p-6 text-white shadow-xl">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.25),_transparent_60%)] opacity-70"></div>
            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-white/80">{{ __('Fleet Vehicle') }}</p>
                    <h1 class="text-3xl font-bold tracking-tight">{{ $vehicle->name }}</h1>
                    <div class="flex flex-wrap items-center gap-3 text-sm text-white/85">
                        <span class="inline-flex items-center gap-1 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $vehicle->is_in_service ? 'text-white' : 'text-white/80' }}">
                            <span class="h-2 w-2 rounded-full {{ $vehicle->is_in_service ? 'bg-lime-300 animate-pulse' : 'bg-white/40' }}"></span>
                            {{ $vehicle->is_in_service ? __('In Service') : __('Out of Service') }}
                        </span>
                        @if($vehicle->currentAssignment)
                            <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15M9.75 9.75L7.5 12l2.25 2.25M14.25 9.75L16.5 12l-2.25 2.25"/></svg>
                                {{ __('Assigned to :driver', ['driver' => $vehicle->currentAssignment->name]) }}
                            </span>
                        @endif
                    </div>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="rounded-2xl border border-white/30 bg-white/15 px-4 py-3 text-xs font-semibold uppercase tracking-wider text-white/85">
                        {{ __('Last Updated') }}
                        <p class="mt-1 text-base normal-case">{{ optional($vehicle->updated_at)->diffForHumans() }}</p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <livewire:annual-vehicle-report-modal :vehicleId="$vehicle->id" />
                        <button type="button" onclick="Livewire.dispatch('open-annual-report-modal-{{ $vehicle->id }}')" 
                                class="inline-flex items-center justify-center gap-2 rounded-full border border-white/40 bg-white/10 px-4 py-2 text-xs font-semibold text-white/85 backdrop-blur transition hover:bg-white/20">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                            </svg>
                            {{ __('Annual Report') }}
                        </button>
                        <a href="{{ route('my.vehicles.index') }}" class="inline-flex items-center justify-center gap-2 rounded-full border border-white/40 bg-white/10 px-4 py-2 text-xs font-semibold text-white/85 backdrop-blur transition hover:bg-white/20">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12H5m7-7l-7 7 7 7"/></svg>
                            {{ __('Back to vehicles') }}
                        </a>
                    </div>
                </div>
            </div>
        </section>

        @if (session('status'))
            <div class="rounded-2xl border {{ session('status') === 'vehicle-updated' ? 'border-emerald-100 bg-emerald-50 text-emerald-700' : 'border-sky-100 bg-sky-50 text-sky-700' }} px-4 py-3 text-sm shadow-sm">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75M12 17h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>
                        @if(session('status') === 'vehicle-updated')
                            {{ __('Vehicle saved successfully.') }}
                        @elseif(session('status') === 'maintenance-record-added')
                            {{ __('Maintenance record added.') }}
                        @elseif(session('status') === 'vehicle-created')
                            {{ __('Vehicle created. Add maintenance details below.') }}
                        @endif
                    </span>
                </div>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <form method="POST" action="{{ route('my.vehicles.update', $vehicle) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <section class="rounded-3xl border border-slate-100 bg-white/90 p-6 shadow-sm">
                        <header class="mb-6">
                            <h2 class="text-lg font-semibold text-slate-900">{{ __('Vehicle Profile') }}</h2>
                            <p class="text-xs text-slate-500">{{ __('Keep driver-facing name and mileage info current for accurate reporting.') }}</p>
                        </header>

                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="sm:col-span-2">
                                <label for="name" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Vehicle Name') }}</label>
                                <input id="name" name="name" type="text" value="{{ old('name', $vehicle->name) }}" required class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                <x-input-error for="name" class="mt-2 text-xs font-semibold text-red-500" />
                            </div>
                            <div>
                                <label for="odometer" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Current Odometer (mi)') }}</label>
                                <input id="odometer" name="odometer" type="number" min="0" value="{{ old('odometer', $vehicle->odometer) }}" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                <x-input-error for="odometer" class="mt-2 text-xs font-semibold text-red-500" />
                            </div>
                            <div>
                                <label for="last_service_mileage" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Last Service Mileage') }}</label>
                                <input id="last_service_mileage" name="last_service_mileage" type="number" min="0" value="{{ old('last_service_mileage', $vehicle->last_service_mileage) }}" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                <x-input-error for="last_service_mileage" class="mt-2 text-xs font-semibold text-red-500" />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-100 bg-white/90 p-6 shadow-sm">
                        <header class="mb-6 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">{{ __('Assignment') }}</h2>
                                <p class="text-xs text-slate-500">{{ __('Track who currently has the vehicle and capture any notes for dispatch.') }}</p>
                            </div>
                        </header>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="current_user_id" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Assigned Driver') }}</label>
                                <select id="current_user_id" name="current_user_id" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                    <option value="">{{ __('— Unassigned —') }}</option>
                                    @foreach($drivers as $driver)
                                        <option value="{{ $driver->id }}" @selected(old('current_user_id', $vehicle->current_user_id) == $driver->id)>
                                            {{ $driver->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error for="current_user_id" class="mt-2 text-xs font-semibold text-red-500" />
                            </div>
                            <div>
                                <label for="current_assignment_started_at" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Assignment Start Date') }}</label>
                                <input id="current_assignment_started_at" name="current_assignment_started_at" type="date" value="{{ old('current_assignment_started_at', optional($vehicle->current_assignment_started_at)->toDateString()) }}" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                <x-input-error for="current_assignment_started_at" class="mt-2 text-xs font-semibold text-red-500" />
                            </div>
                            <div class="md:col-span-2">
                                <label for="current_assignment_notes" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Assignment Notes') }}</label>
                                <textarea id="current_assignment_notes" name="current_assignment_notes" rows="3" class="mt-2 block w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">{{ old('current_assignment_notes', $vehicle->current_assignment_notes) }}</textarea>
                                <x-input-error for="current_assignment_notes" class="mt-2 text-xs font-semibold text-red-500" />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-100 bg-white/90 p-6 shadow-sm">
                        <header class="mb-6">
                            <h2 class="text-lg font-semibold text-slate-900">{{ __('Maintenance Schedule') }}</h2>
                            <p class="text-xs text-slate-500">{{ __('Log last service dates so we can surface upcoming work and overdue items on dashboards.') }}</p>
                        </header>

                        @php
                            $oilStatus = $vehicle->getOilChangeStatus();
                            $inspectionStatus = $vehicle->getInspectionStatus();
                        @endphp

                        <div class="mb-6 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl border {{ $oilStatus === 'overdue' ? 'border-red-200 bg-red-50' : ($oilStatus === 'due_soon' ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50') }} p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <label for="next_oil_change_due_at" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Oil Change') }}</label>
                                    @if($oilStatus === 'overdue')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-700">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                                            {{ __('Overdue') }}
                                        </span>
                                    @elseif($oilStatus === 'due_soon')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                                            {{ __('Due Soon') }}
                                        </span>
                                    @elseif($oilStatus === 'ok')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            {{ __('OK') }}
                                        </span>
                                    @endif
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label for="last_oil_change_at" class="block text-xs font-medium text-slate-500">{{ __('Last Service') }}</label>
                                        <input id="last_oil_change_at" name="last_oil_change_at" type="date" value="{{ old('last_oil_change_at', optional($vehicle->last_oil_change_at)->toDateString()) }}" class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs shadow-sm focus:border-orange-400 focus:outline-none focus:ring-1 focus:ring-orange-200">
                                        <x-input-error for="last_oil_change_at" class="mt-1 text-xs font-semibold text-red-500" />
                                    </div>
                                    <div>
                                        <label for="next_oil_change_due_at" class="block text-xs font-medium text-slate-500">{{ __('Next Due') }}</label>
                                        <input id="next_oil_change_due_at" name="next_oil_change_due_at" type="date" value="{{ old('next_oil_change_due_at', optional($vehicle->next_oil_change_due_at)->toDateString()) }}" class="mt-1 block w-full rounded-lg border {{ $oilStatus === 'overdue' ? 'border-red-300' : ($oilStatus === 'due_soon' ? 'border-amber-300' : 'border-slate-200') }} bg-white px-2 py-1.5 text-xs shadow-sm focus:border-orange-400 focus:outline-none focus:ring-1 focus:ring-orange-200">
                                        <x-input-error for="next_oil_change_due_at" class="mt-1 text-xs font-semibold text-red-500" />
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-2xl border {{ $inspectionStatus === 'overdue' ? 'border-red-200 bg-red-50' : ($inspectionStatus === 'due_soon' ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50') }} p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <label for="next_inspection_due_at" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Inspection') }}</label>
                                    @if($inspectionStatus === 'overdue')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-700">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                                            {{ __('Overdue') }}
                                        </span>
                                    @elseif($inspectionStatus === 'due_soon')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                                            {{ __('Due Soon') }}
                                        </span>
                                    @elseif($inspectionStatus === 'ok')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            {{ __('OK') }}
                                        </span>
                                    @endif
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label for="last_inspection_at" class="block text-xs font-medium text-slate-500">{{ __('Last Service') }}</label>
                                        <input id="last_inspection_at" name="last_inspection_at" type="date" value="{{ old('last_inspection_at', optional($vehicle->last_inspection_at)->toDateString()) }}" class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs shadow-sm focus:border-orange-400 focus:outline-none focus:ring-1 focus:ring-orange-200">
                                        <x-input-error for="last_inspection_at" class="mt-1 text-xs font-semibold text-red-500" />
                                    </div>
                                    <div>
                                        <label for="next_inspection_due_at" class="block text-xs font-medium text-slate-500">{{ __('Next Due') }}</label>
                                        <input id="next_inspection_due_at" name="next_inspection_due_at" type="date" value="{{ old('next_inspection_due_at', optional($vehicle->next_inspection_due_at)->toDateString()) }}" class="mt-1 block w-full rounded-lg border {{ $inspectionStatus === 'overdue' ? 'border-red-300' : ($inspectionStatus === 'due_soon' ? 'border-amber-300' : 'border-slate-200') }} bg-white px-2 py-1.5 text-xs shadow-sm focus:border-orange-400 focus:outline-none focus:ring-1 focus:ring-orange-200">
                                        <x-input-error for="next_inspection_due_at" class="mt-1 text-xs font-semibold text-red-500" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <input id="is_in_service" name="is_in_service" type="checkbox" value="1" class="h-4 w-4 rounded border-slate-300 text-orange-500 focus:ring-orange-400" {{ old('is_in_service', $vehicle->is_in_service) ? 'checked' : '' }}>
                            <label for="is_in_service" class="text-sm font-semibold text-slate-700">{{ __('Vehicle is currently in service') }}</label>
                        </div>
                    </section>

                    <div class="flex justify-end">
                        <x-button class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-orange-600 sm:w-auto">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            {{ __('Save Vehicle') }}
                        </x-button>
                    </div>
                </form>
            </div>

            <div class="space-y-6">
                <form method="POST" action="{{ route('my.vehicles.maintenance.store', $vehicle) }}" class="space-y-5 rounded-3xl border border-slate-100 bg-white/90 p-6 shadow-sm">
                @csrf
                    <header>
                        <h2 class="text-lg font-semibold text-slate-900">{{ __('Log Maintenance') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Record oil changes, inspections, repairs, and other work to build the service trail.') }}</p>
                    </header>

                    <div class="space-y-4">
                        <div>
                            <label for="type" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Maintenance Type') }}</label>
                            <select id="type" name="type" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                @foreach($maintenanceTypes as $key => $label)
                                    <option value="{{ $key }}" @selected(old('type') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error for="type" class="mt-2 text-xs font-semibold text-red-500" />
                        </div>
                        <div>
                            <label for="title" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Title / Summary') }}</label>
                            <input id="title" name="title" type="text" value="{{ old('title') }}" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            <x-input-error for="title" class="mt-2 text-xs font-semibold text-red-500" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="performed_at" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Performed On') }}</label>
                                <input id="performed_at" name="performed_at" type="date" value="{{ old('performed_at') }}" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                <x-input-error for="performed_at" class="mt-2 text-xs font-semibold text-red-500" />
                            </div>
                            <div>
                                <label for="next_due_at" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Next Due') }}</label>
                                <input id="next_due_at" name="next_due_at" type="date" value="{{ old('next_due_at') }}" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                <x-input-error for="next_due_at" class="mt-2 text-xs font-semibold text-red-500" />
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="mileage" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Mileage') }}</label>
                                <input id="mileage" name="mileage" type="number" min="0" value="{{ old('mileage') }}" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                <x-input-error for="mileage" class="mt-2 text-xs font-semibold text-red-500" />
                            </div>
                            <div>
                                <label for="cost" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Cost (USD)') }}</label>
                                <input id="cost" name="cost" type="number" step="0.01" min="0" value="{{ old('cost') }}" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                <x-input-error for="cost" class="mt-2 text-xs font-semibold text-red-500" />
                            </div>
                        </div>
                        <div>
                            <label for="notes" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Notes') }}</label>
                            <textarea id="notes" name="notes" rows="3" class="mt-2 block w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">{{ old('notes') }}</textarea>
                            <x-input-error for="notes" class="mt-2 text-xs font-semibold text-red-500" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <x-button class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-orange-600 sm:w-auto">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            {{ __('Add Record') }}
                        </x-button>
                    </div>
                </form>
            </div>
        </div>

        <section class="rounded-3xl border border-slate-100 bg-white/90 shadow-sm">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-6 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Maintenance History') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Most recent records are shown first.') }}</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $vehicle->maintenanceRecords->count() }} {{ __('records') }}</span>
            </header>

            <div class="max-h-96 overflow-x-auto overflow-y-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-6 py-3 text-left">{{ __('Type') }}</th>
                            <th class="px-6 py-3 text-left">{{ __('Performed') }}</th>
                            <th class="px-6 py-3 text-left">{{ __('Next Due') }}</th>
                            <th class="px-6 py-3 text-left">{{ __('Mileage') }}</th>
                            <th class="px-6 py-3 text-left">{{ __('Notes & Cost') }}</th>
                            <th class="px-6 py-3 text-left">{{ __('Recorded By') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($vehicle->maintenanceRecords as $record)
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-6 py-4 font-medium text-slate-900">
                                    {{ $maintenanceTypes[$record->type] ?? Str::headline($record->type) }}
                                    @if($record->title)
                                        <div class="text-xs font-medium text-slate-500">{{ $record->title }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-slate-600">
                                    {{ optional($record->performed_at)->toFormattedDateString() ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-slate-600">
                                    {{ optional($record->next_due_at)->toFormattedDateString() ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-slate-600">
                                    {{ $record->mileage ? number_format($record->mileage) : '—' }}
                                </td>
                                <td class="px-6 py-4 text-slate-600">
                                    <div>{{ $record->notes ?? '—' }}</div>
                                    @if($record->cost)
                                        <div class="text-xs text-slate-500">{{ __('Cost: $:amount', ['amount' => number_format($record->cost, 2)]) }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-slate-600">
                                    {{ $record->creator?->name ?? '—' }}
                                    <div class="text-xs text-slate-400">{{ optional($record->created_at)->diffForHumans() }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-6 text-center text-slate-500">
                                    {{ __('No maintenance records yet. Log the first service above.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
