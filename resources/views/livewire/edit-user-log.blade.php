@props(['log'=>(Object)[], 'car_drivers' => [], 'vehicles' => [], 'customer_contacts' => [], 'vehicle_positions' => []])

<div class="max-w-7xl mx-auto p-2">

<div class="text-center text-2xl flex flex-wrap">
    <a class="button" href="{{route('my.customers.show', ['customer'=>$log->job->customer_id])}}">{{$log->job->customer->name}}</a> | <a class="button" href="{{route('my.jobs.show', ['job'=>$log->job_id])}}">JOB:{{$log->job->job_no}}</a> | LOAD: {{$log->job->load_no}}</div>

<table class="w-full text-center border">
    <thead>
        <tr>
            <th>sched. pickup</th>
            <th>sched. delivery</th>
        </tr>
    </thead>
    <tbody>
        <tr class="border">
            <td>{{$log->job->scheduled_pickup_at}}</td>
            <td>{{$log->job->scheduled_delivery_at}}</td>
        </tr>
        <tr class="border">
            
            <td>{{$log->job->pickup_address}}</td>
            <td>{{$log->job->delivery_address}}</td>
        </tr>
    </tbody>
</table>

<div class="md:grid md:grid-cols-2 gap-2 w-full border border-2 m-2 p-2">
    <form wire:submit="uploadFile">
        <input type="file" wire:model="file">
        @error('file') <span class="error">{{ $message }}</span> @enderror

        <x-action-message class="me-3" on="uploaded">
            {{ __('File Uploaded.') }}
        </x-action-message>
        <button class="w-full" type="submit">After selecting a file click HERE to attach to log</button>
    </form>

    <div> <b>attachments ({{$log->attachments? $log->attachments->count():0}}): </b>
        @foreach($log->attachments as $att)
        <div class="flex">
        <x-delete-form class="inline-block underline text-red" action="{{route('attachments.destroy', ['attachment'=> $att->id])}}" title="X"/>
        <a class="button" download href="{{route('attachments.download', ['attachment'=>$att->id])}}"><span>&#128229;</span>{{$att->file_name}}</a>
        </div>
        @endforeach
    </div>

