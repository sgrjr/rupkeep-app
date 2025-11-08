<x-app-layout>
    <div>
        <div class="max-w-6xl mx-auto p-4 space-y-4">
            @php
                $activeCount = $vehicles->whereNull('deleted_at')->count();
                $archivedCount = $vehicles->whereNotNull('deleted_at')->count();
            @endphp
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h1 class="text-2xl font-bold">Vehicles</h1>
                    <p class="text-sm text-gray-500">
                        Active: {{ $activeCount }} &middot; Archived: {{ $archivedCount }}
                    </p>
                </div>
                <a href="{{ route('my.vehicles.create') }}" class="text-sm font-semibold text-primary hover:underline">
                    + Add Vehicle
                </a>
            </div>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                @forelse($vehicles as $vehicle)
                    @php
                        $oilDue = $vehicle->next_oil_change_due_at;
                        $inspectionDue = $vehicle->next_inspection_due_at;
                        $isOilOverdue = $oilDue && \Illuminate\Support\Carbon::parse($oilDue)->isPast();
                        $isInspectionOverdue = $inspectionDue && \Illuminate\Support\Carbon::parse($inspectionDue)->isPast();
                        $isOilSoon = $oilDue && \Illuminate\Support\Carbon::parse($oilDue)->isBetween(now(), now()->addDays(7));
                        $isInspectionSoon = $inspectionDue && \Illuminate\Support\Carbon::parse($inspectionDue)->isBetween(now(), now()->addDays(7));
                        $isArchived = $vehicle->trashed();
                    @endphp

                    <div @class([
                        'border rounded-lg shadow-sm bg-white relative',
                        'opacity-70 border-dashed border-red-300' => $isArchived,
                    ])>
                        @if($isArchived)
                            <span class="absolute top-3 right-3 text-xs font-semibold tracking-wide uppercase text-red-700">
                                Archived
                            </span>
                        @endif
                        <div class="p-4 space-y-3">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">{{ $vehicle->name }}</h2>
                                    <p class="text-sm text-gray-500">
                                        Odometer: {{ $vehicle->odometer ?? '—' }}
                                        @if($vehicle->odometer_updated_at)
                                            <span class="text-xs text-gray-400">
                                                (updated {{ \Illuminate\Support\Carbon::parse($vehicle->odometer_updated_at)->diffForHumans() }})
                                            </span>
                                        @endif
                                    </p>
                                </div>
                                <span class="text-xs px-2 py-1 rounded-full {{ $vehicle->is_in_service ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-800' }}">
                                    {{ $vehicle->is_in_service ? 'In Service' : 'Out of Service' }}
                                </span>
                            </div>

                            <div class="text-sm text-gray-700 space-y-1">
                                <p>
                                    <span class="font-semibold">Assigned to:</span>
                                    {{ $vehicle->currentAssignment?->name ?? '—' }}
                                    @if($vehicle->current_assignment_started_at)
                                        <span class="text-gray-500">
                                            (since {{ \Illuminate\Support\Carbon::parse($vehicle->current_assignment_started_at)->toFormattedDateString() }})
                                        </span>
                                    @endif
                                </p>
                                @if($vehicle->current_assignment_notes)
                                    <p class="text-xs text-gray-500 italic">“{{ $vehicle->current_assignment_notes }}”</p>
                                @endif
                            </div>

                            <div class="space-y-1 text-sm">
                                <p>
                                    <span class="font-semibold">Next oil change:</span>
                                    <span class="{{ $isOilOverdue ? 'text-red-600 font-semibold' : ($isOilSoon ? 'text-amber-600 font-semibold' : 'text-gray-800') }}">
                                        {{ $oilDue ? \Illuminate\Support\Carbon::parse($oilDue)->toFormattedDateString() : '—' }}
                                    </span>
                                </p>
                                <p>
                                    <span class="font-semibold">Next inspection:</span>
                                    <span class="{{ $isInspectionOverdue ? 'text-red-600 font-semibold' : ($isInspectionSoon ? 'text-amber-600 font-semibold' : 'text-gray-800') }}">
                                        {{ $inspectionDue ? \Illuminate\Support\Carbon::parse($inspectionDue)->toFormattedDateString() : '—' }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div class="px-4 py-3 border-t bg-gray-50 flex items-center justify-between">
                            @if(!$vehicle->trashed())
                                <a href="{{ route('my.vehicles.edit', $vehicle) }}" class="text-sm font-semibold text-primary hover:underline">
                                    Manage
                                </a>
                            @else
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                    Restore to manage
                                </span>
                            @endif
                            <div class="flex items-center gap-3">
                                @if($vehicle->trashed())
                                    @can('restore', $vehicle)
                                        <form method="POST" action="{{ route('my.vehicles.restore', $vehicle->id) }}" class="inline-flex items-center">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="text-xs font-semibold text-green-600 hover:underline">
                                                Restore
                                            </button>
                                        </form>
                                    @endcan
                                    @can('forceDelete', $vehicle)
                                        <livewire:delete-confirmation-button
                                            :action-url="route('my.vehicles.force-destroy', $vehicle->id)"
                                            button-text="Delete Permanently"
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
                                            button-text="Delete"
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
                @empty
                    <div class="col-span-full text-center text-gray-500 py-12">
                        No vehicles yet. Add your first vehicle to track maintenance.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>