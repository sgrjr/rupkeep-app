<x-app-layout>
    <div>
        <a class="button w-full block text-center" href="{{route('my.customers.show', ['customer'=>$customer->id])}}">&larr;{{$customer->name}}</a>

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form action="{{route('customers.update', ['customer'=> $customer->id])}}" method="post">
                @csrf

                <input type="hidden" name="_method" value="PATCH" />
                
                <div class="mt-4">
                    <x-label for="name" value="{{ __('Company/Customer Name') }}" />
                    <x-input id="name" class="block mt-1 w-full"  name="name" value="{{$customer->name}}" required />
                </div>

                <div class="mt-4">
                    <x-label for="street" value="{{ __('Street Address') }}" />
                    <x-input id="street" class="block mt-1 w-full"  name="street" value="{{$customer->street}}" required />
                </div>

                <div class="mt-4">
                    <x-label for="city" value="{{ __('City') }}" />
                    <x-input id="city" class="block mt-1 w-full"  name="city" value="{{$customer->city}}" required />
                </div>

                <div class="mt-4">
                    <x-label for="state" value="{{ __('State') }}" />
                    <x-input id="state" class="block mt-1 w-full"  name="state" value="{{$customer->state}}" required />
                </div>

                <div class="mt-4">
                    <x-label for="zip" value="{{ __('Zip') }}" />
                    <x-input id="zip" class="block mt-1 w-full"  name="zip" value="{{$customer->zip}}" required />
                </div>

                <div class="flex items-center justify-end mt-4">
                <x-button>
                    {{ __('Save Changes') }}
                </x-button>
            </div>
            </form>


            @foreach($customer->contacts as $contact)
                <form action="{{route('customer.contacts.update', ['customer'=> $customer->id, 'contact' => $contact->id])}}" method="POST" class="border p-0">
                    @csrf
                    <input name="_method" type="hidden" value="PUT">

                    <div class="grid grid-cols-4 gap-1 ">
                        <div class="flex flex-col">
                            <label class="font-bold text-sm" for="nc_name{{$contact->id}}">Name: </label>
                            <input id="nc_name{{$contact->id}}" name="name" value="{{$contact->name}}" class="p-1 pt-0 pb-0"/>
                        </div>
                        <div class="flex flex-col">
                        <label class="font-bold text-sm" for="nc_phone{{$contact->id}}">Phone: </label>
                        <input id="nc_phone{{$contact->id}}" name="phone" value="{{$contact->phone}}" class="p-1 pt-0 pb-0"/>
                        </div>
                        <div class="flex flex-col">
                        <label class="font-bold text-sm" for="nc_email{{$contact->id}}">Email: </label>
                        <input id="nc_email{{$contact->id}}" name="email" value="{{$contact->email}}" type="email" class="p-1 pt-0 pb-0"/>
                        </div>
                        <div class="flex flex-col">
                        <label class="font-bold text-sm" for="nc_memo{{$contact->id}}">Memo: </label>
                        <input id="nc_memo{{$contact->id}}" name="memo" value="{{$contact->memo}}" class="p-1 pt-0 pb-0"/>
                        </div>
                    </div>

                    <div class="w-full flex justify-end gap-2">
                        <label class="font-bold text-sm" for="nc_memo{{$contact->id}}">Delete?: </label>
                        <input id="nc_delete{{$contact->id}}" name="delete" type="checkbox" class="p-1 pt-0 pb-0"/>
                        <button class="">save</button>
                    </div>
                    
                </form>

            @endforeach


            <div class="border p-2">
                <h2 class="text-center text-lg font-bold">New Contact / Driver</h2>
                <form action="{{route('customer.contacts.store', ['customer'=> $customer->id])}}" method="post" class="mt-8">
                    @csrf

                    <input type="hidden" name="_method" value="post" />
                    <input type="hidden" name="customer_id" value="{{$customer->id}}" />
                    <input type="hidden" name="organization_id" value="{{$customer->organization_id}}" />

                    <div class="mt-4">
                        <x-label for="name" value="{{ __('Contact/Driver Name') }}" />
                        <x-input id="name" class="block mt-1 w-full"  name="name" value="" required aria-placeholder="name"/>
                    </div>

                    <div class="mt-4">
                        <x-label for="email" value="{{ __('Email') }}" />
                        <x-input id="email" class="block mt-1 w-full"  name="email" value="" type="email" aria-placeholder="email" />
                    </div>

                    <div class="mt-4">
                        <x-label for="phone" value="{{ __('Phone') }}" />
                        <x-input id="phone" class="block mt-1 w-full"  name="phone" value="" aria-placeholder="phone"/>
                    </div>

                    <div class="mt-4">
                        <x-label for="memo" value="{{ __('Memo') }}" />
                        <x-input id="memo" class="block mt-1 w-full"  name="memo" value="" aria-placeholder="memo" />
                    </div>

                    <div class="flex items-center justify-end mt-4">
                    <x-button>
                        {{ __('+Create Contact/Driver') }}
                    </x-button>
                </div>
                </form>
            </div>
            <hr/>
            

        </div>


    </div>
</x-app-layout>