</div>

    <form wire:submit="saveLog" method="post" class="pretty-form relative w-full overflow-x-scroll pb-12">
        @csrf

        <div class="flex justify-end fixed bottom-2 right-2 w-full">

            <x-action-message class="me-3 text-center" on="saved">
                {{ __('Saved.') }}
            </x-action-message>

            <x-button class="text-center">
                {{ __('Save Changes') }}
            </x-button>
        </div>
        
        <div class="grid grid-cols-1">
            <div class="group flex">
                <label for="truck_no" class="input-label">Pretrip Check?</label>
                <input type="checkbox" id="truck_no" wire:model="form.pretrip_check" placeholder="pretrip_check">
                <label for="maintenance_memo" class="input-label">Maintenance Memo #:</label>
                <textarea type="text" id="trailer_no" wire:model="form.maintenance_memo" placeholder="maintenance_memo" ></textarea>
                @error('form.maintenance_memo')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>
        </div>

        
        <div class="grid grid-cols-1">
            <div class="group">
                <label class="input-label">Driver:</label>
                <select wire:model="form.car_driver_id">
                    @foreach($car_drivers as $car_driver)
                    <option value="{{$car_driver['value']}}">{{$car_driver['name']}}</option>
                    @endforeach
                </select>
                @error('form.car_driver_id')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-2">
            <div class="group">
                <label class="input-label">Vehicle:</label>
                <select wire:model="form.vehicle_id">
                    @foreach($vehicles as $vehicle)
                    <option value="{{$vehicle['value']}}">{{$vehicle['name']}}</option>
                    @endforeach
                </select>
                @error('form.vehicle_id')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="group">
                <label class="input-label">Vehicle Position:</label>
                <select wire:model="form.vehicle_position">
                    @foreach($vehicle_positions as $position)
                    <option value="{{$position['value']}}">{{$position['name']}}</option>
                    @endforeach
                </select>
                @error('form.vehicle_position')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1">

            <div class="group">
                <label class="input-label">Truck Driver:</label>
                <select wire:model="form.truck_driver_id">
                    @foreach($customer_contacts as $contact)
                    <option value="{{$contact['value']}}">{{$contact['name']}}</option>
                    @endforeach
                </select>
                @error('form.truck_driver_id')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <details><summary>new truck driver?</summary>            
                <div class="group border m-2 pl-2">
                    <label class="input-label"><i>New Truck Driver Name:</i></label>
                    <input type="text" id="new_truck_driver_name" wire:model="form.new_truck_driver_name" placeholder="use only if driver is not in list!">
                    @error('form.new_truck_driver_name')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror
                    <label class="input-label"><i>New Truck Driver Phone:</i></label>
                    <input type="text" id="new_truck_driver_phone" wire:model="form.new_truck_driver_phone" placeholder="use only if driver does not exist in list!">
                    @error('form.new_truck_driver_phone')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror
                    <label class="input-label"><i>New Truck Driver Memo:</i></label>
                    <input type="text" id="new_truck_driver_memo" wire:model="form.new_truck_driver_memo" placeholder="new driver memo">
                    @error('form.new_truck_driver_memo')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror
                </div>
            </details>
        </div>

        <div class="grid grid-cols-4">
            <div class="group">
                <label for="start_mileage">start_mileage</label>
                <input type="text" id="start_mileage" wire:model="form.start_mileage" placeholder="start mileage">
                @error('form.start_mileage')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="group">
                <label for="start_job_mileage">start job mileage</label>
                <input type="text" id="start_job_mileage" wire:model="form.start_job_mileage" placeholder="start_job_mileage">
                @error('form.start_job_mileage')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="group">
                <label for="end_job_mileage">end_job_mileage</label>
                <input type="text" id="end_job_mileage" wire:model="form.end_job_mileage" placeholder="end_job_mileage">
                @error('form.end_job_mileage')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror            
            </div>

            <div class="group">
                <label for="end_mileage">end_mileage</label>
                <input type="text" id="end_mileage" wire:model="form.end_mileage" placeholder="end_mileage">
                @error('form.end_mileage')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror            
            </div>
        </div>

        <div class="grid grid-cols-2">
            <div class="group">
                <label for="truck_no" class="input-label">Truck #:</label>
                <input type="text" id="truck_no" wire:model="form.truck_no" placeholder="truck number">
                @error('form.truck_no')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="group">
                <label for="load_no" class="input-label">Trailer #:</label>
                <input type="text" id="trailer_no" wire:model="form.trailer_no" placeholder="trailer number">
                @error('form.trailer_no')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-2">
            <div class="group">
                <label for="started_at">Started</label>
                <input type="text" id="started_at" wire:model="form.started_at" placeholder="select date+tine">
                @error('form.started_at')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <div class="group">
                <label for="ended_at">Ended</label>
                <input type="text" id="ended_at" wire:model="form.ended_at" placeholder="select date+time">
                @error('form.ended_at')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror            
            </div>
        </div>


        <div class="group grid grid-cols-4">

            <label for="is_deadhead" class="inline">is Deadhead?</label>
            <div>
                <input type="checkbox" class="inline" id="is_deadhead" wire:model="form.is_deadhead" placeholder="is_deadhead">
                @error('form.is_deadhead')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>

            <label for="load_canceled" class="inline">Load Canceled?</label>
            <div>
                <input type="checkbox" class="inline" id="load_canceled" wire:model="form.load_canceled" placeholder="load_canceled">
                @error('form.load_canceled')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1">
            <div class="group flex">
                <label for="extra_charge" class="input-label">extra_charge</label>
                <input type="text" id="extra_charge" wire:model="form.extra_charge" placeholder="extra_charge">
                @error('form.extra_charge')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>
        </div>


        <div class="grid grid-cols-1">
            <div class="group flex">
                <label for="extra_load_stops_count" class="input-label">extra_load_stops_count</label>
                <input type="number" id="extra_load_stops_count" wire:model="form.extra_load_stops_count" placeholder="extra_load_stops_count">
                @error('form.extra_load_stops_count')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>
        </div>            

        <div class="grid grid-cols-1">
            <div class="group flex">
                <label for="wait_time_hours" class="input-label">wait_time_hours</label>
                <input type="text" id="wait_time_hours" wire:model="form.wait_time_hours" placeholder="wait_time_hours">
                @error('form.wait_time_hours')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>
        </div>           

        <div class="grid grid-cols-1">
            <div class="group flex">
                <label for="tolls" class="input-label">tolls</label>
                <input type="text" id="tolls" wire:model="form.tolls" placeholder="tolls? 0.00">
                @error('form.tolls')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>
        </div>
            
        <div class="grid grid-cols-1">
            <div class="group flex">
                <label for="gas" class="input-label">gas</label>
                <input type="text" id="gas" wire:model="form.gas" placeholder="gas? 0.00">
                @error('form.gas')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>
        </div>            

        <div class="grid grid-cols-1">
            <div class="group flex">
                <label for="hotel" class="input-label">hotel</label>
                <input type="text" id="hotel" wire:model="form.hotel" placeholder="hotel costs 0.00">
                @error('form.hotel')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="group">
            <label for="memo">Memo</label>
            <textarea type="text" id="memo" wire:model="form.memo" placeholder="memo"></textarea> 
            @error('form.memo')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror           
        </div>

    </form>
</div>

@script
<script>

flatpickr('#scheduled_pickup_at', {
    "dateFormat": "Y-m-d H:i",
    "enableTime":true,
    "defaultHour": 6
});

flatpickr('#scheduled_delivery_at', {
    //"dateFormat": "m/d/y H:i",
    "enableTime":true,
    "defaultHour": 6
});

</script>
@endscript