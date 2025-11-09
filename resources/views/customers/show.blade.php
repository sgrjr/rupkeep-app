@php
    $jobs = $customer->jobs->sortByDesc('scheduled_pickup_at');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Customer Profile') }}</p>
                <h1 class="text-xl font-semibold text-slate-900">{{ $customer->name }}</h1>
                <p class="text-xs text-slate-500">{{ $customer->city }}, {{ $customer->state }} {{ $customer->zip }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('customers.edit', ['customer' => $customer->id]) }}"
                   class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-orange-300 hover:text-orange-600">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-4.536a2.5 2.5 0 11-3.536 3.536L4.5 16.5V19.5H7.5l8.5-8.5"/></svg>
                    {{ __('Edit customer') }}
                </a>
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
                <div class="space-y-3">
                    @forelse($customer->contacts as $contact)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                            <p class="font-semibold text-slate-900">{{ $contact->name }}</p>
                            <p class="text-xs text-slate-500">{{ $contact->phone ?: __('No phone on file') }}</p>
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
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Jobs & Invoicing') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Select one or more jobs to generate a new invoice. Multiple jobs will create a summary invoice automatically.') }}</p>
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
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse($jobs as $job)
                                @php
                                    $primaryInvoice = $job->invoices->whereNull('parent_invoice_id')->sortByDesc('created_at')->first();
                                @endphp
                                <tr>
                                    <td class="px-4 py-3">
                                        @if(!$primaryInvoice && !$job->invoice_paid)
                                            <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
                                                <input name="invoice_this[]" value="{{ $job->id }}" type="checkbox" class="rounded border-slate-300 text-orange-500 focus:ring-orange-400"/>
                                                {{ __('Select') }}
                                            </label>
                                        @else
                                            <span class="text-xs text-slate-400">—</span>
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
                                                <span class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-600">
                                                    {{ $primaryInvoice->isSummary() ? __('Summary Invoice #:number', ['number' => $primaryInvoice->invoice_number]) : __('Invoice #:number', ['number' => $primaryInvoice->invoice_number]) }}
                                                </span>
                                                <a href="{{ route('my.invoices.edit', ['invoice' => $primaryInvoice->id]) }}" class="inline-flex items-center text-xs font-semibold text-orange-600 underline hover:text-orange-700">
                                                    {{ __('View invoice') }}
                                                </a>
                                            </div>
                                        @else
                                            <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-500">{{ __('Ready for invoicing') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">{{ __('No jobs found for this customer.') }}</td>
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
