<x-app-layout>
    <div>
        <div class="max-w-5xl mx-auto p-2">

            <form class="m-auto w-full flex justify-center">
                <input type="text" name="search_value"/>
                <select name="search_field" >
                    <option value="has_customer_name">Customer Name</option>
                    <option value="job_no">Job #</option>
                    <option value="load_no">Load #</option>
                    <option value="invoice_no">Invoice #</option>
                    <option value="check_no">Check #</option>
                    <option value="delivery_address">Delivery Address</option>
                    <option value="pickup_address">Pickup Address</option>
                    <option value="is_paid">Is Paid</option>
                    <option value="is_not_paid">Is NOT Paid</option>
                    <option value="is_canceled">Is Canceled</option>
                </select>
                <button type="submit">search</button>
            </form>
            <h1 class="text-2xl font-bold text-center mb-2">
                
            @if($customer)
                {{$customer->name}}
            @endif
            Jobs: ({{count($jobs)}})</h1>

            <div class="flex flex-wrap gap-2 justify-center md:grid md:grid-cols-3">

                @foreach($jobs AS $j)
                    <div class="card{{$j->invoice_paid < 1? ' unpaid-invoice':''}}">
                        <div class="p-2">
                        @can('viewAny', new \App\Models\Organization)
                        <p><b>organization:</b> {{$j->organization->name}}</p>
                        @endcan
                        <p><span class="font-bold italic">job#:</span> <span class="font-normal">{{$j->job_no}}</span></p>
                        <p><b>load#:</b> {{$j->load_no}}</p>
                        <p><b>customer:</b> {{$j->customer->name}}</p>
                        <p><b>scheduled pickup:</b> {{$j->pickup_address}} @ {{$j->scheduled_pickup_at}}</p>
                        <p><b>scheduled delivery:</b> {{$j->delivery_address}} @ {{$j->scheduled_delivery_at}}</p>
                        <p><b>check_no:</b> {{$j->check_no}}</p>
                        <p><b>invoice_paid:</b> {{$j->invoice_paid}}</p>
                        <p><b>invoice#:</b> {{$j->invoice_no}}</p>
                        <p><b>rate_code:</b> {{$j->rate_code}}</p>
                        <p><b>rate_value:</b> {{$j->rate_value}}</p>
                        @if($j->canceled_at)<p><b>canceled_at:</b> {{$j->canceled_at}}</p>@endif
                        @if($j->canceled_reason)<p><b>canceled_reason:</b> {{$j->canceled_reason}}</p>@endif
                        <p><b>memo:</b> 
                            @if(str_starts_with($j->memo, 'http'))
                                <a target="_blank" href="{!!$j->memo!!}" class="button">view invoice</a>
                            @else
                                {{$j->memo}}
                            @endif
                        </p>
                    </div>

                    <div class="card-actions">
                        @if(auth()->user()->can('update', $j))
                            <a href="{{route('my.jobs.edit', ['job'=>$j->id])}}" class="button">edit</a>
                        @endif
                        @if(auth()->user()->can('delete', $j))
                            <x-delete-form class="inline-block underline" action="{{route('my.jobs.destroy', ['job'=> $j->id])}}" title="delete"/>
                        @endif

                        <a href="{{route('my.jobs.show', ['job'=>$j->id])}}" class="button">view</a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>