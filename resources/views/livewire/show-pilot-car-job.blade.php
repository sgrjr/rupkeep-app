@props(['drivers'=>[], 'vehicles'=>[]])

<div class="max-w-7xl mx-auto p-2">

@if($job->customer_id)
<a class="button w-full block text-center" href="{{route('my.customers.show', ['customer'=>$job->customer_id])}}">&larr;{{$job->customer->name}}</a>
@endif
    <h2 class="font-bold">Job details: </h2>
    <div class="card">
        <div class="p-2">
        @can('viewAny', new \App\Models\Organization)
        <p><b>organization:</b> {{$job->organization->name}}</p>
        @endcan
        <p><span class="font-bold italic">job#:</span> <span class="font-normal">{{$job->job_no}}</span></p>
        <p><b>load#:</b> {{$job->load_no}}</p>
        
        <p><b>customer:</b> @if($job->customer) {{$job->customer->name}} @endif </p>
        
        @if($job->customer) 
        <p class="pl-2"><details><summary>contact info: </summary> {{$job->customer->street}} {{$job->customer->city}}, {{$job->customer->state}} {{$job->customer->zip}} #
  

        <table class="border">
            <thead>
                <tr>
                    <th>name</th>
                    <th>phone</th>
                    <th>memo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($job->customer->contacts as $contact)
                <tr>
                    <td>{{$contact->name}}</td>
                    <td>{{$contact->phone}}</td>
                    <td>{{$contact->memo}}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
        </details></p>
        <p><b>scheduled pickup:</b> {{$job->pickup_address}} @ {{$job->scheduled_pickup_at}}</p>
        <p><b>scheduled delivery:</b> {{$job->delivery_address}} @ {{$job->scheduled_delivery_at}}</p>
        <p><b>check_no:</b> {{$job->check_no}}</p>
        <p><b>invoice_paid:</b> {{$job->invoice_paid}}</p>
        <p><b>invoice#:</b> {{$job->invoice_no}}</p>
        <p><b>rate_code:</b> {{$job->rate_code}}</p>
        <p><b>rate_value:</b> {{$job->rate_value}}</p>
        @if($job->canceled_at)<p><b>canceled_at:</b> {{$job->canceled_at}}</p>@endif
        @if($job->canceled_reason)<p><b>canceled_reason:</b> {{$job->canceled_reason}}</p>@endif
        <p><b>memo:</b> 
            @if(str_starts_with($job->memo, 'http'))
                <a target="_blank" href="{!!$job->memo!!}" class="button">view invoice</a>
            @else
                {{$job->memo}}
            @endif
        </p>
        <p><b>invoice:</b> {{$job->invoices_count}}</p>
    </div>

    <div class="card-actions">
        @if(auth()->user()->can('update', $job))
            <a href="{{route('my.jobs.edit', ['job'=>$job->id])}}" class="button">edit</a>
        @endif
        @if(auth()->user()->can('delete', $job))
            <x-delete-form class="inline-block underline" action="{{route('my.jobs.destroy', ['job'=> $job->id])}}" title="delete"/>
        @endif
    </div>
</div>

@if($job->logs)

<h2 class="font-bold">Job Logs ({{$job->logs->count()}}): </h2>

    <form wire:submit="assignJob" class="flex border p-2">
        <label class="input-label">Driver: </label>
        <select wire:model.blur="assignment.car_driver_id" class="bg-gray-100" required>
            @foreach($drivers as $driver)
            <option value="{{$driver['value']}}">{{$driver['name']}}</option>
            @endforeach
        </select>

        <label class="input-label">Vehicle: </label>
        <select wire:model.blur="assignment.vehicle_id" class="bg-gray-100">
            @foreach($vehicles as $vehicle)
            <option value="{{$vehicle['value']}}">{{$vehicle['name']}}</option>
            @endforeach
        </select>

        <label class="input-label">Vehicle Position: </label>
        <select wire:model.blur="assignment.vehicle_position" class="bg-gray-100">
            @foreach($vehicle_positions as $position)
            <option value="{{$position['value']}}">{{$position['name']}}</option>
            @endforeach
        </select>

        <x-action-message class="me-3" on="updated">
            {{ __('Added.') }}
        </x-action-message>
        <button class="w-full" type="submit">Click to Assign Job</button>
    </form>

