@props(['log'=>(Object)[], 'car_drivers' => [], 'vehicles' => [], 'customer_contacts' => [], 'vehicle_positions' => []])

<div class="min-h-screen bg-gray-100 font-sans antialiased pb-20">

<div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

<!-- Job Navigation Header -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6 text-center">
        <h1 class="text-xl font-bold text-gray-800 mb-2">Log for Job: <span class="text-blue-700">{{$log->job->job_no}}</span></h1>
        <div class="flex flex-col sm:flex-row justify-center items-center gap-2 text-sm text-gray-600">
            <a class="btn-base btn-primary w-full sm:w-auto mt-2 sm:mt-0" href="{{route('my.customers.show', ['customer'=>$log->job->customer_id])}}">&larr; {{$log->job->customer->name}}</a>
            <span class="hidden sm:inline">|</span>
            <a class="btn-base btn-primary w-full sm:w-auto mt-2 sm:mt-0" href="{{route('my.jobs.show', ['job'=>$log->job_id])}}">View Job Summary</a>
        </div>

        {{-- Desktop/Tablet Table Layout --}}
        <div class="mt-4 overflow-x-auto hidden sm:block"> {{-- Hidden on mobile, block on sm+ --}}
            <table class="w-full text-center border-collapse responsive-table">
                <thead>
                    <tr>
                        <th>Scheduled Pickup</th>
                        <th>Scheduled Delivery</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-t border-gray-300">
                        <td data-label="Scheduled Pickup">{{ \Carbon\Carbon::parse($log->job->scheduled_pickup_at)->format('g:i A D, M j, Y') }}</td>
                        <td data-label="Scheduled Delivery">{{ \Carbon\Carbon::parse($log->job->scheduled_delivery_at)->format('g:i A D, M j, Y') }}</td>
                    </tr>
                    <tr class="border-t border-gray-300">
                        <td data-label="Pickup Address">{{$log->job->pickup_address}}</td>
                        <td data-label="Delivery Address">{{$log->job->delivery_address}}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Mobile-Specific List Layout --}}
        <div class="mt-4 block sm:hidden text-left"> {{-- Block on mobile, hidden on sm+ --}}
            <div class="space-y-3">
                <div class="bg-gray-50 p-3 rounded-md border border-gray-200">
                    <p class="font-semibold text-gray-700 mb-1 text-sm">Scheduled Pickup:</p>
                    <p class="text-gray-800">{{ \Carbon\Carbon::parse($log->job->scheduled_pickup_at)->format('g:i A D, M j, Y') }}</p>
                </div>
                <div class="bg-gray-50 p-3 rounded-md border border-gray-200">
                    <p class="font-semibold text-gray-700 mb-1 text-sm">Scheduled Delivery:</p>
                    <p class="text-gray-800">{{ \Carbon\Carbon::parse($log->job->scheduled_delivery_at)->format('g:i A D, M j, Y') }}</p>
                </div>
                <div class="bg-gray-50 p-3 rounded-md border border-gray-200">
                    <p class="font-semibold text-gray-700 mb-1 text-sm">Pickup Address:</p>
                    <p class="text-gray-800">{{$log->job->pickup_address}}</p>
                </div>
                <div class="bg-gray-50 p-3 rounded-md border border-gray-200">
                    <p class="font-semibold text-gray-700 mb-1 text-sm">Delivery Address:</p>
                    <p class="text-gray-800">{{$log->job->delivery_address}}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Log Edit Form -->
    <form id="user_log_form" wire:submit="saveLog" class="bg-white rounded-lg shadow-lg relative pb-6 mb-6">
        @csrf

        <!-- Sticky Save Button -->
        <div class="flex justify-center fixed bottom-0 left-0 right-0 p-4 bg-white border-t border-gray-200 shadow-lg z-40">
            {{-- General error message for validation failures --}}
            @if (session()->has('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <strong class="font-bold">Error!</strong>
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            {{-- Indicator for any Livewire saving activity (auto-save or manual) --}}
            <span wire:loading class="text-gray-600 text-sm mr-4 flex items-center">
                <span class="animate-pulse mr-2">&#8987;</span> Saving...
            </span>

            {{-- Success message for manual save --}}
            <x-action-message class="me-3 text-center text-green-800 bg-green-100 px-4 py-2 rounded-md font-semibold" on="saved">
                {{ __('Saved!') }}
            </x-action-message>
            <button type="submit" class="btn-base btn-primary px-8 py-3 text-lg" wire:loading.attr="disabled" wire:loading.class="opacity-75 cursor-not-allowed">
                <span wire:loading wire:target="saveLog" class="animate-spin mr-2">&#9696;</span>
                Save Changes
            </button>
        </div>

        <div class="p-5 space-y-6"> {{-- Increased space-y for main sections --}}

            <!-- Driver & Vehicle Details -->
            <details {{ $isDriverVehicleOpen ? 'open' : '' }} class="input-group">
                <summary wire:click="$toggle('isDriverVehicleOpen')" class="font-bold text-lg text-gray-800 mb-3 border-b pb-2 cursor-pointer">Driver & Vehicle</summary>
                <div class="space-y-4 pt-3"> {{-- Added pt-3 for spacing below summary --}}
                    <div class="custom-select-wrapper">
                        <label for="car_driver_id">Car Driver:</label>
                        <select id="car_driver_id" wire:model.blur="form.car_driver_id">
                            <option value="">Select Driver</option>
                            @foreach($car_drivers as $car_driver)
                            <option value="{{$car_driver['value']}}">{{$car_driver['name']}}</option>
                            @endforeach
                        </select>
                        @error('form.car_driver_id') <span class="error-message">{{ $message }}</span> @enderror
                    </div>

                    <div class="custom-select-wrapper">
                        <label for="vehicle_id">Vehicle:</label>
                        <select id="vehicle_id" wire:model.blur="form.vehicle_id">
                            <option value="">Select Vehicle</option>
                            @foreach($vehicles as $vehicle)
                            <option value="{{$vehicle['value']}}">{{$vehicle['name']}}</option>
                            @endforeach
                        </select>
                        @error('form.vehicle_id') <span class="error-message">{{ $message }}</span> @enderror
                    </div>

                    <div class="custom-select-wrapper">
                        <label for="vehicle_position">Vehicle Position:</label>
                        <select id="vehicle_position" wire:model.blur="form.vehicle_position">
                            <option value="">Select Position</option>
                            @foreach($vehicle_positions as $position)
                            <option value="{{$position['value']}}">{{$position['name']}}</option>
                            @endforeach
                        </select>
                        @error('form.vehicle_position') <span class="error-message">{{ $message }}</span> @enderror
                    </div>

                    <div class="custom-select-wrapper">
                        <label for="truck_driver_id">Truck Driver:</label>
                        <select id="truck_driver_id" wire:model.blur="form.truck_driver_id">
                            <option value="">Select Truck Driver</option>
                            @foreach($customer_contacts as $contact)
                            <option value="{{$contact['value']}}">{{$contact['name']}}</option>
                            @endforeach
                        </select>
                        @error('form.truck_driver_id') <span class="error-message">{{ $message }}</span> @enderror
                    </div>

                    <details class="bg-gray-100 p-3 rounded-md border border-gray-200">
                        <summary class="font-semibold cursor-pointer text-blue-700 hover:text-blue-800">New Truck Driver?</summary>
                        <div class="mt-3 space-y-3">
                            <div>
                                <label for="new_truck_driver_name">New Truck Driver Name:</label>
                                <input type="text" id="new_truck_driver_name" wire:model.blur="form.new_truck_driver_name" placeholder="Name (use if not in list)">
                                @error('form.new_truck_driver_name') <span class="error-message">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="new_truck_driver_phone">New Truck Driver Phone:</label>
                                <input type="tel" id="new_truck_driver_phone" wire:model.blur="form.new_truck_driver_phone" placeholder="Phone (use if not in list)">
                                @error('form.new_truck_driver_phone') <span class="error-message">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="new_truck_driver_memo">New Truck Driver Memo:</label>
                                <textarea id="new_truck_driver_memo" wire:model.blur="form.new_truck_driver_memo" placeholder="Any relevant notes for new driver"></textarea>
                                @error('form.new_truck_driver_memo') <span class="error-message">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </details>
                </div>
            </details>

            <!-- Trip Timing -->
            <details {{ $isTripTimingOpen ? 'open' : '' }} class="input-group">
                <summary wire:click="$toggle('isTripTimingOpen')" class="font-bold text-lg text-gray-800 mb-3 border-b pb-2 cursor-pointer">Trip Timing</summary>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-3">
                    <div>
                        <label for="started_at">Started At:</label>
                        <input type="datetime-local" id="started_at" wire:model.blur="form.started_at" value="{{ $form->started_at }}">
                        @error('form.started_at') <span class="error-message">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="ended_at">Ended At:</label>
                        <input type="datetime-local" id="ended_at" wire:model.blur="form.ended_at" value="{{ $form->ended_at }}">
                        @error('form.ended_at') <span class="error-message">{{ $message }}</span> @enderror
                    </div>
                </div>
            </details>

            <!-- Mileage Details -->
            <details {{ $isMileageDetailsOpen ? 'open' : '' }} class="input-group">
                <summary wire:click="$toggle('isMileageDetailsOpen')" class="font-bold text-lg text-gray-800 mb-3 border-b pb-2 cursor-pointer">Mileage Details</summary>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 pt-3"> {{-- Adjusted grid for better mobile layout --}}
                    <div>
                        <label for="start_mileage">Start Mileage:</label>
                        <input type="number" id="start_mileage" wire:model.blur="form.start_mileage" placeholder="0">
                        @error('form.start_mileage') <span class="error-message">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="start_job_mileage">Start Job Mileage:</label>
                        <input type="number" id="start_job_mileage" wire:model.blur="form.start_job_mileage" placeholder="0">
                        @error('form.start_job_mileage') <span class="error-message">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="end_job_mileage">End Job Mileage:</label>
                        <input type="number" id="end_job_mileage" wire:model.blur="form.end_job_mileage" placeholder="0">
                        @error('form.end_job_mileage') <span class="error-message">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="end_mileage">End Mileage:</label>
                        <input type="number" id="end_mileage" wire:model.blur="form.end_mileage" placeholder="0">
                        @error('form.end_mileage') <span class="error-message">{{ $message }}</span> @enderror
                    </div>
                </div>
            </details>

            <!-- Truck & Trailer Numbers -->
            <details {{ $isTruckTrailerOpen ? 'open' : '' }} class="input-group">
                <summary wire:click="$toggle('isTruckTrailerOpen')" class="font-bold text-lg text-gray-800 mb-3 border-b pb-2 cursor-pointer">Truck & Trailer</summary>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-3">
                    <div>
                        <label for="truck_no">Truck #:</label>
                        <input type="text" id="truck_no" wire:model.blur="form.truck_no" placeholder="Truck Number">
                        @error('form.truck_no') <span class="error-message">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="trailer_no">Trailer #:</label>
                        <input type="text" id="trailer_no" wire:model.blur="form.trailer_no" placeholder="Trailer Number">
                        @error('form.trailer_no') <span class="error-message">{{ $message }}</span> @enderror
                    </div>
                </div>
            </details>

            <!-- Load & Trip Status -->
            <details {{ $isLoadTripStatusOpen ? 'open' : '' }} class="input-group">
                <summary wire:click="$toggle('isLoadTripStatusOpen')" class="font-bold text-lg text-gray-800 mb-3 border-b pb-2 cursor-pointer">Load & Trip Status</summary>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-3">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="is_deadhead" wire:model.live="form.is_deadhead">
                        <label for="is_deadhead" class="text-sm font-semibold text-gray-700">Is Deadhead?</label>
                        @error('form.is_deadhead') <span class="error-message">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="load_canceled" wire:model.live="form.load_canceled">
                        <label for="load_canceled" class="text-sm font-semibold text-gray-700">Load Canceled?</label>
                        @error('form.load_canceled') <span class="error-message">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="extra_load_stops_count">Extra Load Stops:</label>
                        <input type="number" id="extra_load_stops_count" wire:model.blur="form.extra_load_stops_count" placeholder="0">
                        @error('form.extra_load_stops_count') <span class="error-message">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="wait_time_hours">Wait Time (hours):</label>
                        <input type="number" step="0.5" id="wait_time_hours" wire:model.blur="form.wait_time_hours" placeholder="0.0">
                        @error('form.wait_time_hours') <span class="error-message">{{ $message }}</span> @enderror
                    </div>
                </div>
            </details>

            <!-- Expenses -->
            <details {{ $isExpensesOpen ? 'open' : '' }} class="input-group">
                <summary wire:click="$toggle('isExpensesOpen')" class="font-bold text-lg text-gray-800 mb-3 border-b pb-2 cursor-pointer">Expenses</summary>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-3">
                    <div>
                        <label for="tolls">Tolls ($):</label>
                        <input type="number" step="0.01" id="tolls" wire:model.blur="form.tolls" placeholder="0.00">
                        @error('form.tolls') <span class="error-message">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="gas">Gas ($):</label>
                        <input type="number" step="0.01" id="gas" wire:model.blur="form.gas" placeholder="0.00">
                        @error('form.gas') <span class="error-message">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="hotel">Hotel ($):</label>
                        <input type="number" step="0.01" id="hotel" wire:model.blur="form.hotel" placeholder="0.00">
                        @error('form.hotel') <span class="error-message">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="extra_charge">Extra Charge ($):</label>
                        <input type="number" step="0.01" id="extra_charge" wire:model.blur="form.extra_charge" placeholder="0.00">
                        @error('form.extra_charge') <span class="error-message">{{ $message }}</span> @enderror
                    </div>
                </div>
            </details>

            <!-- Maintenance & Memo -->
            <details {{ $isMaintenanceMemoOpen ? 'open' : '' }} class="input-group">
                <summary wire:click="$toggle('isMaintenanceMemoOpen')" class="font-bold text-lg text-gray-800 mb-3 border-b pb-2 cursor-pointer">Maintenance & Memo</summary>
                <div class="space-y-4 pt-3">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="pretrip_check" wire:model.live="form.pretrip_check">
                        <label for="pretrip_check" class="text-sm font-semibold text-gray-700">Pretrip Check?</label>
                        @error('form.pretrip_check') <span class="error-message">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="maintenance_memo">Maintenance Memo:</label>
                        <textarea id="maintenance_memo" class="w-full" rows="5" wire:model.blur="form.maintenance_memo" placeholder="Notes about maintenance"></textarea>
                        @error('form.maintenance_memo') <span class="error-message">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="memo">General Memo:</label>
                        <textarea id="memo" class="mb-4 pt-0 w-full" rows="5" wire:model.blur="form.memo" placeholder="Any other general notes for this log"></textarea>
                        @error('form.memo') <span class="error-message">{{ $message }}</span> @enderror
                    </div>
                </div>
            </details>
        </div>
    </form>

    <!-- File Upload Section (moved below main form for better flow) -->
    <div class="bg-white rounded-lg shadow-md p-4 mb-6 mt-6"> {{-- Adjusted margin-top --}}
        <h2 class="text-lg font-bold text-gray-800 mb-3 border-b pb-2">Attachments</h2>
        <form wire:submit="uploadFile" class="flex flex-col sm:flex-row items-start sm:items-center gap-3 p-3 bg-gray-100 rounded-md border border-gray-200 mb-4">
            <label for="file-upload" class="font-semibold text-gray-700 sm:min-w-[100px]">Upload New:</label>
            <input type="file" id="file-upload" wire:model="file" class="w-full text-sm text-gray-500
                file:mr-4 file:py-2 file:px-4
                file:rounded-md file:border-0
                file:text-sm file:font-semibold
                file:bg-blue-50 file:text-blue-700
                hover:file:bg-blue-100
            "/>
            <button type="submit" class="btn-base btn-primary w-full sm:w-auto flex-shrink-0" wire:loading.attr="disabled">
                <span wire:loading wire:target="uploadFile" class="animate-spin mr-2">&#9696;</span> Attach File
            </button>
            @error('file') <span class="text-red-500 text-xs mt-1 sm:mt-0 sm:ml-auto">{{ $message }}</span> @enderror
            <x-action-message class="me-3 text-green-600 text-sm mt-1 sm:mt-0 sm:ml-auto" on="uploaded">
                {{ __('File Uploaded.') }}
            </x-action-message>
        </form>

        <div class="mt-4">
            <p class="font-semibold text-gray-700 mb-2">Existing Attachments ({{$log->attachments? $log->attachments->count():0}}):</p>
            @if($log->attachments && $log->attachments->count() > 0)
                <div class="space-y-2">
                    @foreach($log->attachments as $att)
                    <div class="flex items-center gap-2 bg-gray-50 p-2 rounded-md border border-gray-200">
                        <livewire:delete-confirmation-button
                            :action-url="route('attachments.destroy', ['attachment'=> $att->id])"
                            button-text="&times;"
                            :redirect-route="Route::currentRouteName()"
                        />
                        <a class="btn-base btn-action btn-primary flex items-center gap-1 flex-grow" download href="{{route('attachments.download', ['attachment'=>$att->id])}}">
                            <span>&#128229;</span>{{$att->file_name}}
                        </a>
                    </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 text-sm">No attachments yet.</p>
            @endif
        </div>
    </div>
</div>

@script
<script>
    // Initialize Flatpickr for started_at and ended_at fields
    flatpickr('#started_at', {
        "dateFormat": "Y-m-d H:i",
        "enableTime": true,
        "defaultHour": 6
    });

    flatpickr('#ended_at', {
        "dateFormat": "Y-m-d H:i",
        "enableTime": true,
        "defaultHour": 6
    });
</script>
@endscript