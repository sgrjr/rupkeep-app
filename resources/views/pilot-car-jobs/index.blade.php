@props(['redirect_to_root'=>false])
<x-app-layout>
<div class="min-h-screen bg-gray-100 font-sans antialiased">
    <div class="max-w-5xl mx-auto p-4 sm:p-6 lg:p-8">

        @can('viewAny', \App\Models\Invoice::class)
        <form
            method="GET"
            action="{{ route('my.invoices.export.quickbooks') }}"
            class="w-full flex flex-col sm:flex-row items-center justify-center gap-3 mb-6 p-4 bg-white rounded-lg shadow-md"
        >
            <div class="flex flex-col sm:flex-row gap-3 w-full">
                <input
                    type="date"
                    name="from"
                    value="{{ request('from') }}"
                    class="w-full sm:w-auto flex-grow px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="From date"
                />
                <input
                    type="date"
                    name="to"
                    value="{{ request('to') }}"
                    class="w-full sm:w-auto flex-grow px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="To date"
                />
                <select
                    name="paid"
                    class="w-full sm:w-auto px-4 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Any Status</option>
                    <option value="yes" @selected(request('paid') === 'yes')>Paid</option>
                    <option value="no" @selected(request('paid') === 'no')>Unpaid</option>
                </select>
            </div>
            <button
                type="submit"
                class="btn-base btn-secondary w-full sm:w-auto"
            >
                Export QuickBooks CSV
            </button>
        </form>
        @endcan

        <!-- Search Form -->
        <form class="w-full flex flex-col sm:flex-row items-center justify-center gap-3 mb-6 p-4 bg-white rounded-lg shadow-md">
            <input
                type="text"
                name="search_value"
                placeholder="Search..."
                class="w-full sm:w-auto flex-grow px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <div class="custom-select-wrapper w-full sm:w-auto">
                <select
                    name="search_field"
                    class="w-full px-4 py-2 border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
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
            </div>
            <button
                type="submit"
                class="btn-base btn-primary"
            >
                Search
            </button>
        </form>

        <!-- Customer Name and Job Count -->
        <h1 class="text-3xl font-extrabold text-center text-gray-800 mb-6">
            @if($customer)
                {{$customer->name}}
            @endif
            Jobs: ({{count($jobs)}})
        </h1>

        <!-- Jobs Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 space-2">

            @foreach($jobs AS $j)
                <div class="
                    bg-white rounded-lg shadow-lg overflow-hidden
                    border-l-4
                    {{$j->invoice_paid < 1 ? 'border-red-500' : 'border-green-500'}}
                    hover:shadow-xl transition-shadow duration-300 ease-in-out
                    flex flex-col
                ">
                    <div class="p-5 flex-grow">
                        <!-- Job Header/Identifier -->
                        <div class="mb-4 pb-3 border-b border-gray-200">
                            <p class="text-lg font-bold text-gray-900 flex items-center justify-between">
                                <span class="text-blue-700">Job #{{$j->job_no}}</span>
                                @if($j->invoice_paid < 1)
                                    <span class="text-sm font-semibold text-red-600 bg-red-100 px-2 py-1 rounded-full">UNPAID</span>
                                @else
                                    <span class="text-sm font-semibold text-green-600 bg-green-100 px-2 py-1 rounded-full">PAID</span>
                                @endif
                            </p>
                            @if($j->customer)
                                <p class="text-sm text-gray-600 mt-1">Customer: <span class="font-medium text-gray-800">{{$j->customer->name}}</span></p>
                            @endif
                        </div>

                        <!-- Job Details -->
                        <div class="space-y-2 text-sm text-gray-700">
                            @can('viewAny', new \App\Models\Organization)
                                <p><span class="font-semibold">Organization:</span> <span class="text-gray-800">{{$j->organization->name}}</span></p>
                            @endcan
                            <p><span class="font-semibold">Load #:</span> <span class="text-gray-800">{{$j->load_no}}</span></p>
                            <p><span class="font-semibold">Scheduled Pickup:</span> <span class="text-gray-800">{{$j->pickup_address}} @ {{$j->scheduled_pickup_at}}</span></p>
                            <p><span class="font-semibold">Scheduled Delivery:</span> <span class="text-gray-800">{{$j->delivery_address}} @ {{$j->scheduled_delivery_at}}</span></p>
                            <p><span class="font-semibold">Invoice #:</span> <span class="text-gray-800">{{$j->invoice_no}}</span></p>
                            <p><span class="font-semibold">Check #:</span> <span class="text-gray-800">{{$j->check_no}}</span></p>
                            <p><span class="font-semibold">Rate Code:</span> <span class="text-gray-800">{{$j->rate_code}}</span></p>
                            @php
                                $rateDisplay = $j->rate_value !== null
                                    ? '$'.number_format((float) $j->rate_value, 2)
                                    : '—';
                            @endphp
                            <p><span class="font-semibold">Rate Value:</span> <span class="text-gray-800">{{$rateDisplay}}</span></p>

                            @if($j->canceled_at)
                                <p class="text-red-600"><span class="font-semibold">Canceled At:</span> <span class="text-gray-800">{{$j->canceled_at}}</span></p>
                            @endif
                            @if($j->canceled_reason)
                                <p class="text-red-600"><span class="font-semibold">Canceled Reason:</span> <span class="text-gray-800">{{$j->canceled_reason}}</span></p>
                            @endif
                            <p>
                                <span class="font-semibold">Memo:</span>
                                @if(str_starts_with($j->memo, 'http'))
                                    <a target="_blank" href="{!!$j->memo!!}" class="text-blue-600 hover:text-blue-800 underline">view invoice</a>
                                @else
                                    <span class="text-gray-800">{{$j->memo}}</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <!-- Card Actions -->
                    @php
                        $showRoute = $redirect_to_root
                            ? route('jobs.show', ['job' => $j->id])
                            : route('my.jobs.show', ['job' => $j->id]);
                        $editRoute = route('my.jobs.edit', ['job' => $j->id]);
                        $destroyRoute = route('my.jobs.destroy', ['job' => $j->id]);
                    @endphp
                    <div class="flex flex-wrap justify-end flex-end gap-2 bg-gray-50 border-t border-gray-200">
                        <a href="{{$showRoute}}" class="mr-4">
                            Show
                        </a>
                        @if(auth()->user()->can('update', $j))
                            <a href="{{$editRoute}}" class="mr-4">
                                Edit
                            </a>
                        @endif
                        @if(auth()->user()->can('delete', $j))
                            <livewire:delete-confirmation-button
                                :action-url="$destroyRoute"
                                button-text=""
                                :model-class="\App\Models\PilotCarJob::class"
                                :record-id="$j->id"
                                resource="jobs"
                                :redirect-route="$redirect_to_root ? 'jobs.index' : 'my.jobs.index'"
                            />
                        @endif
                    </div>
                </div>

            @endforeach
        </div>
    </div>
</div>
</x-app-layout>