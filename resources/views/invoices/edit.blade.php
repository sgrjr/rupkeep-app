<x-app-layout>
    <div>
        <div class="max-w-7xl mx-auto p-2">

            <div class="flex items-center justify-between">
                <h1 class="text-xl text-center font-bold">{{$invoice->customer->name}} {{$invoice->values['title']}}# {{$invoice->invoice_number}}</h1>
                <a href="{{route('my.invoices.print', ['invoice'=>$invoice->id])}}" target="_blank" class="btn-base btn-secondary">
                    Print Invoice
                </a>
            </div>
            <form class="mb-4" action="{{route('my.invoices.update',['invoice'=>$invoice->id])}}" method="post" autocomplete="off">
                @csrf
                <input name="_method" type="hidden" value="PUT">

                <div class="mt-4 flex justify-start gap-2 w-full">
                    <label for="paid_in_full" class="text-xl">Paid in Full?</label>
                    <select id="paid_in_full" name="paid_in_full" value="{{$invoice->paid_in_full? 'yes':'no' }}">
                        <option value="yes" {{$invoice->paid_in_full? 'selected':'' }}>Yes</option>
                        <option value="no" {{$invoice->paid_in_full? '':'selected' }}>No</option>
                    </select>
                    <label for="delete" class="text-xl" >Delete?</label>
                    <input id="delete" class="text-xl w-6 h-6 ml-auto mr-auto inline-block" name="delete" type="checkbox" />
                    <x-button>
                        {{ __('Save') }}
                    </x-button>
                </div>
            </form>

            <hr/>

            

            <form class="mt-4" action="{{route('my.invoices.update',['invoice'=>$invoice->id])}}" method="post" autocomplete="off">
                @csrf
                <input name="_method" type="hidden" value="PUT">

                <div class="grid grid-cols-2">
                    <div class="mt-4 flex flex-col gap-2">
                        <label for="logo" class="text-xl" >Logo Url: </label>
                        <input id="logo" name="title" type="text" value="{{{$invoice->values['logo']}}}" placeholder="logo url" />
                    </div>
                    <div class="mt-4 flex flex-col gap-2">
                        <label for="title" class="text-xl" >Title: </label>
                        <input id="title" name="title" type="text" value="{{{$invoice->values['title']}}}" placeholder="title" />
                    </div>
                </div>

                <details class="border p-2 m-2">
                    <summary class="text-xl font-bold">Bill From: {{$invoice->values['bill_from']['company']}} {{$invoice->values['bill_from']['street']}} ...</summary>

                    <div class="grid grid-cols-2">
                        <div class="mt-4 flex flex-col gap-2">
                            <label for="bill_from_company" class="text-xl" >Company: </label>
                            <input id="bill_from_company" name="bill_from_company" type="text" value="{{$invoice->values['bill_from']['company']}}" placeholder="bill from company" />
                        </div>
                        <div class="mt-4 flex flex-col gap-2">
                            <label for="bill_from_attention" class="text-xl" >Attention: </label>
                            <input id="bill_from_attention" name="bill_from_attention" type="text" value="{{$invoice->values['bill_from']['attention']}}" placeholder="bill from attention" />
                        </div>
                        <div class="mt-4 flex flex-col gap-2">
                            <label for="bill_from_street" class="text-xl" >Street: </label>
                            <input id="bill_from_street" name="bill_from_street" type="text" value="{{$invoice->values['bill_from']['street']}}" placeholder="bill from street" />
                        </div>
                        <div class="mt-4 flex flex-col gap-2">
                            <label for="bill_from_city" class="text-xl" >City: </label>
                            <input id="bill_from_city" name="bill_from_city" type="text" value="{{$invoice->values['bill_from']['city']}}" placeholder="title" />
                        </div>

                        
                        <div class="mt-4 flex flex-col gap-2">
                            <label for="bill_from_state" class="text-xl" >State: </label>
                            <input id="bill_from_state" name="bill_from_state" type="text" value="{{$invoice->values['bill_from']['state']}}" placeholder="bill from company" />
                        </div>
                        <div class="mt-4 flex flex-col gap-2">
                            <label for="bill_from_zip" class="text-xl" >Zip: </label>
                            <input id="bill_from_zip" name="bill_from_zip" type="text" value="{{$invoice->values['bill_from']['zip']}}" placeholder="title" />
                        </div>

                    </div>
                </details>

                <div class="border p-2 m-2">
                    <h2 class="text-xl font-bold">Bill To: </h2>

                    <div class="grid grid-cols-2">
                        <div class="mt-4 flex flex-col gap-2">
                            <label for="bill_to_company" class="text-xl" >Company: </label>
                            <input id="bill_to_company" name="bill_to_company" type="text" value="{{{$invoice->values['bill_to']['company']}}}" placeholder="bill from company" />
                        </div>
                        <div class="mt-4 flex flex-col gap-2">
                            <label for="bill_to_attention" class="text-xl" >Attention: </label>
                            <input id="bill_to_attention" name="bill_to_attention" type="text" value="{{{$invoice->values['bill_to']['attention']}}}" placeholder="bill from attention" />
                        </div>
                        <div class="mt-4 flex flex-col gap-2">
                            <label for="bill_to_street" class="text-xl" >Street: </label>
                            <input id="bill_to_street" name="bill_to_street" type="text" value="{{{$invoice->values['bill_to']['street']}}}" placeholder="bill from street" />
                        </div>
                        <div class="mt-4 flex flex-col gap-2">
                            <label for="bill_to_city" class="text-xl" >City: </label>
                            <input id="bill_to_city" name="bill_to_city" type="text" value="{{{$invoice->values['bill_to']['city']}}}" placeholder="title" />
                        </div>

                        
                        <div class="mt-4 flex flex-col gap-2">
                            <label for="bill_to_state" class="text-xl" >State: </label>
                            <input id="bill_to_state" name="bill_to_state" type="text" value="{{{$invoice->values['bill_to']['state']}}}" placeholder="bill from company" />
                        </div>
                        <div class="mt-4 flex flex-col gap-2">
                            <label for="bill_to_zip" class="text-xl" >Zip: </label>
                            <input id="bill_to_zip" name="bill_to_zip" type="text" value="{{{$invoice->values['bill_to']['zip']}}}" placeholder="title" />
                        </div>

                    </div>
                </div>

                <div class="flex justify-end w-full">
                    <x-button>
                        {{ __('Save') }}
                    </x-button>
                </div>
            </form>

            <div class="mt-6">
                <livewire:invoice-comments :invoice="$invoice" />
            </div>

