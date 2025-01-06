<x-app-layout>
    <div>
        <div class="max-w-5xl mx-auto p-2">
            <h1 class="text-2xl font-bold text-center mb-2">My Users: ({{count($users)}})</h1>

            <div class="flex flex-wrap gap-2 justify-center md:grid md:grid-cols-3">

                @foreach($users AS $u)
                    <div class="card">

                        <div class="p-2">
                            <p><b>name:</b> {{$u->name}}</p>
                            <p><b>email:</b> {{$u->email}}</p>
                            <p><b>role:</b> {{$u->organization_role}}</p>
                            @can('viewAny', new \App\Models\Organization)
                            <p><b>organization:</b> {{$u->organization->name}}</p>
                            @endcan
                        </div>
                        
                        <div class="card-actions">
                            <x-delete-form action="{{route('my.users.destroy', ['user'=> $u->id])}}" title="delete"/>
                            <a class="button" href="{{route('user.profile', ['user'=>$u->id])}}">edit</a>
                        </div>
                    </div>
                @endforeach
            </div>
            
                @if($all_users && count($all_users) > 0)
                <h2 class="text-2xl font-bold text-center mb-2">All Application Users ({{count($all_users)}}):</h2>
                <div class="flex flex-wrap gap-2 justify-center md:grid md:grid-cols-3">
                    @foreach($all_users AS $u)
                        <div class="card">

                            <div class="p-2">
                                <p><b>name:</b> {{$u->name}}</p>
                                <p><b>email:</b> {{$u->email}}</p>
                                <p><b>role:</b> {{$u->organization_role}}</p>
                                @can('viewAny', new \App\Models\Organization)
                                <p><b>organization:</b> {{$u->organization->name}}</p>
                                @endcan
                            </div>
                            
                            <div class="card-actions">
                                <x-delete-form action="{{route('my.users.destroy', ['user'=> $u->id])}}" title="delete"/>
                                <a class="button" href="{{route('user.profile', ['user'=>$u->id])}}">edit</a>
                            </div>
                        </div>
                    @endforeach
                    
                </div>
                @endif

    </div>
</x-app-layout>
