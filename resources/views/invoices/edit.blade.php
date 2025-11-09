@php
    use Illuminate\Support\Number;

    $values = $invoice->values ?? [];
    $job = $invoice->job;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Invoice Management') }}</p>
                <h1 class="text-xl font-semibold text-slate-900">
                    {{ __('Invoice #:number', ['number' => $invoice->invoice_number]) }}
                </h1>
                <p class="text-xs text-slate-500">
                    {{ __('Created :date • Last updated :updated', [
                        'date' => optional($invoice->created_at)->format('M j, Y g:ia'),
                        'updated' => optional($invoice->updated_at)->diffForHumans(),
                    ]) }}
                </p>
                </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('my.invoices.print', ['invoice' => $invoice->id]) }}" target="_blank"
                   class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-white px-3 py-1 text-xs font-semibold text-orange-600 shadow-sm transition hover:bg-orange-500 hover:text-white">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M7 7h10M7 11h10M7 15h5M17 15h.01M6 19h12a2 2 0 002-2v-8a2 2 0 00-2-2h-1V5a2 2 0 00-2-2H9a2 2 0 00-2 2v2H6a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    {{ __('Print / Download') }}
                </a>
                @if($invoice->customer)
                    <a href="{{ route('my.customers.show', ['customer' => $invoice->customer_id]) }}"
                       class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-orange-300 hover:text-orange-600">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                        {{ __('View Customer') }}
                    </a>
                @endif
                @if($job)
                    <a href="{{ route('my.jobs.show', ['job' => $job->id]) }}"
                       class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-orange-300 hover:text-white">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                        {{ __('View Job') }}
                    </a>
                @endif
            </div>
                    </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-3xl border border-orange-100 bg-white/80 p-6 shadow-sm">
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Billing Summary') }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-900">
                            {{ data_get($values, 'bill_to.company') ?? $invoice->customer?->name }}
                        </h2>
                        <p class="text-xs text-slate-500">
                            {{ data_get($values, 'bill_to.street') }}, {{ data_get($values, 'bill_to.city') }},
                            {{ data_get($values, 'bill_to.state') }} {{ data_get($values, 'bill_to.zip') }}
                        </p>
                    </div>
                    <dl class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 text-xs text-slate-600">
                        <div>
                            <dt class="font-semibold uppercase tracking-wide text-slate-500">{{ __('Total Due') }}</dt>
                            <dd class="mt-1 text-base font-semibold text-slate-900">
                                {{ Number::currency(data_get($values, 'total', 0), 'USD') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="font-semibold uppercase tracking-wide text-slate-500">{{ __('Rate Code') }}</dt>
                            <dd class="mt-1 text-sm">{{ data_get($values, 'effective_rate_code') ?? data_get($values, 'rate_code') }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold uppercase tracking-wide text-slate-500">{{ __('Billable Miles') }}</dt>
                            <dd class="mt-1 text-sm">{{ data_get($values, 'billable_miles') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold uppercase tracking-wide text-slate-500">{{ __('Customer') }}</dt>
                            <dd class="mt-1 text-sm">{{ $invoice->customer?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold uppercase tracking-wide text-slate-500">{{ __('Job No.') }}</dt>
                            <dd class="mt-1 text-sm">{{ data_get($values, 'load_no') ?? $job?->job_no ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold uppercase tracking-wide text-slate-500">{{ __('Status') }}</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center gap-2 rounded-full px-2 py-0.5 text-[11px] font-semibold
                                    {{ $invoice->paid_in_full ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $invoice->paid_in_full ? __('Paid in full') : __('Awaiting payment') }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 text-xs text-slate-600 space-y-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Quick Links') }}</p>
                    <div class="space-y-2">
                        @if($job)
                            <a href="{{ route('my.jobs.show', ['job' => $job->id]) }}"
                               class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-700 transition hover:border-orange-300 hover:text-orange-600">
                                <span>{{ __('Job overview') }}</span>
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                            <a href="{{ route('my.jobs.edit', ['job' => $job->id]) }}"
                               class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-700 transition hover:border-orange-300 hover:text-orange-600">
                                <span>{{ __('Edit job basics & rate') }}</span>
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-4.536a2.5 2.5 0 11-3.536 3.536L4.5 16.5V19.5H7.5l8.5-8.5"/>
                                </svg>
                            </a>
                            <a href="{{ route('my.jobs.show', ['job' => $job->id]) }}#job-logs"
                               class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-700 transition hover:border-orange-300 hover:text-orange-600">
                                <span>{{ __('Manage job logs & expenses') }}</span>
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10"/>
                                </svg>
                            </a>
                        @endif
                        @if($invoice->customer)
                            <a href="{{ route('my.customers.show', ['customer' => $invoice->customer_id]) }}"
                               class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-700 transition hover:border-orange-300 hover:text-orange-600">
                                <span>{{ __('Customer profile & contacts') }}</span>
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        @endif
                        <p class="rounded-xl border border-orange-100 bg-orange-50 px-3 py-2 text-[11px] text-orange-700">
                            {{ __('Need the invoice to re-sync with log data? Update the logs from the job view, then regenerate a fresh invoice from the job page.') }}
                            <a href="#invoice-snapshot-info" class="ml-1 inline-flex items-center gap-1 text-orange-700 font-semibold underline decoration-dotted hover:text-orange-800">
                                {{ __('See more') }}
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0-6 6m6-6-6-6" />
                                </svg>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <form action="{{ route('my.invoices.update', ['invoice' => $invoice->id]) }}" method="post" class="space-y-8">
            @csrf
            @method('PUT')

            <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ __('Status & Actions') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Update payment status or remove the invoice entirely.') }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="paid_in_full">
                            {{ __('Paid in full?') }}
                        </label>
                        <select id="paid_in_full" name="paid_in_full"
                                class="rounded-full border border-slate-200 bg-white px-3 py-1 text-sm font-semibold text-slate-700 shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            <option value="yes" {{ $invoice->paid_in_full ? 'selected' : '' }}>{{ __('Yes') }}</option>
                            <option value="no" {{ $invoice->paid_in_full ? '' : 'selected' }}>{{ __('No') }}</option>
                        </select>

                        <label class="inline-flex items-center gap-2 rounded-full border border-red-100 bg-red-50 px-3 py-1 text-xs font-semibold text-red-600">
                            <input type="checkbox" name="delete" class="rounded border-red-200 text-red-500 focus:ring-red-400">
                            {{ __('Delete invoice') }}
                        </label>
                    </div>
                </div>

                @if($invoice->isSummary())
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                        <p class="font-semibold text-slate-700">{{ __('When deleting this summary invoice') }}</p>
                        <div class="mt-2 space-y-2">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="delete_mode" value="release_children" class="text-orange-500 focus:ring-orange-400" checked>
                                <span>{{ __('Release child invoices (keep them editable individually)') }}</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="delete_mode" value="delete_children" class="text-red-500 focus:ring-red-400">
                                <span>{{ __('Delete child invoices as well (irreversible)') }}</span>
                            </label>
                        </div>
                    </div>
                @endif
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm space-y-6">
                <header>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Invoice Appearance') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Override branding and footer content shown on the printable view.') }}</p>
                </header>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="values_logo" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Logo URL') }}</label>
                        <input id="values_logo" name="values[logo]" type="text"
                               value="{{ old('values.logo', data_get($values, 'logo')) }}"
                               placeholder="https://example.com/logo.png"
                               class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                        </div>
                    <div>
                        <label for="values_title" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Document Title') }}</label>
                        <input id="values_title" name="values[title]" type="text"
                               value="{{ old('values.title', data_get($values, 'title')) }}"
                               placeholder="INVOICE"
                               class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                        </div>
                        </div>
                <div>
                    <label for="values_footer" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Invoice Footer Message') }}</label>
                    <textarea id="values_footer" name="values[footer]" rows="3"
                              class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"
                              placeholder="{{ __('Thank your customers or provide payment instructions.') }}">{{ old('values.footer', data_get($values, 'footer')) }}</textarea>
                        </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm space-y-6">
                <header>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Billing Addresses') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Update the “Bill From” and “Bill To” blocks shown on the invoice header.') }}</p>
                </header>
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="space-y-3 rounded-2xl border border-slate-100 bg-slate-50/60 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Bill From') }}</p>
                        <div class="space-y-3">
                            <div>
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Company') }}</label>
                                <input type="text" name="values[bill_from][company]"
                                       value="{{ old('values.bill_from.company', data_get($values, 'bill_from.company')) }}"
                                       class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Attention') }}</label>
                                <input type="text" name="values[bill_from][attention]"
                                       value="{{ old('values.bill_from.attention', data_get($values, 'bill_from.attention')) }}"
                                       class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Street') }}</label>
                                <input type="text" name="values[bill_from][street]"
                                       value="{{ old('values.bill_from.street', data_get($values, 'bill_from.street')) }}"
                                       class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                            </div>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('City') }}</label>
                                    <input type="text" name="values[bill_from][city]"
                                           value="{{ old('values.bill_from.city', data_get($values, 'bill_from.city')) }}"
                                           class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('State') }}</label>
                                    <input type="text" name="values[bill_from][state]"
                                           value="{{ old('values.bill_from.state', data_get($values, 'bill_from.state')) }}"
                                           class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Zip') }}</label>
                                    <input type="text" name="values[bill_from][zip]"
                                           value="{{ old('values.bill_from.zip', data_get($values, 'bill_from.zip')) }}"
                                           class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-3 rounded-2xl border border-slate-100 bg-slate-50/60 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Bill To') }}</p>
                        <div class="space-y-3">
                            <div>
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Company') }}</label>
                                <input type="text" name="values[bill_to][company]"
                                       value="{{ old('values.bill_to.company', data_get($values, 'bill_to.company')) }}"
                                       class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Attention') }}</label>
                                <input type="text" name="values[bill_to][attention]"
                                       value="{{ old('values.bill_to.attention', data_get($values, 'bill_to.attention')) }}"
                                       class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                            </div>
                            <div>
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Street') }}</label>
                                <input type="text" name="values[bill_to][street]"
                                       value="{{ old('values.bill_to.street', data_get($values, 'bill_to.street')) }}"
                                       class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                            </div>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('City') }}</label>
                                    <input type="text" name="values[bill_to][city]"
                                           value="{{ old('values.bill_to.city', data_get($values, 'bill_to.city')) }}"
                                           class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('State') }}</label>
                                    <input type="text" name="values[bill_to][state]"
                                           value="{{ old('values.bill_to.state', data_get($values, 'bill_to.state')) }}"
                                           class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                                </div>
                                <div>
                                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Zip') }}</label>
                                    <input type="text" name="values[bill_to][zip]"
                                           value="{{ old('values.bill_to.zip', data_get($values, 'bill_to.zip')) }}"
                                           class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm space-y-6">
                <header>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Job & Load Details') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Override pickup, delivery, driver, and reference information that appears on the invoice.') }}</p>
                </header>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Pickup Address') }}</label>
                        <textarea name="values[pickup_address]" rows="3"
                                  class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200">{{ old('values.pickup_address', data_get($values, 'pickup_address')) }}</textarea>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Delivery Address') }}</label>
                        <textarea name="values[delivery_address]" rows="3"
                                  class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200">{{ old('values.delivery_address', data_get($values, 'delivery_address')) }}</textarea>
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Load Number') }}</label>
                        <input type="text" name="values[load_no]" value="{{ old('values.load_no', data_get($values, 'load_no')) }}"
                               class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Check Number') }}</label>
                        <input type="text" name="values[check_no]" value="{{ old('values.check_no', data_get($values, 'check_no')) }}"
                               class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Truck Driver') }}</label>
                        <input type="text" name="values[truck_driver_name]" value="{{ old('values.truck_driver_name', data_get($values, 'truck_driver_name')) }}"
                               class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Truck Number') }}</label>
                        <input type="text" name="values[truck_number]" value="{{ old('values.truck_number', data_get($values, 'truck_number')) }}"
                               class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Trailer Number') }}</label>
                        <input type="text" name="values[trailer_number]" value="{{ old('values.trailer_number', data_get($values, 'trailer_number')) }}"
                               class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Truck Notes') }}</label>
                        <input type="text" name="values[notes]" value="{{ old('values.notes', data_get($values, 'notes')) }}"
                               class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Wait Time (hours)') }}</label>
                        <input type="number" step="0.25" name="values[wait_time_hours]" value="{{ old('values.wait_time_hours', data_get($values, 'wait_time_hours')) }}"
                               class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Extra Load Stops') }}</label>
                        <input type="number" step="1" min="0" name="values[extra_load_stops_count]" value="{{ old('values.extra_load_stops_count', data_get($values, 'extra_load_stops_count')) }}"
                               class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm space-y-6">
                <header>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Charges & Overrides') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Adjust how totals are calculated. These values override the live data pulled from job logs.') }}</p>
                </header>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Rate Code') }}</label>
                        <input type="text" name="values[rate_code]" value="{{ old('values.rate_code', data_get($values, 'rate_code')) }}"
                               class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Rate Value') }}</label>
                        <input type="number" step="0.01" name="values[rate_value]" value="{{ old('values.rate_value', data_get($values, 'rate_value')) }}"
                               class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Effective Rate Code') }}</label>
                        <input type="text" name="values[effective_rate_code]" value="{{ old('values.effective_rate_code', data_get($values, 'effective_rate_code')) }}"
                               class="mt-2 block w-full rounded-xl border border-slate-200	bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Effective Rate Value') }}</label>
                        <input type="number" step="0.01" name="values[effective_rate_value]"
                               value="{{ old('values.effective_rate_value', data_get($values, 'effective_rate_value')) }}"
                               class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Billable Miles') }}</label>
                        <input type="number" step="0.1" name="values[billable_miles]" value="{{ old('values.billable_miles', data_get($values, 'billable_miles')) }}"
                               class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Non-billable Miles') }}</label>
                        <input type="number" step="0.1" name="values[nonbillable_miles]" value="{{ old('values.nonbillable_miles', data_get($values, 'nonbillable_miles')) }}"
                               class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Deadhead Legs') }}</label>
                        <input type="number" min="0" step="1" name="values[dead_head]" value="{{ old('values.dead_head', data_get($values, 'dead_head')) }}"
                               class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Escort Vehicles') }}</label>
                        <input type="number" min="0" step="1" name="values[cars_count]" value="{{ old('values.cars_count', data_get($values, 'cars_count')) }}"
                               class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Tolls') }}</label>
                        <input type="number" step="0.01" name="values[tolls]" value="{{ old('values.tolls', data_get($values, 'tolls')) }}"
                               class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Hotel') }}</label>
                        <input type="number" step="0.01" name="values[hotel]" value="{{ old('values.hotel', data_get($values, 'hotel')) }}"
                               class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Extra Charge') }}</label>
                        <input type="number" step="0.01" name="values[extra_charge]" value="{{ old('values.extra_charge', data_get($values, 'extra_charge')) }}"
                               class="mt-2 block w-full rounded-xl border border-slate-200	bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Total Due Override') }}</label>
                        <input type="number" step="0.01" name="values[total]" value="{{ old('values.total', data_get($values, 'total')) }}"
                               class="mt-2 block w-full rounded-xl border border-slate-200	bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                    </div>
                </div>
            </section>

            @if($invoice->isSummary())
                <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm space-y-4">
                    <header>
                        <h2 class="text-lg font-semibold text-slate-900">{{ __('Child Invoices') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('This summary groups multiple job invoices. Edit individual job invoices through the links below.') }}</p>
                    </header>

                    <div class="overflow-hidden rounded-2xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-xs text-slate-600">
                            <thead class="bg-slate-50 text-slate-500">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold uppercase tracking-wide">{{ __('Invoice #') }}</th>
                                    <th class="px-4 py-2 text-left font-semibold uppercase tracking-wide">{{ __('Job') }}</th>
                                    <th class="px-4 py-2 text-left font-semibold uppercase tracking-wide">{{ __('Total') }}</th>
                                    <th class="px-4 py-2 text-left font-semibold uppercase tracking-wide">{{ __('Miles') }}</th>
                                    <th class="px-4 py-2 text-left font-semibold uppercase tracking-wide"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach(data_get($invoice->values, 'summary_items', []) as $item)
                                    <tr>
                                        <td class="px-4 py-2 font-semibold text-slate-800">{{ $item['invoice_number'] ?? '—' }}</td>
                                        <td class="px-4 py-2">{{ $item['job_no'] ?? __('Job') }}</td>
                                        <td class="px-4 py-2">{{ isset($item['total']) ? number_format($item['total'], 2) : '—' }}</td>
                                        <td class="px-4 py-2">{{ $item['billable_miles'] ?? '—' }}</td>
                                        <td class="px-4 py-2 text-right">
                                            @isset($item['invoice_id'])
                                                <a href="{{ route('my.invoices.edit', ['invoice' => $item['invoice_id']]) }}"
                                                   class="inline-flex items-center gap-1 rounded-full border border-orange-200 bg-white px-3 py-1 text-[11px] font-semibold text-orange-600 transition hover:bg-orange-500 hover:text-white">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-4.536a2.5 2.5 0 11-3.536 3.536L4.5 16.5V19.5H7.5l8.5-8.5"/></svg>
                                                    {{ __('Edit') }}
                                                </a>
                                            @endisset
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <div class="flex flex-wrap items-center justify-end gap-3">
                @if($job)
                    <a href="{{ route('my.jobs.show', ['job' => $job->id]) }}"
                       class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-orange-300 hover:text-orange-600">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                        {{ __('Back to job') }}
                    </a>
                @endif
                    <x-button>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ __('Save invoice changes') }}
                    </x-button>
                </div>
            </form>

        <section id="invoice-snapshot-info" class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('Internal Notes & Flags') }}</h2>
            <p class="text-xs text-slate-500">{{ __('Team-only comment thread. Customers see only what is marked public.') }}</p>
            <div class="mt-4">
                <livewire:invoice-comments :invoice="$invoice" />
        </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('How invoice snapshots work') }}</h2>
            <div class="mt-3 space-y-3 text-sm text-slate-600">
                <p>{{ __('Each invoice is a snapshot of the related job logs at the moment it was generated. Editing logs later will not update this invoice automatically.') }}</p>
                <p>{{ __('Need the invoice to reflect new mileage, expenses, or rate changes? First delete this invoice (or mark it for deletion), adjust the job logs, and then return to the job page to generate a fresh invoice. This ensures the totals stay in sync with your source data.') }}</p>
                <p>{{ __('If the numbers on this invoice already match what you want to bill, it is safe to edit them directly here—those overrides remain until you choose to regenerate.') }}</p>
            </div>
        </section>
    </div>
</x-app-layout>
