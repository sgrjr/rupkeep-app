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
                                                <a href="{{ route('customer.invoices.show', $invoice) }}"
                                                   class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-white px-3 py-1 text-[11px] font-semibold text-orange-600 transition hover:border-orange-300 hover:text-orange-700">
                                                    {{ __('View invoice') }}
                                                </a>
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

