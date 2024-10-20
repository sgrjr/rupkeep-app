<x-app-layout>
    <div>
        <div class="max-w-5xl mx-auto p-2">
            <h1 class="text-2xl font-bold text-center mb-2">Jobs: ({{count($jobs)}})</h1>

            <div class="flex flex-wrap gap-2 justify-center md:grid md:grid-cols-3">

                @foreach($jobs AS $j)
                    <div class="card">
                        <div class="p-2">
                        <p><b>job_no:</b> {{$j->job_no}}</p>
                        <p><b>odometer:</b> {{$j->odometer}}</p>
                        <p><b>odometer last date updated:</b> {{$j->odometer_updated_at? $v->odometer_updated_at:'(none)'}}</p>
                        <p><b>currently assigned driver:</b> {{$j->user?$v->name:'(none)'}}</p>
                        @can('viewAny', new \App\Models\Organization)
                        <p><b>organization:</b> {{$j->organization->name}}</p>
                        @endcan

                        <p>NOT COMPLETE!!!         'customer_id',
        'scheduled_pickup_at',
        'scheduled_delivery_at',
        'load_no',
        'pickup_address',
        'delivery_address',
        'check_no',
        'invoice_paid',
        'invoice_no',
        'rate_code',
        'rate_value',
        'canceled_at',
        'canceled_reason',
        'memo'</p>
                        </div>

                        <div class="card-actions">
                            <a href="{{route('my.jobs.edit', ['job'=>$j->id])}}" class="button">edit</a>
                            <x-delete-form class="inline-block underline" action="{{route('my.jobs.destroy', ['job'=> $j->id])}}" title="delete"/>
                        </div>
                    </div>
                @endforeach
        </div>
    </div>
</x-app-layout>