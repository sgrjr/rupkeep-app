@php
    // /my/invoices does not pass these; only the cross-organization screen does.
    $crossOrganization = $crossOrganization ?? false;
    $organizations = $organizations ?? collect();
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $crossOrganization ? __('All Invoices') : __('Invoices') }}
            @if($crossOrganization)
                <span class="ml-2 rounded-full bg-purple-100 px-2 py-0.5 text-[11px] font-semibold uppercase text-purple-700">{{ __('Super User') }}</span>
            @endif
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">

            <section class="rounded-3xl border border-slate-200 bg-white/90 m-6 p-6 shadow-sm">
                <form method="GET" action="{{ $crossOrganization ? route('invoices.index') : route('my.invoices.index') }}" class="grid gap-4 md:grid-cols-6">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600 mb-1">{{ __('Search') }}</label>
                        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
                               placeholder="{{ __('Invoice #, customer, job #') }}"
                               class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200 text-slate-900">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600 mb-1">{{ __('Status') }}</label>
                        <select name="paid" class="w-full rounded-xl border border-slate-400 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200 text-slate-900">
                            <option value="">{{ __('Any') }}</option>
                            <option value="yes" @selected(($filters['paid'] ?? '') === 'yes')>{{ __('Paid') }}</option>
                            <option value="no" @selected(($filters['paid'] ?? '') === 'no')>{{ __('Unpaid') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600 mb-1">{{ __('Type') }}</label>
                        <select name="type" class="w-full rounded-xl border border-slate-400 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200 text-slate-900">
                            <option value="">{{ __('All') }}</option>
                            <option value="single" @selected(($filters['type'] ?? '') === 'single')>{{ __('Single') }}</option>
                            <option value="summary" @selected(($filters['type'] ?? '') === 'summary')>{{ __('Summary') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600 mb-1">{{ __('From') }}</label>
                        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="w-full rounded-xl border border-slate-400 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200 text-slate-900">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600 mb-1">{{ __('To') }}</label>
                        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="w-full rounded-xl border border-slate-400 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200 text-slate-900">
                    </div>

                    @if($crossOrganization)
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600 mb-1">{{ __('Organization') }}</label>
                            <select name="organization_id" class="w-full rounded-xl border border-slate-400 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200 text-slate-900">
                                <option value="">{{ __('All Organizations') }}</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}" @selected((string) ($filters['organization_id'] ?? '') === (string) $org->id)>{{ $org->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="md:col-span-6 flex flex-wrap items-center justify-between gap-3">
                        <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-600">
                            <input type="checkbox" name="orphaned" value="1" @checked(($filters['orphaned'] ?? '') === '1')
                                   class="rounded border-slate-300 text-orange-500 focus:ring-orange-200">
                            {{ __('Only invoices with no job attached') }}
                        </label>
                        <div class="flex gap-2">
                            <a href="{{ $crossOrganization ? route('invoices.index') : route('my.invoices.index') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">{{ __('Clear') }}</a>
                            <x-button type="submit">{{ __('Search') }}</x-button>
                        </div>
                    </div>
                </form>

                <p class="mt-4 border-t border-slate-100 pt-4 text-sm text-slate-600">
                    {{ __(':count invoice(s)', ['count' => number_format($listedCount)]) }}
                    <span class="mx-2 text-slate-300">|</span>
                    <span class="font-semibold text-slate-900">{{ \App\Support\Money::currency($listedTotal) }}</span>
                    <span class="text-xs text-slate-500">{{ __('total for this filter') }}</span>
                </p>
            </section>

            <section class="m-6 overflow-hidden rounded-3xl border border-slate-200 bg-white/90 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-left">{{ __('Invoice') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Date') }}</th>
                                @if($crossOrganization)
                                    <th class="px-4 py-3 text-left">{{ __('Organization') }}</th>
                                @endif
                                <th class="px-4 py-3 text-left">{{ __('Customer') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Job') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($invoices as $invoice)
                                @php
                                    $values = is_array($invoice->values) ? $invoice->values : [];
                                    // The job row may be gone; the job number recorded on the
                                    // invoice at the time it was cut is not.
                                    $jobNo = $values['job_no'] ?? $invoice->job?->job_no;
                                @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 font-medium text-slate-900">
                                        {{ $invoice->invoice_number ?? '#'.$invoice->id }}
                                        @if($invoice->isSummary())
                                            <span class="ml-1 rounded-full bg-purple-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-purple-700">{{ __('Summary') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ optional($invoice->created_at)->format('M j, Y') }}</td>
                                    @if($crossOrganization)
                                        <td class="px-4 py-3 text-slate-600">{{ optional($invoice->organization)->name ?? '—' }}</td>
                                    @endif
                                    <td class="px-4 py-3 text-slate-600">{{ optional($invoice->customer)->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600">
                                        @if($invoice->job)
                                            <a href="{{ route('my.jobs.show', ['job' => $invoice->job->id]) }}" class="text-orange-600 hover:underline">{{ $jobNo ?? $invoice->job->id }}</a>
                                        @elseif($jobNo)
                                            <span title="{{ __('The job record is no longer present') }}">{{ $jobNo }}</span>
                                            <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-amber-700">{{ __('No job') }}</span>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-slate-900">
                                        {{ \App\Support\Money::currency((float) ($values['total'] ?? 0)) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($invoice->paid_in_full)
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">{{ __('Paid') }}</span>
                                        @else
                                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700">{{ __('Unpaid') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <a href="{{ route('my.invoices.edit', ['invoice' => $invoice->id]) }}" class="text-orange-600 hover:underline">{{ __('Open') }}</a>
                                        <span class="mx-1 text-slate-300">|</span>
                                        <a href="{{ route('my.invoices.print', ['invoice' => $invoice->id]) }}" class="text-slate-600 hover:underline">{{ __('Print') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $crossOrganization ? 8 : 7 }}" class="px-4 py-10 text-center text-slate-500">{{ __('No invoices match these filters.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($invoices->hasPages())
                    <div class="border-t border-slate-100 px-4 py-3">
                        {{ $invoices->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
