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

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="flex flex-col">
                            <label class="font-bold text-sm" for="nc_name{{$contact->id}}">Name: </label>
                            <input id="nc_name{{$contact->id}}" name="name" value="{{$contact->name}}" class="p-1 pt-0 pb-0 rounded border border-slate-200"/>
                        </div>
                        <div class="flex flex-col">
                            <label class="font-bold text-sm" for="nc_phone{{$contact->id}}">Phone: </label>
                            <input id="nc_phone{{$contact->id}}" name="phone" value="{{$contact->phone}}" class="p-1 pt-0 pb-0 rounded border border-slate-200"/>
                        </div>
                        <div class="flex flex-col">
                            <label class="font-bold text-sm" for="nc_email{{$contact->id}}">Email: </label>
                            <input id="nc_email{{$contact->id}}" name="email" value="{{$contact->email}}" type="email" class="p-1 pt-0 pb-0 rounded border border-slate-200"/>
                        </div>
                        <div class="flex flex-col">
                            <label class="font-bold text-sm" for="nc_memo{{$contact->id}}">Memo: </label>
                            <input id="nc_memo{{$contact->id}}" name="memo" value="{{$contact->memo}}" class="p-1 pt-0 pb-0 rounded border border-slate-200"/>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="font-bold text-sm" for="nc_notification_address{{$contact->id}}">Notification Address (SMS Gateway): </label>
                        <input id="nc_notification_address{{$contact->id}}" name="notification_address" value="{{$contact->notification_address}}" placeholder="2074168659@mms.uscc.net" class="mt-1 block w-full p-1 pt-0 pb-0 rounded border border-slate-200"/>
                        <p class="mt-1 text-xs text-slate-500">For SMS notifications, use format: phone@gateway (e.g., 2074168659@mms.uscc.net). Leave blank to use email.</p>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-4">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_main_contact" value="1" {{ $contact->is_main_contact ? 'checked' : '' }} class="rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                            <span class="text-sm font-medium text-slate-700">{{ __('Main Contact') }}</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_billing_contact" value="1" {{ $contact->is_billing_contact ? 'checked' : '' }} class="rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                            <span class="text-sm font-medium text-slate-700">{{ __('Billing Contact') }}</span>
                        </label>
                    </div>

                    <div class="mt-3 w-full flex justify-end gap-2">
                        <label class="flex items-center gap-2">
                            <span class="text-sm font-medium text-red-600">{{ __('Delete?') }}</span>
                            <input id="nc_delete{{$contact->id}}" name="delete" type="checkbox" class="rounded border-slate-300 text-red-600 focus:ring-red-500"/>
                        </label>
                        <button class="rounded bg-orange-500 px-4 py-1 text-sm font-semibold text-white hover:bg-orange-600">{{ __('Save') }}</button>
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

                    <div class="mt-4">
                        <x-label for="notification_address" value="{{ __('Notification Address (SMS Gateway)') }}" />
                        <x-input id="notification_address" class="block mt-1 w-full"  name="notification_address" value="" placeholder="2074168659@mms.uscc.net" />
                        <p class="mt-1 text-xs text-slate-500">For SMS notifications, use format: phone@gateway (e.g., 2074168659@mms.uscc.net). Leave blank to use email.</p>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-4">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_main_contact" value="1" class="rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                            <span class="text-sm font-medium text-slate-700">{{ __('Main Contact') }}</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_billing_contact" value="1" class="rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                            <span class="text-sm font-medium text-slate-700">{{ __('Billing Contact') }}</span>
                        </label>
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
