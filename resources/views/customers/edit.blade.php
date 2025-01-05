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

            <hr/>

            <form action="{{route('customer.contacts.store', ['customer'=> $customer->id])}}" method="post" class="mt-8">
                @csrf

                <input type="hidden" name="_method" value="post" />
                <input type="hidden" name="customer_id" value="{{$customer->id}}" />
                <input type="hidden" name="organization_id" value="{{$customer->organization->id}}" />

                <div class="mt-4">
                    <x-label for="name" value="{{ __('Contact/Driver Name') }}" />
                    <x-input id="name" class="block mt-1 w-full"  name="name" value="{{$customer->name}}" required />
                </div>

                <div class="mt-4">
                    <x-label for="phone" value="{{ __('Phone') }}" />
                    <x-input id="phone" class="block mt-1 w-full"  name="phone" value="{{$customer->phone}}" required />
                </div>

                <div class="mt-4">
                    <x-label for="memo" value="{{ __('Memo') }}" />
                    <x-input id="memo" class="block mt-1 w-full"  name="memo" value="{{$customer->memo}}" required />
                </div>

                <div class="flex items-center justify-end mt-4">
                <x-button>
                    {{ __('+Create Contact/Driver') }}
                </x-button>
            </div>
            </form>

            <hr/>
            
            <ul>
                @foreach($customer->contacts as $contact)
                <li><b>name: </b>{{$contact->name}} <b>phone: </b>{{$contact->phone}} <b>memo: </b>{{$contact->memo}}</li>
                @endforeach
            </ul>
        </div>


    </div>
</x-app-layout>
