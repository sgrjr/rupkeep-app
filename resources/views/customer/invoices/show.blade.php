@extends('layouts.guest')

@section('content')
    <div class="max-w-4xl mx-auto py-8 px-4 space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    {{ __('Invoice #:number', ['number' => $invoice->invoice_number]) }}
                </h1>
                <p class="text-sm text-gray-500">
                    {{ __('Generated on :date', ['date' => $invoice->created_at->format('M d, Y')]) }}
                </p>
            </div>

            <div>
                @if($invoice->paid_in_full)
                    <span class="inline-flex items-center px-4 py-2 text-sm font-semibold text-green-700 bg-green-100 rounded-full">
                        {{ __('Paid in full') }}
                    </span>
                @else
                    <span class="inline-flex items-center px-4 py-2 text-sm font-semibold text-yellow-700 bg-yellow-100 rounded-full">
                        {{ __('Payment due') }}
                    </span>
                @endif
            </div>
        </div>

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="grid md:grid-cols-2 gap-6 p-6 border-b border-gray-100">
                <div>
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                        {{ __('Bill From') }}
                    </h2>
                    <p class="mt-2 text-sm text-gray-700">
                        {{ $invoice->values['bill_from']['company'] ?? '' }}<br>
                        {{ $invoice->values['bill_from']['street'] ?? '' }}<br>
                        {{ $invoice->values['bill_from']['city'] ?? '' }},
                        {{ $invoice->values['bill_from']['state'] ?? '' }}
                        {{ $invoice->values['bill_from']['zip'] ?? '' }}
                    </p>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                        {{ __('Bill To') }}
                    </h2>
                    <p class="mt-2 text-sm text-gray-700">
                        {{ $invoice->values['bill_to']['company'] ?? '' }}<br>
                        {{ $invoice->values['bill_to']['street'] ?? '' }}<br>
                        {{ $invoice->values['bill_to']['city'] ?? '' }},
                        {{ $invoice->values['bill_to']['state'] ?? '' }}
                        {{ $invoice->values['bill_to']['zip'] ?? '' }}
                    </p>
                </div>
            </div>

            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">{{ __('Load #') }}</span>
                    <span class="text-sm font-semibold text-gray-800">{{ $invoice->values['load_no'] ?? '—' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">{{ __('Pickup Address') }}</span>
                    <span class="text-sm font-semibold text-gray-800 text-right">
                        {{ $invoice->values['pickup_address'] ?? '—' }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">{{ __('Delivery Address') }}</span>
                    <span class="text-sm font-semibold text-gray-800 text-right">
                        {{ $invoice->values['delivery_address'] ?? '—' }}
                    </span>
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 flex items-center justify-between">
                <span class="text-lg font-semibold text-gray-900">
                    {{ __('Total Due') }}
                </span>
                <span class="text-2xl font-bold text-gray-900">
                    {{ \Illuminate\Support\Number::currency($invoice->values['total'] ?? 0, 'USD') }}
                </span>
            </div>
        </div>

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ __('Notes & Additional Details') }}
                </h2>
            </div>
            <div class="p-6 text-sm text-gray-700 space-y-3">
                <p><strong>{{ __('Truck / Trailer') }}:</strong> {{ $invoice->values['truck_number'] ?? '—' }} / {{ $invoice->values['trailer_number'] ?? '—' }}</p>
                <p><strong>{{ __('Driver(s)') }}:</strong> {{ $invoice->values['truck_driver_name'] ?? '—' }}</p>
                <p><strong>{{ __('Wait Time Hours') }}:</strong> {{ $invoice->values['wait_time_hours'] ?? 0 }}</p>
                <p><strong>{{ __('Extra Stops') }}:</strong> {{ $invoice->values['extra_load_stops_count'] ?? 0 }}</p>
                <p><strong>{{ __('Deadhead Trips') }}:</strong> {{ $invoice->values['dead_head'] ?? 0 }}</p>
                <p><strong>{{ __('Notes') }}:</strong> {{ $invoice->values['notes'] ?? __('No additional notes provided.') }}</p>
            </div>
        </div>

        @auth
            <livewire:invoice-comments :invoice="$invoice" />
        @endauth

        @if($attachments->isNotEmpty())
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-900">
                        {{ __('Proof Materials') }}
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ __('Files shared by staff for your review.') }}
                    </p>
                </div>
                <div class="p-6 space-y-3">
                    @foreach($attachments as $attachment)
                        <div class="flex items-center justify-between gap-3 border border-gray-200 rounded-md px-4 py-3">
                            <span class="text-sm text-gray-700">
                                {{ $attachment->file_name }}
                            </span>
                            <a class="text-sm font-semibold text-primary underline" download href="{{ route('attachments.download', ['attachment' => $attachment->id]) }}">
                                {{ __('Download') }}
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex items-center justify-between">
            <a href="{{ route('customer.invoices.index') }}" class="text-sm underline text-gray-600 hover:text-gray-900">
                {{ __('Back to invoices') }}
            </a>
        </div>
    </div>
@endsection