@foreach($job->logs as $log)
<div class="card">
    <div class="p-2 grid grid-cols-2">
        <p><span class="italic">car driver:</span> <span>{{$log->user? $log->user->name:'(missing)'}}</span></p>
        <p><span class="italic">vehicle:</span> <span>{{$log->vehicle? $log->vehicle->name:'(missing)'}}</span> <span>({{$log->vehicle_position? $log->vehicle_position:''}})</span></p>
        <p><span class="italic">truck driver:</span> <span>{{$log->truck_driver? $log->truck_driver->name . ' - ' . $log->truck_driver->phone:'(missing)'}}</span></p>
        <p><span class="italic">truck #:</span> <span>{{$log->truck_no? $log->truck_no:'(missing)'}}</span></p>
        <p><span class="italic">trailer #:</span> <span>{{$log->trailer_no? $log->trailer_no:'(missing)'}}</span></p>
       
        <p><span class="italic">extra load stops:</span> <span>{{$log->extra_load_stops_count? $log->extra_load_stops_count:'0'}}</span></p>
        <p class="{{$log->load_canceled < 1? '':'text-red-500'}}"><span class="italic">load canceled:</span> <span>{{$log->load_canceled < 1? 'no':'yes'}}</span></p>
        <p><span class="italic">is deadhead?:</span> <span>{{$log->is_deadhead < 1? 'no':'yes'}}</span></p>
        <p><span class="italic">wait time (hours):</span> <span>{{$log->wait_time_hours? $log->wait_time_hours:'0'}}</span></p>
        <p><span class="italic">tolls (total):</span> <span>${{$log->tolls? $log->tolls:'0.00'}}</span></p>
        <p><span class="italic">gas (total):</span> <span>${{$log->gas? $log->gas:'0.00'}}</span></p>
        <p><span class="italic">hotel:</span> <span>${{$log->hotel? $log->hotel:'0.00'}}</span></p>
        <p><span class="italic">extra charge:</span> <span>${{$log->extra_charge? $log->extra_charge:'0.00'}}</span></p>
        <p class="{{$log->pretrip_check < 1? 'text-red-500':'text-green-500'}}"><span class="italic">pretrip check?:</span> <span>{{$log->pretrip_check < 1? 'no':'yes'}}</span></p>
        <p><span class="italic">maintenance memo:</span> <span>{{$log->maintenance_memo? $log->maintenance_memo:'(none)'}}</span></p>
        <p><span class="italic">started at:</span> <span>{{$log->started_at? $log->started_at:'(missing)'}}</span></p>
        <p><span class="italic">ended at:</span> <span>{{$log->ended_at? $log->ended_at:'(missing)'}}</span></p>
    </div>

    <p><span class="italic">memo:</span> <span>{{$log->memo? $log->memo:'(none)'}}</span></p>

    <table class="w-full border text-center">
        <thead>
            <tr>
                <th>start mileage</th>
                <th>start job mileage</th>
                <th>end job mileage</th>
                <th>end mileage</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><span>{{$log->start_mileage? $log->start_mileage:'(missing)'}}</span></td>
                <td><span>{{$log->start_job_mileage? $log->start_job_mileage:'(missing)'}}</span></td>
                <td><span>{{$log->end_job_mileage? $log->end_job_mileage:'(missing)'}}</span></td>
                <td> <span>{{$log->end_mileage? $log->end_mileage:'(missing)'}}</span></td>
            </tr>
        </tbody>
    </table>

    <div> <b>attachments ({{$log->attachments? $log->attachments->count():0}}): </b>
        @foreach($log->attachments as $att)
        <a class="button" download href="{{route('attachments.download', ['attachment'=>$att->id])}}">{{$att->file_name}}</a>, 
        @endforeach
    </div>
    <div class="card-actions">
        @if(auth()->user()->can('update', $log))
            <a href="{{route('logs.edit', ['log'=>$log->id])}}" class="button">edit</a>
        @endif
        @if(auth()->user()->can('delete', $log))
            <x-delete-form class="inline-block underline" action="{{route('logs.destroy', ['log'=> $log->id])}}" title="delete"/>
        @endif
    </div>
</div>
@endforeach
@endif


</div>