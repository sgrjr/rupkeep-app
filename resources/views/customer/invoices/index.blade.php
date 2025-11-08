@extends('layouts.guest')

@section('content')
    <div class="max-w-5xl mx-auto py-8 px-4">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">
            {{ __('Your Invoices') }}
        </h1>

        @if($invoices->isEmpty())
            <div class="bg-white border border-gray-200 rounded-lg p-6 text-center text-gray-600">
                {{ __('No invoices yet. When new invoices are ready they will appear here.') }}
            </div>
        @else
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                {{ __('Invoice #') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                {{ __('Created') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                {{ __('Total Due') }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                {{ __('Status') }}
                            </th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($invoices as $invoice)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ $invoice->invoice_number }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ $invoice->created_at->format('M d, Y') }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ \Illuminate\Support\Number::currency($invoice->values['total'] ?? 0, 'USD') }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if($invoice->paid_in_full)
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700">
                                            {{ __('Paid') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-700">
                                            {{ __('Open') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-right">
                                    <a href="{{ route('customer.invoices.show', $invoice) }}" class="text-primary underline">
                                        {{ __('View') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
@endsection

