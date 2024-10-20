<x-app-layout>
    <div>
        <div class="max-w-5xl mx-auto p-2">
            <h1 class="text-2xl font-bold text-center mb-2">Vehicles: ({{count($vehicles)}})</h1>

            <div class="flex flex-wrap gap-2 justify-center md:grid md:grid-cols-3">

                @foreach($vehicles AS $v)
                    <div class="card">
                        <div class="p-2">
                        <p><b>name:</b> {{$v->name}}</p>
                        <p><b>odometer:</b> {{$v->odometer}}</p>
                        <p><b>odometer last date updated:</b> {{$v->odometer_updated_at? $v->odometer_updated_at:'(none)'}}</p>
                        <p><b>currently assigned driver:</b> {{$v->user?$v->name:'(none)'}}</p>
                        @can('viewAny', new \App\Models\Organization)
                        <p><b>organization:</b> {{$v->organization->name}}</p>
                        @endcan
                        </div>

                        <div class="card-actions">
                            <a href="{{route('my.vehicles.edit', ['vehicle'=>$v->id])}}" class="button">edit</a>
                            <x-delete-form class="inline-block underline" action="{{route('my.vehicles.destroy', ['vehicle'=> $v->id])}}" title="delete"/>
                        </div>
                    </div>
                @endforeach
        </div>
    </div>
</x-app-layout>