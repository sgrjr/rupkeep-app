@props(['drivers'=>[], 'vehicles'=>[]])
<div class="min-h-screen bg-gray-100 font-sans antialiased">

    <div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

        @if($job->customer_id)
        <a class="btn-base btn-primary w-full text-center mb-6 block" href="{{route('my.customers.show', ['customer'=>$job->customer_id])}}">&larr; {{$job->customer->name}}</a>
        @endif

        <!-- Settings & Actions Section -->
        <details class="bg-white rounded-lg shadow-lg overflow-hidden flex flex-col mb-6">
            <summary class="p-5 font-bold text-xl cursor-pointer bg-gray-50 border-b border-gray-200 hover:bg-gray-100 transition-colors duration-200">
                Settings & Actions
            </summary>
            <div class="flex flex-col sm:flex-row justify-start gap-3 p-4 bg-gray-50 border-t border-gray-200">
                @if(auth()->user()->can('update', $job))
                    <a href="{{route('my.jobs.edit', ['job'=>$job->id])}}" class="btn-base btn-action btn-action-warning w-full sm:w-auto">Edit Job</a>
                @endif
                <form wire:submit="generateInvoice" class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto p-3 bg-gray-100 rounded-md border border-gray-200">
                    <label class="font-semibold text-gray-700 mb-2 sm:mb-0 sm:min-w-[120px]">Generate Invoice:</label>
                    <x-action-message class="me-3 text-green-600 text-sm" on="updated">
                        {{ __('Invoice Created.') }}
                    </x-action-message>
                    <button class="btn-base btn-action btn-primary w-full sm:w-auto" type="submit">Generate Invoice</button>
                </form>
                <form wire:submit="uploadFile" class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto p-3 bg-gray-100 rounded-md border border-gray-200">
                    <label class="font-semibold text-gray-700 mb-2 sm:mb-0 sm:min-w-[120px]">Upload File:</label>
                    <input type="file" wire:model="file" class="w-full text-sm text-gray-500
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-md file:border-0
                        file:text-sm file:font-semibold
                        file:bg-blue-50 file:text-blue-700
                        hover:file:bg-blue-100
                    "/>
                    @error('file') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    <x-action-message class="me-3 text-green-600 text-sm" on="uploaded">
                        {{ __('File Uploaded.') }}
                    </x-action-message>
                    <button class="btn-base btn-action btn-primary w-full sm:w-auto" type="submit">Attach File</button>
                </form>
                @if(auth()->user()->can('delete', $job))                    
                    <livewire:delete-confirmation-button
                        :action-url="route('my.jobs.destroy', ['job'=> $job->id])"
                        button-text="Delete Job"
                        :redirect-route="$redirect_to_root ?? null"
                    />
                @endif
            </div>
        </details>

        <!-- General Details Section -->
        <details open class="bg-white rounded-lg shadow-lg overflow-hidden flex flex-col mb-6">
            <summary class="p-5 font-bold text-xl cursor-pointer bg-gray-50 border-b border-gray-200 hover:bg-gray-100 transition-colors duration-200">
                General Details
            </summary>
            <div class="p-5 flex-grow space-y-3 text-gray-700">
                @can('viewAny', new \App\Models\Organization)
                <p><span class="font-semibold">Organization:</span> <span class="text-gray-800">{{$job->organization->name}}</span></p>
                @endcan
                <p><span class="font-semibold">Job #:</span> <span class="text-blue-700 font-bold">{{$job->job_no}}</span></p>
                <p><span class="font-semibold">Load #:</span> <span class="text-gray-800">{{$job->load_no}}</span></p>

                <p><span class="font-semibold">Customer:</span> @if($job->customer) <span class="text-gray-800">{{$job->customer->name}}</span> @endif </p>

                @if($job->customer)
                <details class="bg-gray-50 p-3 rounded-md border border-gray-200">
                    <summary class="font-semibold cursor-pointer text-blue-700 hover:text-blue-800">Contact Info <span class="text-xs text-gray-500">(click to expand)</span></summary>
                    <div class="mt-2 text-sm">
                        <p>{{$job->customer->street}} {{$job->customer->city}}, {{$job->customer->state}} {{$job->customer->zip}}</p>
                        <div class="overflow-x-auto mt-3">
                            <table class="responsive-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>Memo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($job->customer->contacts as $contact)
                                    <tr>
                                        <td data-label="Name">{{$contact->name}}</td>
                                        <td data-label="Phone">{{$contact->phone}}</td>
                                        <td data-label="Memo">{{$contact->memo}}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </details>
                @endif

                <p><span class="font-semibold">Scheduled Pickup:</span> <a class="text-blue-600 hover:underline" target="_blank" href="http://maps.google.com/?daddr={{$job->pickup_address}}">(maps) {{$job->pickup_address}}</a> @ {{$job->scheduled_pickup_at}}</p>
                <p><span class="font-semibold">Scheduled Delivery:</span> <a class="text-blue-600 hover:underline" target="_blank" href="http://maps.google.com/?daddr={{$job->delivery_address}}">(maps) {{$job->delivery_address}}</a> @ {{$job->scheduled_delivery_at}}</p>
                <p><span class="font-semibold">Check #:</span> <span class="text-gray-800">{{$job->check_no}}</span></p>
                <p><span class="font-semibold">Invoice Paid:</span> <span class="{{$job->invoice_paid < 1 ? 'text-red-600' : 'text-green-600'}} font-semibold">{{$job->invoice_paid < 1 ? 'No' : 'Yes'}}</span></p>
                <p><span class="font-semibold">Invoice #:</span> <span class="text-gray-800">{{$job->invoice_no}}</span></p>
                <p><span class="font-semibold">Rate Code:</span> <span class="text-gray-800">{{$job->rate_code}}</span></p>
                <p><span class="font-semibold">Rate Value:</span> <span class="text-gray-800">{{$job->rate_value}}</span></p>
                @if($job->canceled_at)<p class="text-red-600"><span class="font-semibold">Canceled At:</span> <span class="text-gray-800">{{$job->canceled_at}}</span></p>@endif
                @if($job->canceled_reason)<p class="text-red-600"><span class="font-semibold">Canceled Reason:</span> <span class="text-gray-800">{{$job->canceled_reason}}</span></p>@endif
                <p>
                    <span class="font-semibold">Memo:</span>
                    @if(str_starts_with($job->memo, 'http'))
                        <a target="_blank" href="{!!$job->memo!!}" class="text-blue-600 hover:underline">view invoice</a>
                    @else
                        <span class="text-gray-800">{{$job->memo}}</span>
                    @endif
                </p>
                <p class="mt-4">
                    <span class="font-semibold">Invoices:</span>
                    <div class="flex flex-wrap gap-2 mt-2">
                        @foreach($job->invoices as $invoice)
                        <a target="_blank" href="{{route('my.invoices.edit', ['invoice'=>$invoice->id])}}" class="btn-base btn-action btn-action-primary">Invoice #{{$invoice->invoice_number}}</a>
                        @endforeach
                    </div>
                </p>

                <div class="mt-4">
                    <span class="font-semibold">Attachments ({{$job->attachments? $job->attachments->count():0}}): </span>
                    <div class="space-y-2 mt-2">
                        @foreach($job->attachments as $att)
                        <div class="flex items-center gap-2">
                           <livewire:delete-confirmation-button
                                :action-url="route('attachments.destroy', ['attachment'=> $att->id])"
                                button-text="&times;"
                                :redirect-route="Route::currentRouteName()" {{-- Redirect to current page after attachment delete --}}
                            />

                            <a class="btn-base btn-action btn-primary flex items-center gap-1" download href="{{route('attachments.download', ['attachment'=>$att->id])}}">
                                <span>&#128229;</span>{{$att->file_name}}
                            </a>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </details>

       @if($job->logs)
        <!-- Job Logs Section -->
        <details class="bg-white rounded-lg shadow-lg overflow-hidden flex flex-col mb-6">
            <summary class="p-5 font-bold text-xl cursor-pointer bg-gray-50 border-b border-gray-200 hover:bg-gray-100 transition-colors duration-200">
                Job Logs ({{$job->logs->count()}})
            </summary>
            <div class="p-5 flex-grow space-y-6">
                <form wire:submit="assignJob" class="bg-white rounded-lg shadow-md p-4 mb-6 flex flex-col sm:flex-row items-center gap-4">
                    <label class="font-semibold text-gray-700 min-w-[80px]">Assign:</label>
                    <div class="custom-select-wrapper flex-grow w-full">
                        <select wire:model.blur="assignment.car_driver_id" class="w-full px-4 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Select Driver</option>
                            @foreach($drivers as $driver)
                            <option value="{{$driver['value']}}">{{$driver['name']}}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="custom-select-wrapper flex-grow w-full">
                        <select wire:model.blur="assignment.vehicle_id" class="w-full px-4 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Vehicle</option>
                            @foreach($vehicles as $vehicle)
                            <option value="{{$vehicle['value']}}">{{$vehicle['name']}}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="custom-select-wrapper flex-grow w-full">
                        <select wire:model.blur="assignment.vehicle_position" class="w-full px-4 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Position</option>
                            @foreach($vehicle_positions as $position)
                            <option value="{{$position['value']}}">{{$position['name']}}</option>
                            @endforeach
                        </select>
                    </div>

                    <x-action-message class="me-3 text-green-600 text-sm" on="updated">
                        {{ __('Added.') }}
                    </x-action-message>
                    <button class="btn-base btn-primary w-full sm:w-auto min-w-[150px]" type="submit">Assign Job</button>
                </form>

                <div class="grid grid-cols-1 gap-6">
                    @foreach($job->logs as $log)
                    <div class="bg-white rounded-lg shadow-lg overflow-hidden flex flex-col">
                        <div class="p-5 flex-grow space-y-4 text-gray-700"> {{-- Increased space-y for better separation --}}
                            <p class="text-lg font-bold text-gray-900 mb-3 border-b pb-2">Log Details</p>

                            {{-- Driver & Vehicle Info Section --}}
                            <div class="bg-gray-50 p-4 rounded-md border border-gray-200">
                                <h4 class="font-semibold text-gray-800 mb-2">Driver & Vehicle</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                                    <p><span class="font-semibold">Car Driver:</span> <span class="text-gray-800">{{$log->user? $log->user->name:'(missing)'}}</span></p>
                                    <p><span class="font-semibold">Vehicle:</span> <span class="text-gray-800">{{$log->vehicle? $log->vehicle->name:'(missing)'}}</span> <span class="text-gray-600">({{$log->vehicle_position? $log->vehicle_position:''}})</span></p>
                                    <p><span class="font-semibold">Truck Driver:</span> <span class="text-gray-800">{{$log->truck_driver? $log->truck_driver->name . ' - ' . $log->truck_driver->phone:'(missing)'}}</span></p>
                                    <p><span class="font-semibold">Truck #:</span> <span class="text-gray-800">{{$log->truck_no? $log->truck_no:'(missing)'}}</span></p>
                                    <p><span class="font-semibold">Trailer #:</span> <span class="text-gray-800">{{$log->trailer_no? $log->trailer_no:'(missing)'}}</span></p>
                                </div>
                            </div>

                            {{-- Load & Trip Details Section --}}
                            <div class="bg-gray-50 p-4 rounded-md border border-gray-200">
                                <h4 class="font-semibold text-gray-800 mb-2">Load & Trip Details</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                                    <p><span class="font-semibold">Extra Load Stops:</span> <span class="text-gray-800">{{$log->extra_load_stops_count? $log->extra_load_stops_count:'0'}}</span></p>
                                    <p class="{{$log->load_canceled < 1? '':'text-red-600'}}"><span class="font-semibold">Load Canceled:</span> <span class="font-semibold">{{$log->load_canceled < 1? 'No':'Yes'}}</span></p>
                                    <p><span class="font-semibold">Is Deadhead?:</span> <span class="text-gray-800">{{$log->is_deadhead < 1? 'No':'Yes'}}</span></p>
                                    <p><span class="font-semibold">Wait Time (hours):</span> <span class="text-gray-800">{{$log->wait_time_hours? $log->wait_time_hours:'0'}}</span></p>
                                    <p><span class="font-semibold">Started At:</span> <span class="text-gray-800">{{$log->started_at? $log->started_at:'(missing)'}}</span></p>
                                    <p><span class="font-semibold">Ended At:</span> <span class="text-gray-800">{{$log->ended_at? $log->ended_at:'(missing)'}}</span></p>
                                </div>
                            </div>

                            {{-- Expenses Section --}}
                            <div class="bg-gray-50 p-4 rounded-md border border-gray-200">
                                <h4 class="font-semibold text-gray-800 mb-2">Expenses</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                                    <p><span class="font-semibold">Tolls (total):</span> <span class="text-gray-800">${{$log->tolls? number_format($log->tolls, 2):'0.00'}}</span></p>
                                    <p><span class="font-semibold">Gas (total):</span> <span class="text-gray-800">${{$log->gas? number_format($log->gas, 2):'0.00'}}</span></p>
                                    <p><span class="font-semibold">Hotel:</span> <span class="text-gray-800">${{$log->hotel? number_format($log->hotel, 2):'0.00'}}</span></p>
                                    <p><span class="font-semibold">Extra Charge:</span> <span class="text-gray-800">${{$log->extra_charge? number_format($log->extra_charge, 2):'0.00'}}</span></p>
                                </div>
                            </div>

                            {{-- Checks & Memos Section --}}
                            <div class="bg-gray-50 p-4 rounded-md border border-gray-200">
                                <h4 class="font-semibold text-gray-800 mb-2">Checks & Memos</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                                    <p class="{{$log->pretrip_check < 1? 'text-red-600':'text-green-600'}}"><span class="font-semibold">Pretrip Check?:</span> <span class="font-semibold">{{$log->pretrip_check < 1? 'No':'Yes'}}</span></p>
                                    <p><span class="font-semibold">Maintenance Memo:</span> <span class="text-gray-800">{{$log->maintenance_memo? $log->maintenance_memo:'(none)'}}</span></p>
                                </div>
                                <p class="mt-4"><span class="font-semibold">Memo:</span> <span class="text-gray-800">{{$log->memo? $log->memo:'(none)'}}</span></p>
                            </div>


                            <div class="overflow-x-auto mt-4">
                                <h4 class="font-semibold text-gray-800 mb-2">Mileage</h4>
                                <table class="responsive-table text-center">
                                    <thead>
                                        <tr>
                                            <th>Start Mileage</th>
                                            <th>Start Job Mileage</th>
                                            <th>End Job Mileage</th>
                                            <th>End Mileage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td data-label="Start Mileage">{{$log->start_mileage? $log->start_mileage:'(missing)'}}</td>
                                            <td data-label="Start Job Mileage">{{$log->start_job_mileage? $log->start_job_mileage:'(missing)'}}</td>
                                            <td data-label="End Job Mileage">{{$log->end_job_mileage? $log->end_job_mileage:'(missing)'}}</td>
                                            <td data-label="End Mileage">{{$log->end_mileage? $log->end_mileage:'(missing)'}}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4">
                                <span class="font-semibold">Attachments ({{$log->attachments? $log->attachments->count():0}}): </span>
                                <div class="space-y-2 mt-2">
                                    @foreach($log->attachments as $att)
                                    <div class="flex items-center gap-2">
                                        <livewire:delete-confirmation-button
                                            :action-url="route('attachments.destroy', ['attachment'=> $att->id])"
                                            button-text="&times;"
                                            :redirect-route="Route::currentRouteName()"
                                        />
                                        <a class="btn-base btn-action btn-primary flex items-center gap-1" download href="{{route('attachments.download', ['attachment'=>$att->id])}}">
                                            <span>&#128229;</span>{{$att->file_name}}
                                        </a>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-wrap justify-start gap-2 p-4 bg-gray-50 border-t border-gray-200">
                            @if(auth()->user()->can('update', $log))
                                <a href="{{route('logs.edit', ['log'=>$log->id])}}" class="btn-base btn-action btn-action-warning">Edit Log</a>
                            @endif
                            @if(auth()->user()->can('delete', $log))
                                <livewire:delete-confirmation-button
                                    :action-url="route('logs.destroy', ['log'=> $log->id])"
                                    button-text="Delete Log"
                                    :redirect-route="Route::currentRouteName()"
                                />
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </details>
        @endif
    </div>
</div>