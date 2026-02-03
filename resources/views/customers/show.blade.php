@php
    $jobs = $customer->jobs->sortByDesc('scheduled_pickup_at');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide">{{ __('Customer Profile') }}</p>
                <h1 class="text-xl font-semibold">{{ $customer->name }}</h1>
                <p class="text-xs">{{ $customer->city }}, {{ $customer->state }} {{ $customer->zip }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('update', $customer)
                    <a href="{{ route('customers.edit', ['customer' => $customer->id]) }}"
                       class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-orange-300 hover:text-orange-600">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-4.536a2.5 2.5 0 11-3.536 3.536L4.5 16.5V19.5H7.5l8.5-8.5"/></svg>
                        {{ __('Edit customer') }}
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
        <section class="grid gap-6 md:grid-cols-2">
            <div class="space-y-4 rounded-3xl border border-orange-100 bg-white/90 p-6 shadow-sm">
                <header>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Company Details') }}</p>
                    <h2 class="mt-1 text-lg font-semibold text-slate-900">{{ $customer->name }}</h2>
                </header>
                <dl class="space-y-2 text-sm text-slate-600">
                    <div class="flex justify-between">
                        <dt class="font-semibold text-slate-500">{{ __('Street') }}</dt>
                        <dd>{{ $customer->street ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="font-semibold text-slate-500">{{ __('City / State') }}</dt>
                        <dd>{{ $customer->city ?? '—' }}, {{ $customer->state ?? '—' }} {{ $customer->zip ?? '' }}</dd>
                    </div>
                    @can('viewAny', new \App\Models\Organization)
                        <div class="flex justify-between">
                            <dt class="font-semibold text-slate-500">{{ __('Organization') }}</dt>
                            <dd>{{ $customer->organization?->name ?? '—' }}</dd>
                        </div>
                    @endcan
                    <div class="flex justify-between">
                        <dt class="font-semibold text-slate-500">{{ __('Open Jobs') }}</dt>
                        <dd>{{ $jobs->where('invoice_paid', '<', 1)->count() }}</dd>
                    </div>
                </dl>
            </div>

            <div class="space-y-4 rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
                <header>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Contacts') }}</p>
                    <h2 class="mt-1 text-lg font-semibold text-slate-900">{{ trans_choice(':count contact|:count contacts', $customer->contacts->count()) }}</h2>
                </header>
                <div class="space-y-3 max-h-[350px] overflow-y-auto">
                    @forelse($customer->contacts as $contact)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-slate-900">{{ $contact->name }}</p>
                                @if($contact->is_main_contact)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-blue-700">
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        {{ __('Main') }}
                                    </span>
                                @endif
                                @if($contact->is_billing_contact)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700">
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        {{ __('Billing') }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-slate-500">
                                @if($contact->phone)
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact->phone) }}" class="text-orange-600 hover:text-orange-700 hover:underline">
                                        {{ $contact->phone }}
                                        <svg class="ml-1 inline h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>
                                        </svg>
                                    </a>
                                @else
                                    {{ __('No phone on file') }}
                                @endif
                            </p>
                            @if($contact->memo)
                                <p class="text-xs text-slate-400">{{ $contact->memo }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-400">{{ __('No contacts recorded yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
            <header class="mb-4">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('Transaction Register') }}</h2>
                <p class="text-xs text-slate-500">{{ __('Complete transaction history showing invoices (credits) and payments (debits) with running balance.') }}</p>
            </header>
            <x-transaction-register 
                :transactions="$transactions" 
                :show-balance="true"
                :account-credit="$accountCredit" />
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Jobs & Invoicing') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Select jobs to create new invoices or group existing invoices into a summary. Multiple selections will create a summary automatically.') }}</p>
                </div>
                <div>
                    @livewire('create-invoice-summary', ['customerId' => $customer->id])
                </div>
            </header>

            <form action="{{ route('my.invoices.store') }}" method="post" class="mt-5 space-y-4">
                @csrf

                <div class="overflow-hidden rounded-2xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-600">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-left">{{ __('Select') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Job #') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Load #') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Pickup') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Delivery') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Invoice') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Attention') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse($jobs as $job)
                                @php
                                    // Get primary invoice from loaded collection (now includes both pivot and direct relationships)
                                    $primaryInvoice = $job->invoices->whereNull('parent_invoice_id')->sortByDesc('created_at')->first();
                                    
                                    // Allow selection if:
                                    // 1. No invoice exists (regardless of invoice_paid flag - allows fixing data inconsistencies)
                                    // 2. Has invoice that is not a summary and invoice is not paid
                                    // Block if: invoice exists AND is paid, OR invoice is a summary
                                    if (!$primaryInvoice) {
                                        // No invoice - allow selection (even if invoice_paid flag is set - data inconsistency)
                                        $canSelect = true;
                                    } else {
                                        // Has invoice - allow if not a summary and not paid
                                        $canSelect = !$primaryInvoice->isSummary() && !$primaryInvoice->paid_in_full;
                                    }
                                @endphp
                                <tr>
                                    <td class="px-4 py-3">
                                        @if($canSelect)
                                            <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
                                                <input name="invoice_this[]" value="{{ $job->id }}" type="checkbox" class="rounded border-slate-300 text-orange-500 focus:ring-orange-400"/>
                                                {{ $primaryInvoice ? __('Group') : __('Select') }}
                                            </label>
                                        @else
                                            @php
                                                $reason = '';
                                                if ($primaryInvoice && $primaryInvoice->isSummary()) {
                                                    $reason = __('Already in summary');
                                                } elseif ($primaryInvoice && $primaryInvoice->paid_in_full) {
                                                    $reason = __('Invoice paid');
                                                } else {
                                                    $reason = __('Cannot select');
                                                }
                                            @endphp
                                            <span class="text-xs text-slate-400" title="{{ $reason }}">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-slate-800">
                                        <a href="{{ route('my.jobs.show', ['job' => $job->id]) }}" class="text-orange-600 hover:text-orange-700">{{ $job->job_no ?? __('No #') }}</a>
                                    </td>
                                    <td class="px-4 py-3">{{ $job->load_no ?? '—' }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-500">{{ optional($job->scheduled_pickup_at)->format('M j, Y g:ia') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-500">{{ $job->delivery_address ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        @if($job->invoice_paid)
                                            <span class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">{{ __('Invoice Paid') }}</span>
                                        @elseif($primaryInvoice)
                                            <div class="flex flex-wrap items-center gap-2">
                                                @if($primaryInvoice->isSummary())
                                                    <span class="inline-flex items-center gap-2 rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-600">
                                                        {{ __('Summary Invoice #:number', ['number' => $primaryInvoice->invoice_number]) }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-600">
                                                        {{ __('Invoice #:number', ['number' => $primaryInvoice->invoice_number]) }}
                                                        @if($canSelect)
                                                            <span class="ml-1 rounded-full bg-blue-100 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700">{{ __('Can Group') }}</span>
                                                        @endif
                                                    </span>
                                                @endif
                                                <a href="{{ route('my.invoices.edit', ['invoice' => $primaryInvoice->id]) }}" class="inline-flex items-center text-xs font-semibold text-orange-600 underline hover:text-orange-700">
                                                    {{ __('View invoice') }}
                                                </a>
                                            </div>
                                        @else
                                            <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-500">{{ __('Ready for invoicing') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($primaryInvoice)
                                            <button type="button" onclick="(function() { const formData = new FormData(); formData.append('_token', document.querySelector('meta[name=csrf-token]').content); fetch('{{ route('my.invoices.toggle-marked-for-attention', ['invoice' => $primaryInvoice->id]) }}', { method: 'POST', headers: { 'Accept': 'application/json' }, body: formData }).then(r => r.json()).then(data => { if(data.success) { window.location.reload(); } }); })()" class="inline-flex items-center gap-1 rounded-full {{ $primaryInvoice->marked_for_attention ? 'bg-red-100 text-red-700 border border-red-200' : 'bg-slate-100 text-slate-500 border border-slate-200' }} px-3 py-1 text-xs font-semibold hover:opacity-80 transition">
                                                @if($primaryInvoice->marked_for_attention)
                                                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                    </svg>
                                                    {{ __('Marked') }}
                                                @else
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
                                                    </svg>
                                                    {{ __('Mark') }}
                                                @endif
                                            </button>
                                        @else
                                            <span class="text-xs text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-400">{{ __('No jobs found for this customer.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-500">{{ __('Summary invoices consolidate child invoices for grouped jobs. To refresh a summary, delete it and regenerate after updating job logs.') }}</p>
                    <x-button type="submit">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 11h16M10 15h10M6 19h8"/></svg>
                        {{ __('Generate invoice') }}
                    </x-button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
