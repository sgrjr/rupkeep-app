@extends('layouts.guest')

@php
    use Illuminate\Support\Number;

    $invoiceCount = $invoices->total();
    $totalDue = $invoices->sum(fn ($invoice) => (float) ($invoice->values['total'] ?? 0));
    $openInvoices = $invoices->where('paid_in_full', false)->count();
    $paidInvoices = $invoiceCount - $openInvoices;
@endphp

@section('content')
    <div class="min-h-screen bg-slate-100/80 py-10">
        <div class="mx-auto max-w-5xl space-y-8 px-4">
            <section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-orange-500 via-orange-400 to-orange-300 p-6 text-white shadow-xl">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.25),_transparent_60%)] opacity-70"></div>
                <div class="relative flex flex-wrap items-center justify-between gap-4">
                    <div class="space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wider text-white/75">{{ __('Customer Portal') }}</p>
                        <h1 class="text-3xl font-bold tracking-tight">{{ __('Your Invoices') }}</h1>
                        <p class="text-sm text-white/80">{{ __('Download proofs, review comments, and track payment status for every invoice we have issued to you.') }}</p>
                    </div>
                    <span class="rounded-full border border-white/25 bg-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white/90 shadow-sm backdrop-blur">
                        {{ trans_choice('{0} No invoices yet|{1} :count invoice|[2,*] :count invoices', $invoiceCount, ['count' => $invoiceCount]) }}
                    </span>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-3xl border border-orange-100 bg-white/90 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Total invoices') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $invoiceCount }}</p>
                </div>
                <div class="rounded-3xl border border-emerald-100 bg-white/90 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Paid') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $paidInvoices }}</p>
                </div>
                <div class="rounded-3xl border border-amber-100 bg-white/90 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Open') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $openInvoices }}</p>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-white/90 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Total due') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ Number::currency($totalDue, 'USD') }}</p>
                </div>
            </section>

            @if($invoices->isEmpty())
                <div class="rounded-3xl border border-dashed border-slate-200 bg-white/90 py-12 text-center text-sm text-slate-500 shadow-sm">
                    {{ __('No invoices yet. When new invoices are ready they will appear here.') }}
                </div>
            @else
                <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
                    <!-- Filters -->
                    <form method="GET" action="{{ route('customer.invoices.index') }}" class="mb-6 space-y-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-slate-600 mb-2">{{ __('Payment Status') }}</label>
                                <select id="status" name="status" class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                    <option value="">{{ __('All invoices') }}</option>
                                    <option value="paid" @selected(request('status') === 'paid')>{{ __('Paid') }}</option>
                                    <option value="unpaid" @selected(request('status') === 'unpaid')>{{ __('Unpaid') }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="date_from" class="block text-xs font-semibold uppercase tracking-wide text-slate-600 mb-2">{{ __('From Date') }}</label>
                                <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}" class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            </div>
                            <div>
                                <label for="date_to" class="block text-xs font-semibold uppercase tracking-wide text-slate-600 mb-2">{{ __('To Date') }}</label>
                                <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}" class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-orange-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z"/></svg>
                                {{ __('Apply Filters') }}
                            </button>
                            @if(request()->hasAny(['status', 'date_from', 'date_to']))
                                <a href="{{ route('customer.invoices.index') }}" class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                                    {{ __('Clear') }}
                                </a>
                            @endif
                        </div>
                    </form>

                    <div class="rounded-2xl border border-slate-200">
                        <div class="overflow-x-auto rounded-2xl">
                            <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-600">
                                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3 text-left">{{ __('Invoice #') }}</th>
                                        <th class="px-4 py-3 text-left">{{ __('Created') }}</th>
                                        <th class="px-4 py-3 text-left">{{ __('Total') }}</th>
                                        <th class="px-4 py-3 text-left">{{ __('Status') }}</th>
                                        <th class="px-4 py-3 text-left">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @foreach($invoices as $invoice)
                                        <tr>
                                            <td class="px-4 py-3 font-semibold text-slate-800">{{ $invoice->invoice_number }}</td>
                                            <td class="px-4 py-3 text-xs text-slate-500">{{ $invoice->created_at->format('M d, Y g:ia') }}</td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-semibold text-slate-600">
                                                    {{ Number::currency($invoice->values['total'] ?? 0, 'USD') }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                @if($invoice->paid_in_full)
                                                    <span class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-600">
                                                        {{ __('Paid in full') }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] font-semibold text-amber-600">
                                                        {{ __('Awaiting payment') }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-2">
                                                    <a href="{{ route('customer.invoices.show', $invoice) }}"
                                                       class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-white px-3 py-1 text-[11px] font-semibold text-orange-600 transition hover:border-orange-300 hover:text-orange-700">
                                                        {{ __('View') }}
                                                    </a>
                                                    @if(config('features.invoice_pdf_downloads', false))
                                                        <a href="{{ route('my.invoices.pdf', ['invoice' => $invoice->id]) }}"
                                                           class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-600 transition hover:border-emerald-300 hover:text-emerald-700"
                                                           title="{{ __('Download PDF') }}">
                                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                                                            </svg>
                                                            {{ __('PDF') }}
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-6">
                        {{ $invoices->links() }}
                    </div>
                </section>
            @endif
        </div>
    </div>
@endsection

