<x-app-layout>
    <div>
        <div class="max-w-5xl mx-auto p-2">
            <h1 class="text-2xl font-bold text-center mb-2">Customers: ({{count($customers)}})</h1>

            <div class="flex flex-wrap gap-2 justify-center md:grid md:grid-cols-3">

                @foreach($customers AS $c)
                    <div class="card">
                        <div class="p-2">
                        <p><b>name:</b> {{$c->name}}</p>
                        <p><b>street:</b> {{$c->street}}</p>
                        <p><b>city:</b> {{$c->city}}</p>
                        <p><b>state:</b> {{$c->state}}</p>
                        <p><b>zip:</b> {{$c->zip}}</p>
                        @can('viewAny', new \App\Models\Organization)
                        <p><b>organization:</b> {{$c->organization->name}}</p>
                        @endcan
                        </div>

                        <div class="card-actions">
                            <a href="{{route('my.jobs.index', ['customer'=>$c->id])}}" class="button">jobs</a>
                            <a href="{{route('customers.edit', ['customer'=>$c->id])}}" class="button">edit</a>
                            <x-delete-form class="inline-block underline" action="{{route('customers.destroy', ['customer'=> $c->id])}}" title="delete"/>
                        </div>
                    </div>
                @endforeach

        </div>
    </div>
</x-app-layout>
