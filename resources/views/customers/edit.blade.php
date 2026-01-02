<x-app-layout>
    <style>
        details summary {
            list-style: none;
        }
        details summary::-webkit-details-marker {
            display: none;
        }
        details[open] summary {
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
        }
    </style>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide">{{ __('Edit Customer') }}</p>
                <h1 class="text-xl font-semibold">{{ $customer->name }}</h1>
                <p class="text-xs">{{ $customer->city }}, {{ $customer->state }} {{ $customer->zip }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('my.customers.show', ['customer' => $customer->id]) }}"
                   class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-orange-300 hover:text-orange-600">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    {{ __('Back to customer') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">

        <div class="rounded-2xl border border-blue-200 bg-blue-50/60 px-4 py-3 shadow-sm">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 flex-shrink-0 text-blue-600 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
                </svg>
                <div>
                    <p class="text-sm font-semibold text-blue-900">{{ __('Click on any section below to expand and make changes') }}</p>
                    <p class="mt-1 text-xs text-blue-700">{{ __('All sections are collapsed by default so you can view all information at a glance. Click any section to expand and edit.') }}</p>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <!-- Customer Information Card -->
            <details class="group rounded-3xl border border-slate-200 bg-white/90 shadow-sm">
                <summary class="cursor-pointer list-none p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <h2 class="text-lg font-semibold text-slate-900">{{ __('Customer Information') }}</h2>
                            <p class="mt-1 text-sm text-slate-600">
                                <strong>{{ $customer->name }}</strong>
                                @if($customer->street || $customer->city || $customer->state || $customer->zip)
                                    — {{ trim(implode(', ', array_filter([$customer->street, $customer->city, $customer->state, $customer->zip]))) }}
                                @endif
                            </p>
                        </div>
                        <svg class="h-5 w-5 text-slate-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                        </svg>
                    </div>
                </summary>
                <div class="border-t border-slate-200 p-6">
                    <form action="{{route('customers.update', ['customer'=> $customer->id])}}" method="post">
                        @csrf
                        <input type="hidden" name="_method" value="PATCH" />
                        
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="sm:col-span-2 lg:col-span-3">
                                <x-label for="name" value="{{ __('Company/Customer Name') }}" />
                                <x-input id="name" class="block mt-1 w-full"  name="name" value="{{$customer->name}}" required />
                            </div>

                            <div class="sm:col-span-2">
                                <x-label for="street" value="{{ __('Street Address') }}" />
                                <x-input id="street" class="block mt-1 w-full"  name="street" value="{{$customer->street}}" required />
                            </div>

                            <div>
                                <x-label for="city" value="{{ __('City') }}" />
                                <x-input id="city" class="block mt-1 w-full"  name="city" value="{{$customer->city}}" required />
                            </div>

                            <div>
                                <x-label for="state" value="{{ __('State') }}" />
                                <x-input id="state" class="block mt-1 w-full"  name="state" value="{{$customer->state}}" required />
                            </div>

                            <div>
                                <x-label for="zip" value="{{ __('Zip') }}" />
                                <x-input id="zip" class="block mt-1 w-full"  name="zip" value="{{$customer->zip}}" required />
                            </div>

                            <div>
                                <x-label for="account_credit" value="{{ __('Account Credit') }}" />
                                <x-input id="account_credit" 
                                         class="block mt-1 w-full" 
                                         name="account_credit" 
                                         type="number" 
                                         step="0.01" 
                                         min="0"
                                         value="{{ number_format($customer->account_credit ?? 0, 2, '.', '') }}" />
                                <p class="mt-1 text-xs text-slate-500">{{ __('Current account credit balance. This can be applied to invoices.') }}</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <x-button>
                                {{ __('Save Changes') }}
                            </x-button>
                        </div>
                    </form>
                </div>
            </details>


            <!-- Existing Contacts -->
            @foreach($customer->contacts as $contact)
                <details class="group rounded-3xl border border-slate-200 bg-white/90 shadow-sm">
                    <summary class="cursor-pointer list-none p-6">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <h2 class="text-lg font-semibold text-slate-900">{{ __('Contact') }}: {{ $contact->name }}</h2>
                                <p class="mt-1 text-sm text-slate-600">
                                    @php
                                        $parts = [];
                                        if($contact->phone) $parts[] = __('Phone') . ': ' . $contact->phone;
                                        if($contact->email) $parts[] = __('Email') . ': ' . $contact->email;
                                        if($contact->memo) $parts[] = __('Memo') . ': ' . $contact->memo;
                                        $roles = [];
                                        if($contact->is_main_contact) $roles[] = __('Main Contact');
                                        if($contact->is_billing_contact) $roles[] = __('Billing Contact');
                                        if(!empty($roles)) $parts[] = '(' . implode(', ', $roles) . ')';
                                    @endphp
                                    {{ implode(' • ', $parts) ?: __('No additional information') }}
                                </p>
                            </div>
                            <svg class="h-5 w-5 text-slate-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                            </svg>
                        </div>
                    </summary>
                    <div class="border-t border-slate-200 p-6">
                        <form action="{{route('customers.contacts.update', ['customer'=> $customer->id, 'contact' => $contact->id])}}" method="POST">
                            @csrf
                            <input name="_method" type="hidden" value="PUT">

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div class="flex flex-col">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-600" for="nc_name{{$contact->id}}">{{ __('Name') }}</label>
                                    <input id="nc_name{{$contact->id}}" name="name" value="{{$contact->name}}" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"/>
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-600" for="nc_phone{{$contact->id}}">{{ __('Phone') }}</label>
                                    <input id="nc_phone{{$contact->id}}" name="phone" value="{{$contact->phone}}" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"/>
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-600" for="nc_email{{$contact->id}}">{{ __('Email') }}</label>
                                    <input id="nc_email{{$contact->id}}" name="email" value="{{$contact->email}}" type="email" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"/>
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-600" for="nc_memo{{$contact->id}}">{{ __('Memo') }}</label>
                                    <input id="nc_memo{{$contact->id}}" name="memo" value="{{$contact->memo}}" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"/>
                                </div>
                            </div>
                            <div class="mt-4">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-600" for="nc_notification_address{{$contact->id}}">{{ __('Notification Address (SMS Gateway)') }}</label>
                                <input id="nc_notification_address{{$contact->id}}" name="notification_address" value="{{$contact->notification_address}}" placeholder="2074168659@mms.uscc.net" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"/>
                                <div class="mt-1 text-xs text-slate-500">
                                    <p class="mb-2">{{ __('For SMS notifications, use format: phone@gateway. Leave blank to use email address.') }}</p>
                                    <details class="mt-2">
                                        <summary class="cursor-pointer text-blue-600 hover:text-blue-800 underline">{{ __('View supported MMS gateway formats') }}</summary>
                                        <div class="mt-2 pl-4 space-y-1 text-xs">
                                            @php
                                                $examplePhone = '2074168659';
                                                $smsGateways = config('sms_gateways.providers', []);
                                            @endphp
                                            @foreach($smsGateways as $key => $provider)
                                                <p>{{ $provider['name'] }} (MMS) <code class="bg-slate-100 px-1 rounded">{{ $examplePhone }}{{ $provider['mms'] }}</code></p>
                                            @endforeach
                                        </div>
                                    </details>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap items-center gap-4">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="is_main_contact" value="1" {{ $contact->is_main_contact ? 'checked' : '' }} class="rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                                    <span class="text-sm font-medium text-slate-700">{{ __('Main Contact') }}</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="is_billing_contact" value="1" {{ $contact->is_billing_contact ? 'checked' : '' }} class="rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                                    <span class="text-sm font-medium text-slate-700">{{ __('Billing Contact') }}</span>
                                </label>
                            </div>

                            <div class="mt-6 flex items-center justify-end gap-3">
                                <label class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-red-600">{{ __('Delete?') }}</span>
                                    <input id="nc_delete{{$contact->id}}" name="delete" type="checkbox" class="rounded border-slate-300 text-red-600 focus:ring-red-500"/>
                                </label>
                                <button class="rounded-full border border-orange-200 bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-orange-600">{{ __('Save') }}</button>
                            </div>
                        </form>
                    </div>
                </details>
            @endforeach


            <!-- New Contact Form -->
            <details class="group rounded-3xl border border-slate-200 bg-white/90 shadow-sm">
                <summary class="cursor-pointer list-none p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <h2 class="text-lg font-semibold text-slate-900">{{ __('New Contact / Driver') }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ __('Click to expand and add a new contact or driver to this customer account.') }}</p>
                        </div>
                        <svg class="h-5 w-5 text-slate-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                        </svg>
                    </div>
                </summary>
                <div class="border-t border-slate-200 p-6">
                    <form action="{{route('customers.contacts.store', ['customer'=> $customer->id])}}" method="post">
                        @csrf
                        <input type="hidden" name="_method" value="post" />
                        <input type="hidden" name="customer_id" value="{{$customer->id}}" />
                        <input type="hidden" name="organization_id" value="{{$customer->organization_id}}" />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-label for="name" value="{{ __('Contact/Driver Name') }}" />
                                <x-input id="name" class="block mt-1 w-full"  name="name" value="" required aria-placeholder="name"/>
                            </div>

                            <div>
                                <x-label for="email" value="{{ __('Email') }}" />
                                <x-input id="email" class="block mt-1 w-full"  name="email" value="" type="email" aria-placeholder="email" />
                            </div>

                            <div>
                                <x-label for="phone" value="{{ __('Phone') }}" />
                                <x-input id="phone" class="block mt-1 w-full"  name="phone" value="" aria-placeholder="phone"/>
                            </div>

                            <div>
                                <x-label for="memo" value="{{ __('Memo') }}" />
                                <x-input id="memo" class="block mt-1 w-full"  name="memo" value="" aria-placeholder="memo" />
                            </div>

                            <div class="sm:col-span-2">
                                <x-label for="notification_address" value="{{ __('Notification Address (SMS Gateway)') }}" />
                                <x-input id="notification_address" class="block mt-1 w-full"  name="notification_address" value="" placeholder="email@example.com or 2074168659@mms.uscc.net" />
                                <div class="mt-1 text-xs text-slate-500">
                                    <p class="mb-2">{{ __('For SMS notifications, use format: phone@gateway. Leave blank to use email address.') }}</p>
                                    <details class="mt-2">
                                        <summary class="cursor-pointer text-blue-600 hover:text-blue-800 underline">{{ __('View supported MMS gateway formats') }}</summary>
                                        <div class="mt-2 pl-4 space-y-1 text-xs">
                                            @php
                                                $examplePhone = '2074168659';
                                                $smsGateways = config('sms_gateways.providers', []);
                                            @endphp
                                            @foreach($smsGateways as $key => $provider)
                                                <p>{{ $provider['name'] }} (MMS) <code class="bg-slate-100 px-1 rounded">{{ $examplePhone }}{{ $provider['mms'] }}</code></p>
                                            @endforeach
                                        </div>
                                    </details>
                                </div>
                            </div>
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

                        <div class="flex items-center justify-end mt-6">
                            <x-button>
                                {{ __('+Create Contact/Driver') }}
                            </x-button>
                        </div>
                    </form>
                </div>
            </details>
        </div>


    </div>
</x-app-layout>