<!-- 

            'footer' => 'Casco Bay Pilot Car would like to thank you for your business. Thank you!',
            'truck_driver_name' =>$this->getTruckDrivers($logs),
            'truck_number' =>$this->getTruckNumbers($logs),
            'trailer_number' =>$this->getTrailerNumbers($logs),
            'pickup_address' =>$this->pickup_address,
            'delivery_address' =>$this->delivery_address,
            'notes' =>$this->getInvoiceNotes($logs),
            'load_no' =>$this->load_no,
            'check_no' =>$this->check_no,
            'wait_time_hours' =>$this->totalWaitTimeHours($logs),
            'extra_load_stops_count' =>$this->totalExtraLoadStops($logs),
            'dead_head' =>$this->getTotalDeadHead($logs),
            'tolls' =>$this->getTotalTolls($logs),
            'hotel' =>$this->getTotalHotel($logs),
            'extra_charge' =>$this->getExtraCharges($logs),
            'cars_count' =>$this->getCarsCount($logs),
            'rate_code' =>$this->rate_code,
            'rate_value' =>$this->rate_value,
            'total_due' => 0.00,
            'billable_miles' => $miles['total_billable'],
            'nonbillable_miles' => $miles['total_nonbillable'],
        ];

        $values['total'] = $this->calculateTotalDue($values);
        $values['effective_rate_code'] = $values['total']['effective_rate_code'];
        $values['effective_rate_value'] = $values['total']['effective_rate_value'];
        $values['total'] = $values['total']['total'];            
-->
        </div>
    </div>
</x-app-layout>
