<x-app-layout>
    <div>
        <div class="max-w-7xl mx-auto p-2">
            <h1 class="text-2xl font-bold text-center mb-2"><span class="text-xs">Customer:</span> {{ $customer->name }}</h1>

            <div class="flex flex-wrap gap-2 justify-center md:grid md:grid-cols-1">


                    <div class="card w-full">
                        <div class="p-2">
                        <p><b>name:</b> {{$customer->name}}</p>
                        <p><b>street:</b> {{$customer->street}}</p>
                        <p><b>city:</b> {{$customer->city}}</p>
                        <p><b>state:</b> {{$customer->state}}</p>
                        <p><b>zip:</b> {{$customer->zip}}</p>
                        @can('viewAny', new \App\Models\Organization)
                        <p><b>organization:</b> {{$customer->organization->name}}</p>
                        @endcan
                        </div>

                        <div class="card-actions">
                            <a href="{{route('customers.edit', ['customer'=>$customer->id])}}" class="button">edit</a>
                            <x-delete-form class="inline-block underline" action="{{route('customers.destroy', ['customer'=> $customer->id])}}" title="delete"/>
                        </div>
                    </div>

                    <h2>Contacts ({{$customer->contacts->count()}})</h2>

                    <table>
                        <thead>
                            <tr class="border border-2">
                                <th>name</th>
                                <th>phone</th>
                                <th>memo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($customer->contacts as $contact)
                            <tr class="border">
                                <td>{{$contact->name}}</td>
                                <td>{{$contact->phone}}</td>
                                <td>{{$contact->memo}}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        
                    </table>

                    <h2>Jobs ({{$customer->jobs->count()}})</h2>

                    <table class="text-center">
                        <thead>
                            <tr class="border border-2">
                                <th></th>
                                <th>Job#</th>
                                <th>Load#</th>
                                <th>Pickup @</th>
                                <th>delivery address</th>
                                <th>invoice</th>
                            </tr>
                        </thead>
                        <tbody>
                            <form action="{{route('my.invoices.store')}}" method="post">
                                @csrf
                                @foreach($customer->jobs as $job)
                                <tr class="border">
                                    <td><a href="{{route('my.jobs.show',['job'=>$job->id])}}">view</a></td>
                                    <td>{{$job->job_no}}</td>
                                    <td>{{$job->load_no}}</td>
                                    <td>{{$job->scheduled_pickup_at}}</td>
                                    <td>{{$job->delivery_address}}</td>
                                    <td>
                                        @if($job->invoice_paid)
                                            invoice paid
                                        @elseif(!$job->invoice)
                                            <input name="invoice_this[]" value="{{$job->id}}" type="checkbox"/> select for invoicing
                                        @else
                                            id: {{$job->invoice->id}} 
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                                <button class="fixed bottom-2 right-2">select & click here to create invoice</button>
                            </form>
                        </tbody>
                    </table>
        </div>
    </div>
</x-app-layout>
