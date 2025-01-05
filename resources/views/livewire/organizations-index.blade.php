<div class="flex flex-wrap gap-2 justify-center p-2 md:grid md:grid-cols-3">
    @foreach($organizations AS $org)
        <div class="card">

            <div class="p-2">
                <p><b>Organization Name:</b> {{$org['name']}}</p>
                <p><b>Primary Contact:</b> {{$org['primary_contact']}}</p>
                <p><b>Telephone:</b> {{$org['telephone']}}</p>
                <p><b>Fax:</b> {{$org['fax']}}</p>
                <p><b>Email:</b> {{$org['email']}}</p>
                <p><b>Website:</b> {{$org['website_url']}}</p>
                <p><b>Street:</b> {{$org['street']}}</p>
                <p><b>City:</b> {{$org['city']}}</p>
                <p><b>State:</b> {{$org['state']}}</p>
                <p><b>Zip Code:</b> {{$org['zip']}}</p>
            </div>
            
            <div class="card-actions">
                <x-delete-form action="{{route('organizations.delete', ['organization'=> $org['id']])}}" title="delete"/>
                <a href="{{route('organizations.edit', ['organization'=> $org['id']])}}" class="button">edit</a>
                <div class="action"><a href="{{route('organizations.show', ['organization'=> $org['id']])}}" class="button">view</a>
            </div>
        </div>
    @endforeach
</div>