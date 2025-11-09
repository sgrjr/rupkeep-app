@php
    use Illuminate\Support\Carbon;

    $activeCount = $vehicles->whereNull('deleted_at')->count();
    $archivedCount = $vehicles->whereNotNull('deleted_at')->count();
    $inServiceCount = $vehicles->where('is_in_service', true)->count();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Fleet Management') }}</p>
                <h1 class="text-xl font-semibold text-slate-900">{{ __('Vehicles') }}</h1>
                <p class="text-xs text-slate-500">{{ trans_choice(':count vehicle on file|:count vehicles on file', $vehicles->count()) }}</p>
            </div>
            @can('create', \App\Models\Vehicle::class)
                <a href="{{ route('my.vehicles.create') }}"
                   class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-500 px-3 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-orange-600">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    {{ __('Add vehicle') }}
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-3xl border border-orange-100 bg-white/90 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Active vehicles') }}</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $activeCount }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white/90 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('In service') }}</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $inServiceCount }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white/90 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Archived') }}</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $archivedCount }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white/90 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Under maintenance') }}</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $vehicles->where('is_in_service', false)->count() }}</p>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Fleet overview') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Track assignments and maintenance schedules for every escort vehicle.') }}</p>
                </div>
            </header>

            <div class="mt-5 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @forelse($vehicles as $vehicle)
                    @php
                        $oilDue = $vehicle->next_oil_change_due_at ? Carbon::parse($vehicle->next_oil_change_due_at) : null;
                        $inspectionDue = $vehicle->next_inspection_due_at ? Carbon::parse($vehicle->next_inspection_due_at) : null;
                        $isOilOverdue = $oilDue && $oilDue->isPast();
                        $isInspectionOverdue = $inspectionDue && $inspectionDue->isPast();
                        $isOilSoon = $oilDue && $oilDue->between(now(), now()->addDays(7));
                        $isInspectionSoon = $inspectionDue && $inspectionDue->between(now(), now()->addDays(7));
                        $isArchived = $vehicle->trashed();
                    @endphp

                    <div @class([
                        'group relative overflow-hidden rounded-3xl border p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-xl',
                        'border-slate-200 bg-white/90' => ! $isArchived,
                        'border-dashed border-red-200 bg-white/60' => $isArchived,
                    ])>
                        <div class="absolute right-0 top-0 h-24 w-24 -translate-y-12 translate-x-6 rounded-full bg-orange-100 opacity-60 blur-3xl transition group-hover:opacity-80"></div>

                        <div class="relative space-y-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-xs uppercase tracking-wider text-slate-400">{{ $vehicle->license_plate ?? __('Vehicle') }}</p>
                                    <h3 class="text-lg font-semibold text-slate-900">{{ $vehicle->name }}</h3>
                                    <p class="text-xs text-slate-500">
                                        {{ __('Odometer') }}: {{ $vehicle->odometer ?? '—' }}
                                        @if($vehicle->odometer_updated_at)
                                            <span class="text-[10px] text-slate-400">
                                                ({{ Carbon::parse($vehicle->odometer_updated_at)->diffForHumans() }})
                                            </span>
                                        @endif
                                    </p>
                                </div>
                                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-wide {{ $vehicle->is_in_service ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                                    {{ $vehicle->is_in_service ? __('In service') : __('Out of service') }}
                                </span>
                            </div>

                            <div class="space-y-1 text-sm text-slate-600">
                                <p>
                                    <span class="font-semibold text-slate-500">{{ __('Assigned to') }}:</span>
                                    {{ $vehicle->currentAssignment?->name ?? '—' }}
                                    @if($vehicle->current_assignment_started_at)
                                        <span class="text-xs text-slate-400">
                                            ({{ Carbon::parse($vehicle->current_assignment_started_at)->toFormattedDateString() }})
                                        </span>
                                    @endif
                                </p>
                                @if($vehicle->current_assignment_notes)
                                    <p class="text-xs italic text-slate-400">“{{ $vehicle->current_assignment_notes }}”</p>
                                @endif
                            </div>

                            <div class="grid gap-3 rounded-2xl border border-slate-100 bg-slate-50/60 px-4 py-3 text-sm">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">{{ __('Next oil change') }}</span>
                                    <span class="font-semibold {{ $isOilOverdue ? 'text-red-600' : ($isOilSoon ? 'text-amber-600' : 'text-slate-800') }}">
                                        {{ $oilDue ? $oilDue->toFormattedDateString() : '—' }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">{{ __('Next inspection') }}</span>
                                    <span class="font-semibold {{ $isInspectionOverdue ? 'text-red-600' : ($isInspectionSoon ? 'text-amber-600' : 'text-slate-800') }}">
                                        {{ $inspectionDue ? $inspectionDue->toFormattedDateString() : '—' }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-col gap-3 border-t border-slate-100 pt-3 text-xs sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                                <div class="flex items-center gap-2">
                                    @if(!$vehicle->trashed())
                                        <a href="{{ route('my.vehicles.edit', $vehicle) }}"
                                           class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-semibold text-slate-600 transition hover:border-orange-300 hover:text-orange-600">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-4.536a2.5 2.5 0 11-3.536 3.536L4.5 16.5V19.5H7.5l8.5-8.5"/></svg>
                                            {{ __('Manage') }}
                                        </a>
                                    @else
                                        <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold text-slate-500">
                                            {{ __('Archived') }}
                                        </span>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    @if($vehicle->trashed())
                                        @can('restore', $vehicle)
                                            <form method="POST" action="{{ route('my.vehicles.restore', $vehicle->id) }}" class="inline-flex items-center">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-white px-3 py-1 text-[11px] font-semibold text-emerald-600 transition hover:border-emerald-300 hover:text-emerald-700">
                                                    {{ __('Restore') }}
                                                </button>
                                            </form>
                                        @endcan
                                        @can('forceDelete', $vehicle)
                                            <livewire:delete-confirmation-button
                                                :action-url="route('my.vehicles.force-destroy', $vehicle->id)"
                                                button-text="{{ __('Delete permanently') }}"
                                                button-class="inline-flex items-center gap-1 rounded-full border border-red-200 bg-white px-3 py-1 text-[11px] font-semibold text-red-600 transition hover:border-red-300 hover:text-red-700"
                                                :model-class="\App\Models\Vehicle::class"
                                                :record-id="$vehicle->id"
                                                resource="vehicles"
                                                redirect-route="my.vehicles.index"
                                                :force="true"
                                            />
                                        @endcan
                                    @else
                                        @can('delete', $vehicle)
                                            <livewire:delete-confirmation-button
                                                :action-url="route('my.vehicles.destroy', $vehicle)"
                                                button-text="{{ __('Archive vehicle') }}"
                                                button-class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-semibold text-slate-600 transition hover:border-orange-300 hover:text-orange-600"
                                                :model-class="\App\Models\Vehicle::class"
                                                :record-id="$vehicle->id"
                                                resource="vehicles"
                                                redirect-route="my.vehicles.index"
                                            />
                                        @endcan
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full rounded-3xl border border-dashed border-slate-200 bg-slate-50/80 py-12 text-center text-sm text-slate-400">
                        {{ __('No vehicles yet. Add your first vehicle to track assignments and maintenance reminders.') }}
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>