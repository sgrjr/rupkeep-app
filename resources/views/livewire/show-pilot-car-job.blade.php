@props(['drivers'=>[], 'vehicles'=>[]])

<div class="bg-slate-100/80 pb-32">
    <div class="mx-auto max-w-6xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-orange-500 via-orange-400 to-orange-300 p-6 text-white shadow-xl">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.25),_transparent_60%)] opacity-70"></div>
            <div class="relative flex flex-wrap items-center justify-between gap-4">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-white/75">{{ __('Pilot Car Job') }}</p>
                        @php
                            $status = $job->status;
                            $statusColors = [
                                'ACTIVE' => 'bg-blue-500/80 text-white',
                                'CANCELLED' => 'bg-red-500/80 text-white',
                                'CANCELLED_NO_GO' => 'bg-amber-500/80 text-white',
                                'COMPLETED' => 'bg-emerald-500/80 text-white',
                            ];
                            $statusColor = $statusColors[$status] ?? 'bg-slate-500/80 text-white';
                        @endphp
                        <span class="inline-flex items-center gap-1.5 rounded-full {{ $statusColor }} px-3 py-1 text-xs font-bold uppercase tracking-wider shadow-sm">
                            {{ $job->status_label }}
                        </span>
                    </div>
                    <h1 class="text-3xl font-bold tracking-tight">{{ $job->job_no ?? __('Unnumbered Job') }}</h1>
                    <div class="flex flex-wrap items-center gap-3 text-sm text-white/85">
                        @if($job->customer_id)
                            <a href="{{route('my.customers.show', ['customer'=>$job->customer_id])}}" class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold transition hover:bg-white/25">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                {{ $job->customer->name }} Jobs
                            </a>
                        @endif
                        <a href="{{ route('my.jobs.index') }}" class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold transition hover:bg-white/25">
                            {{ __('My Jobs') }}
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
                <div class="grid gap-3 rounded-2xl border border-white/25 bg-white/10 px-4 py-3 text-xs font-semibold uppercase tracking-wider text-white/85 shadow-sm backdrop-blur sm:grid-cols-2">
                    <div>
                        {{ __('Pickup') }}
                        <p class="mt-1 text-sm normal-case text-white">
                             {{ $job->pickup_address ?? '—' }}
                            <span class="text-xs italic">{{ optional($job->scheduled_pickup_at)->format('M j, Y g:i A') ?? $job->scheduled_pickup_at ?? '—' }}</span>
                        </p>
                    </div>
                    <div>
                        {{ __('Delivery') }}
                        <p class="mt-1 text-sm normal-case text-white">
                            {{ $job->delivery_address ?? '-'}}
                            <span class="text-xs italic">{{ optional($job->scheduled_delivery_at)->format('M j, Y g:i A') ?? $job->scheduled_delivery_at ?? '—' }}</span>
                        </p>
                    </div>
                    <div>
                        {{ __('Billing Rate') }}
                        <p class="mt-1 text-sm normal-case text-white">
                           {{ $job->effective_rate_code ?? $job->rate_code ?? '—' }}
                        </p>
                    </div>
                    @php
                        $rateComparison = $job->getRateComparison();
                    @endphp
                    @if($rateComparison && $rateComparison['is_mini_better'] && $job->rate_code !== 'mini_flat_rate')
                        <div class="sm:col-span-2 rounded-xl border-2 border-emerald-400/50 bg-gradient-to-br from-emerald-500/20 to-emerald-600/10 p-4 shadow-lg">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-200">{{ __('💰 Rate Comparison') }}</p>
                                    <div class="mt-2 grid grid-cols-2 gap-3 text-xs">
                                        <div class="rounded-lg border border-white/20 bg-white/5 p-2">
                                            <p class="text-[9px] font-semibold uppercase tracking-wide text-white/70">{{ __('Current Rate') }}</p>
                                            <p class="mt-1 text-sm font-bold text-white">{{ $rateComparison['current_rate_label'] }}</p>
                                            <p class="mt-0.5 text-lg font-bold text-white">${{ number_format($rateComparison['current_cost'], 2) }}</p>
                                        </div>
                                        <div class="rounded-lg border-2 border-emerald-300/50 bg-emerald-500/20 p-2">
                                            <p class="text-[9px] font-semibold uppercase tracking-wide text-emerald-200">{{ __('Mini-Run Rate') }}</p>
                                            <p class="mt-1 text-sm font-bold text-emerald-100">Mini-Run ({{ $rateComparison['billable_miles'] }} miles)</p>
                                            <p class="mt-0.5 text-lg font-bold text-emerald-100">${{ number_format($rateComparison['mini_cost'], 2) }}</p>
                                        </div>
                                    </div>
                                    <div class="mt-2 rounded-lg bg-emerald-600/30 px-2 py-1.5">
                                        <p class="text-[10px] font-bold uppercase tracking-wide text-emerald-100">
                                            {{ __('Save $:amount by switching to Mini-Run', ['amount' => number_format($rateComparison['savings'], 2)]) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @elseif($rateComparison && !$rateComparison['is_mini_better'] && $job->rate_code !== 'mini_flat_rate')
                        <div class="sm:col-span-2 rounded-xl border border-white/30 bg-white/10 p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-white/90">{{ __('Rate Comparison') }}</p>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                                <div class="rounded border border-white/20 bg-white/5 p-2">
                                    <p class="text-[9px] text-white/70">{{ __('Current') }}</p>
                                    <p class="mt-0.5 text-sm font-bold text-white">${{ number_format($rateComparison['current_cost'], 2) }}</p>
                                </div>
                                <div class="rounded border border-white/20 bg-white/5 p-2">
                                    <p class="text-[9px] text-white/70">{{ __('Mini-Run') }}</p>
                                    <p class="mt-0.5 text-sm font-bold text-white">${{ number_format($rateComparison['mini_cost'], 2) }}</p>
                                </div>
                            </div>
                            <p class="mt-2 text-[10px] text-white/80">{{ __('Current rate is better for this job.') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        @php
            $totalMiles = (float) (optional($job->miles)->total ?? 0);
            $billableMiles = (float) (optional($job->miles)->billable ?? 0);
            $personalMiles = (float) (optional($job->miles)->personal ?? 0);
        @endphp

        @if($totalMiles > 0 || $billableMiles > 0 || $personalMiles > 0)
        <section class="rounded-3xl border border-orange-200 bg-gradient-to-br from-orange-50 to-orange-100/50 p-6 shadow-lg">
            <header class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">{{ __('Mileage Summary') }}</h2>
                    <p class="text-sm text-slate-600">{{ __('Total distance traveled for this job') }}</p>
                </div>
            </header>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-white/60 bg-white/80 p-5 shadow-sm backdrop-blur">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Total Miles') }}</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($totalMiles, 1) }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ __('All distance traveled') }}</p>
                </div>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-5 shadow-sm backdrop-blur">
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-600">{{ __('Billable Miles') }}</p>
                    <p class="mt-2 text-3xl font-bold text-emerald-700">{{ number_format($billableMiles, 1) }}</p>
                    <p class="mt-1 text-xs text-emerald-600">{{ __('Charged to customer') }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-5 shadow-sm backdrop-blur">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Personal Miles') }}</p>
                    <p class="mt-2 text-3xl font-bold text-slate-700">{{ number_format($personalMiles, 1) }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Non-billable distance') }}</p>
                </div>
            </div>
        </section>
        @endif

        @if($job->public_memo)
            <section class="rounded-3xl border border-emerald-200 bg-emerald-50/60 p-6 shadow-sm">
                <header>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Public Memo (Invoice Notes)') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('This memo will appear on invoices sent to customers.') }}</p>
                </header>
                <div class="mt-4 rounded-xl border border-emerald-200 bg-white p-4">
                    <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ $job->public_memo }}</p>
                </div>
            </section>
        @endif

        @php
            $logMemos = $job->logs()->whereNotNull('memo')->where('memo', '!=', '')->get();
        @endphp
        @if($logMemos->count() > 0)
            <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
                <header>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Internal Log Memos') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Internal memos - not displayed on invoices.') }}</p>
                </header>
                <div class="mt-4 space-y-3">
                    @foreach($logMemos as $log)
                        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 text-xs text-slate-600">
                                        <span class="font-semibold">{{ $log->started_at ? \Carbon\Carbon::parse($log->started_at)->format('M j, Y') : '—' }}</span>
                                        @if($log->user)
                                            <span class="text-slate-400">•</span>
                                            <span>{{ $log->user->name }}</span>
                                        @endif
                                    </div>
                                    <p class="mt-2 text-sm text-slate-700 whitespace-pre-wrap">{{ $log->memo }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @php
            $primaryInvoices = $job->invoices->whereNull('parent_invoice_id');
            $latestInvoice = $primaryInvoices->firstWhere('id', $recentInvoiceId);
        @endphp

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @if(auth()->user()->can('update', $job))
                <a href="{{route('my.jobs.edit', ['job'=>$job->id])}}" class="group relative overflow-hidden rounded-3xl border border-orange-100 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:border-orange-200 hover:shadow-lg">
                    <div class="absolute right-0 top-0 h-24 w-24 -translate-y-12 translate-x-6 rounded-full bg-orange-100 opacity-60 blur-3xl transition group-hover:opacity-80"></div>
                    <div class="relative flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Manage') }}</p>
                            <p class="mt-2 text-lg font-bold text-slate-900">{{ __('Edit Job') }}</p>
                        </div>
                        <span class="rounded-full bg-orange-50 p-2 text-orange-500">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-4.536a2.5 2.5 0 11-3.536 3.536L4.5 16.5V19.5H7.5l8.5-8.5"></path></svg>
                        </span>
                    </div>
                </a>
            @endif

            <div class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-orange-400 to-orange-600"></div>
                <form wire:submit="generateInvoice" class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Invoices') }}</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ __('Generate Invoice') }}</p>
                        </div>
                        <x-action-message class="text-xs font-semibold text-emerald-600" on="updated">
                            {{ __('Created') }}
                        </x-action-message>
                    </div>
                    <x-button type="submit">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 11h16M10 15h10M6 19h8"/></svg>
                        {{ __('Create Invoice') }}
                    </x-button>
                </form>

                @if($primaryInvoices->isNotEmpty())
                    <div class="mt-5 space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Existing Invoices') }}</p>

                        @foreach($primaryInvoices as $invoice)
                            <div @class([
                                    'rounded-2xl border px-4 py-3 text-xs shadow-sm transition',
                                    'border-emerald-200 bg-emerald-50 text-emerald-700' => $recentInvoiceId === $invoice->id,
                                    'border-slate-200 bg-slate-50 text-slate-600' => $recentInvoiceId !== $invoice->id,
                                ])>
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <div class="font-semibold text-slate-800">
                                            {{ __('Invoice #:number', ['number' => $invoice->invoice_number]) }}
                                        </div>
                                        @if($invoice->isSummary())
                                            <span class="inline-flex items-center gap-0.5 rounded-full bg-blue-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-blue-700">
                                                {{ __('Summary') }}
                                            </span>
                                        @endif
                                    </div>
                                    <span class="text-[10px] uppercase tracking-wide text-slate-400">
                                        {{ optional($invoice->created_at)->format('M j, Y g:ia') }}
                                    </span>
                                </div>
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <livewire:invoice-email-form :invoice="$invoice" :key="'email-form-' . $invoice->id" />
                                    <button type="button" onclick="Livewire.dispatch('open-invoice-email-modal-{{ $invoice->id }}')" 
                                            class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-white/70 px-3 py-1 text-[11px] font-semibold text-blue-600 shadow-sm transition hover:bg-blue-500 hover:text-white">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                                        </svg>
                                        {{ __('Email') }}
                                    </button>
                                    <a class="inline-flex items-center gap-1 rounded-full bg-white/70 px-3 py-1 text-[11px] font-semibold text-orange-600 shadow-sm transition hover:bg-orange-500 hover:text-white" href="{{ route('my.invoices.edit', ['invoice' => $invoice->id]) }}">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-4.536a2.5 2.5 0 11-3.536 3.536L4.5 16.5V19.5H7.5l8.5-8.5"/></svg>
                                        {{ __('Edit Invoice') }}
                                    </a>
                                    <a class="inline-flex items-center gap-1 rounded-full border border-white/70 px-3 py-1 text-[11px] font-semibold text-slate-600 transition hover:border-slate-400 hover:text-orange-600" href="{{ route('my.invoices.print', ['invoice' => $invoice->id]) }}" target="_blank">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h10M7 11h10M7 15h5M17 15h.01M6 19h12a2 2 0 002-2v-8a2 2 0 00-2-2h-1V5a2 2 0 00-2-2H9a2 2 0 00-2 2v2H6a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        {{ __('Print') }}
                                    </a>
                                    @if(config('features.invoice_pdf_downloads', false))
                                        <a class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50/70 px-3 py-1 text-[11px] font-semibold text-emerald-700 transition hover:bg-emerald-100" href="{{ route('my.invoices.pdf', ['invoice' => $invoice->id]) }}" title="{{ __('Download PDF') }}">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                                            </svg>
                                            {{ __('PDF') }}
                                        </a>
                                    @endif
                                </div>
                                @if($invoice->isSummary())
                                    <div class="mt-4 space-y-1 text-[11px]">
                                        <p class="font-semibold text-slate-500 uppercase tracking-wide">{{ __('Child Invoices') }}</p>
                                        @foreach(data_get($invoice->values, 'summary_items', []) as $item)
                                            <a href="{{ route('my.invoices.edit', ['invoice' => $item['invoice_id'] ?? null]) }}"
                                               class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-600 transition hover:border-orange-300 hover:text-orange-600">
                                                <span>{{ __('Invoice #:number', ['number' => $item['invoice_number'] ?? '']) }}</span>
                                                <span class="flex items-center gap-3 text-[10px] uppercase tracking-wider text-slate-400">
                                                    {{ $item['job_no'] ?? __('Job') }} · {{ isset($item['total']) ? number_format($item['total'], 2) : '—' }}
                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                                </span>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:col-span-2 lg:col-span-2">
                <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-slate-200 to-slate-300"></div>
                <form wire:submit="uploadFile" class="space-y-3">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Attachments') }}</p>
                    <div class="space-y-3">
                        <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 sm:flex-row sm:flex-wrap sm:items-center">
                            <input type="file" wire:model="file" class="grow rounded-xl border border-dashed border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:outline-none" />
                            <x-button type="submit" variant="ghost" class="w-full justify-center sm:w-auto">
                                <span wire:loading wire:target="uploadFile" class="h-4 w-4 animate-spin rounded-full border-2 border-white/80 border-t-transparent"></span>
                                {{ __('Attach File') }}
                            </x-button>
                            <x-action-message class="text-xs font-semibold text-emerald-600" on="uploaded">
                                {{ __('Uploaded') }}
                            </x-action-message>
                        </div>
                        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2">
                            <input type="checkbox" id="isPublicUpload" wire:model="isPublicUpload" 
                                   class="h-4 w-4 rounded border-slate-300 text-orange-600 focus:ring-orange-500" />
                            <label for="isPublicUpload" class="flex-1 cursor-pointer text-xs text-slate-700">
                                <span class="font-semibold">{{ __('Make visible to customer') }}</span>
                                <p class="mt-0.5 text-[10px] text-slate-500">{{ __('This file will be visible in the customer portal') }}</p>
                            </label>
                        </div>
                    </div>
                    @error('file') <p class="text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                </form>
            </div>

            @if(auth()->user()->can('update', $job) && !$job->canceled_at)
                <div class="rounded-3xl border border-amber-100 bg-white/90 p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">{{ __('Job Management') }}</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ __('Cancel Job') }}</p>
                    <p class="mt-2 text-xs text-slate-500">{{ __('Cancel this job and set appropriate billing based on timing and circumstances.') }}</p>
                    <div class="mt-4">
                        <livewire:cancel-job :job="$job" :key="'cancel-job-' . $job->id" />
                        <button type="button" onclick="Livewire.dispatch('open-cancel-job-modal-{{ $job->id }}')" 
                                class="inline-flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 shadow-sm transition hover:bg-amber-100">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            {{ __('Cancel Job') }}
                        </button>
                    </div>
                </div>
            @endif

            @if(auth()->user()->can('update', $job) && $job->canceled_at)
                <div class="rounded-3xl border border-amber-100 bg-white/90 p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">{{ __('Danger Zone') }}</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ __('Uncancel Job') }}</p>
                    <p class="mt-2 text-xs text-slate-500">{{ __('Reverse the cancellation and restore this job to active status. Afterword visit Edit to manually resolve Billing rate.') }}</p>
                    <div class="mt-4">
                        <button 
                            wire:click="uncancelJob"
                            wire:loading.attr="disabled"
                            wire:target="uncancelJob"
                            class="inline-flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 shadow-sm transition hover:bg-amber-100 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="uncancelJob">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
                                </svg>
                            </span>
                            <span wire:loading wire:target="uncancelJob" class="h-4 w-4 animate-spin rounded-full border-2 border-amber-600 border-t-transparent"></span>
                            {{ __('Uncancel Job') }}
                        </button>
                    </div>
                </div>
            @endif

            @if(auth()->user()->can('delete', $job))
                <div class="rounded-3xl border border-red-100 bg-white/90 p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-red-400">{{ __('Danger Zone') }}</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ __('Delete Job') }}</p>
                    <p class="mt-2 text-xs text-slate-500">{{ __('Removes this job and all associated logs. This cannot be undone.') }}</p>
                    <div class="mt-4">
                        <livewire:delete-confirmation-button
                            :action-url="route('my.jobs.destroy', ['job'=> $job->id])"
                            button-text="{{ __('Delete Job') }}"
                            :model-class="\App\Models\PilotCarJob::class"
                            :record-id="$job->id"
                            resource="jobs"
                            :redirect-route="($redirect_to_root ?? false) ? 'jobs.index' : 'my.jobs.index'"
                        />
                    </div>
                </div>
            @endif
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
            <header class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Job Overview') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Customer, schedule, and financial information for this job.') }}</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ __('Rate: :code', ['code' => $job->rate_code ?? '—']) }}</span>
            </header>

            <div class="grid gap-4 md:grid-cols-2">
                <article class="space-y-3 text-sm text-slate-600">
                    @can('viewAny', new \App\Models\Organization)
                        <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Organization') }}:</span> <span class="text-slate-900">{{ $job->organization->name }}</span></p>
                    @endcan
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Job #') }}:</span> <span class="text-slate-900 font-semibold">{{ $job->job_no }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Load #') }}:</span> <span class="text-slate-900">{{ $job->load_no ?? '—' }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Customer') }}:</span> <span class="text-slate-900">{{ $job->customer?->name ?? '—' }}</span></p>
                    @if($job->default_driver_id)
                        <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Default Driver') }}:</span> <span class="text-slate-900">{{ $job->defaultDriver?->name ?? '—' }}</span></p>
                    @endif
                    @if($job->default_truck_driver_id)
                        <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Default Truck Driver') }}:</span> <span class="text-slate-900">{{ $job->defaultTruckDriver?->name ?? '—' }}</span></p>
                    @endif
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Check #') }}:</span> <span class="text-slate-900">{{ $job->check_no ?? '—' }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Invoice Paid') }}:</span> <span class="font-semibold {{ $job->invoice_paid < 1 ? 'text-red-500' : 'text-emerald-600' }}">{{ $job->invoice_paid < 1 ? __('No') : __('Yes') }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Invoice #') }}:</span> <span class="text-slate-900">{{ $job->invoice_no ?? '—' }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Rate Value') }}:</span> <span class="text-slate-900">{{ $job->rate_value !== null ? '$'.number_format((float) $job->rate_value, 2) : '—' }}</span></p>
                    @if($job->canceled_at)
                        <div class="rounded-xl border border-red-200 bg-red-50 p-3">
                            <p><span class="text-xs font-semibold uppercase tracking-wide text-red-600">{{ __('Canceled At') }}:</span> <span class="text-red-700">{{ optional($job->canceled_at)->format('M j, Y g:i A') }}</span></p>
                            @if($job->canceled_reason)
                                <p class="mt-2"><span class="text-xs font-semibold uppercase tracking-wide text-red-600">{{ __('Cancellation Reason') }}:</span> <span class="text-red-700">{{ $job->canceled_reason }}</span></p>
                            @endif
                            @if(in_array($job->rate_code, ['show_no_go', 'cancellation_24hr', 'cancel_without_billing']))
                                <p class="mt-2"><span class="text-xs font-semibold uppercase tracking-wide text-red-600">{{ __('Cancellation Type') }}:</span> 
                                    <span class="text-red-700">
                                        @if($job->rate_code === 'show_no_go')
                                            {{ __('Show But No-Go') }} ($225.00)
                                        @elseif($job->rate_code === 'cancellation_24hr')
                                            {{ __('Cancellation Within 24hrs') }} ($150.00)
                                        @elseif($job->rate_code === 'cancel_without_billing')
                                            {{ __('Cancel Without Billing') }} (No charge)
                                        @endif
                                    </span>
                                </p>
                            @endif
                        </div>
                    @endif
                </article>

                <article class="space-y-3 text-sm text-slate-600">
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Pickup') }}:</span> <a class="text-orange-600 hover:text-orange-700" target="_blank" href="http://maps.google.com/?daddr={{$job->pickup_address}}">{{ $job->pickup_address ?? '—' }}</a></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Pickup Time') }}:</span> <span class="text-slate-900">{{ $job->scheduled_pickup_at ?? '—' }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Delivery') }}:</span> <a class="text-orange-600 hover:text-orange-700" target="_blank" href="http://maps.google.com/?daddr={{$job->delivery_address}}">{{ $job->delivery_address ?? '—' }}</a></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Delivery Time') }}:</span> <span class="text-slate-900">{{ $job->scheduled_delivery_at ?? '—' }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Memo') }}:</span>
                        @if(str_starts_with($job->memo ?? '', 'http'))
                            <a target="_blank" href="{!!$job->memo!!}" class="text-orange-600 hover:text-orange-700">{{ __('View Link') }}</a>
                        @else
                            <span class="text-slate-900">{{ $job->memo ?? '—' }}</span>
                        @endif
                    </p>
                    <div class="pt-2">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Invoices') }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @if($primaryInvoices->isEmpty())
                                <span class="text-xs text-slate-400">{{ __('No invoices yet.') }}</span>
                            @else
                                <span class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-600">
                                    {{ trans_choice('{0} No invoices|{1} :count invoice|[2,*] :count invoices', $primaryInvoices->count(), ['count' => $primaryInvoices->count()]) }}
                                </span>
                                @if($latestInvoice)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-semibold text-emerald-700">
                                        {{ __('Latest Invoice #:number ready', ['number' => $latestInvoice->invoice_number]) }}
                                    </span>
                                @endif
                            @endif
                        </div>
                    </div>
                </article>
            </div>

            @if($job->customer)
                <details class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <summary class="cursor-pointer text-sm font-semibold text-orange-600 hover:text-orange-700">{{ __('Customer Contact Info') }}</summary>
                    <div class="mt-3 space-y-3 text-sm text-slate-600">
                        <p>{{ $job->customer->street }} {{ $job->customer->city }}, {{ $job->customer->state }} {{ $job->customer->zip }}</p>
                        <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                <thead class="bg-slate-100 text-slate-500">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-semibold uppercase tracking-wider">{{ __('Name') }}</th>
                                        <th class="px-4 py-2 text-left font-semibold uppercase tracking-wider">{{ __('Phone') }}</th>
                                        <th class="px-4 py-2 text-left font-semibold uppercase tracking-wider">{{ __('Memo') }}</th>
                                        @if(auth()->user()->can('update', $job) || auth()->user()->can('createJob', auth()->user()->organization))
                                            <th class="px-4 py-2 text-left font-semibold uppercase tracking-wider">{{ __('Actions') }}</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse($job->customer->contacts as $contact)
                                        <tr>
                                            <td class="px-4 py-2 text-slate-700">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span>{{ $contact->name }}</span>
                                                    @if($contact->is_main_contact)
                                                        <span class="inline-flex items-center gap-0.5 rounded-full bg-blue-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-blue-700">
                                                            {{ __('Main') }}
                                                        </span>
                                                    @endif
                                                    @if($contact->is_billing_contact)
                                                        <span class="inline-flex items-center gap-0.5 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-emerald-700">
                                                            {{ __('Billing') }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-2 text-slate-700">
                                                @if($contact->phone)
                                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact->phone) }}" class="inline-flex items-center gap-1 text-orange-600 hover:text-orange-700 hover:underline">
                                                        {{ $contact->phone }}
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/>
                                                        </svg>
                                                    </a>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-slate-500">{{ $contact->memo }}</td>
                                            @if(auth()->user()->can('update', $job) || auth()->user()->can('createJob', auth()->user()->organization))
                                                <td class="px-4 py-2">
                                                    @if($contact->notification_address || $contact->email)
                                                        <button 
                                                            wire:click="notifyCustomerContact({{ $contact->id }})"
                                                            wire:loading.attr="disabled"
                                                            class="inline-flex items-center gap-1 rounded-full border border-orange-200 bg-orange-50 px-2 py-1 text-[10px] font-semibold text-orange-700 transition hover:bg-orange-100 disabled:opacity-50"
                                                            title="{{ __('Send job status notification') }}">
                                                            <svg wire:loading.remove wire:target="notifyCustomerContact({{ $contact->id }})" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                                                            </svg>
                                                            <span wire:loading.remove wire:target="notifyCustomerContact({{ $contact->id }})">{{ __('Notify') }}</span>
                                                            <span wire:loading wire:target="notifyCustomerContact({{ $contact->id }})" class="h-3 w-3 animate-spin rounded-full border-2 border-orange-600 border-t-transparent"></span>
                                                        </button>
                                                    @else
                                                        <span class="text-[10px] text-slate-400">{{ __('No contact info') }}</span>
                                                    @endif
                                                </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ auth()->user()->can('update', $job) || auth()->user()->can('createJob', auth()->user()->organization) ? '4' : '3' }}" class="px-4 py-3 text-center text-slate-400">{{ __('No contacts recorded.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </details>
            @endif

            <div class="mt-6">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Attachments (:count)', ['count' => $job->attachments? $job->attachments->count():0]) }}</p>
                <div class="mt-3 space-y-3">
                    @forelse($job->attachments as $att)
                        <div class="flex flex-col gap-3 rounded-2xl border px-4 py-3 shadow-sm sm:flex-row sm:flex-wrap sm:items-center sm:justify-between {{ $att->is_public ? 'border-emerald-200 bg-emerald-50/30' : 'border-slate-200 bg-white' }}">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <a class="text-sm font-semibold text-orange-600 hover:text-orange-700" download href="{{route('attachments.download', ['attachment'=>$att->id])}}">
                                        {{ $att->file_name }}
                                    </a>
                                    @if($att->is_public)
                                        <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                            </svg>
                                            {{ __('Public') }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-500">{{ $att->created_at->diffForHumans() }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @can('updateVisibility', $att)
                                    <livewire:attachment-visibility-toggle :attachment="$att" :key="'job-att-'.$att->id"/>
                                @else
                                    @if($att->is_public)
                                        <span class="text-xs font-semibold text-emerald-600">{{ __('Visible to customer') }}</span>
                                    @else
                                        <span class="text-xs font-semibold text-slate-500">{{ __('Staff only') }}</span>
                                    @endif
                                @endcan
                                <livewire:delete-confirmation-button
                                    :action-url="route('attachments.destroy', ['attachment'=> $att->id])"
                                    button-text="&times;"
                                    :redirect-route="Route::currentRouteName()"
                                    :model-class="\App\Models\Attachment::class"
                                    :record-id="$att->id"
                                    resource="attachments"
                                />
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">{{ __('No attachments yet. Upload permits, maps, or photos from the actions above.') }}</p>
                    @endforelse
                </div>
            </div>
        </section>

        @if($job->logs)
            <section id="job-logs" class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ __('Job Logs') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Assign drivers and manage daily records for this job.') }}</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ trans_choice('{0} No logs|{1} :count log|[2,*] :count logs', $job->logs->count(), ['count' => $job->logs->count()]) }}</span>
                </header>

                <div class="mt-6 space-y-6">
                    <form wire:submit="assignJob" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 shadow-sm">
                        <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Assign driver & vehicle') }}</label>
                            <div class="w-full sm:flex-1">
                                <select wire:model.blur="assignment.car_driver_id" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200" required>
                                    <option value="">{{ __('Select Driver') }}</option>
                                    @foreach($drivers as $driver)
                                        <option value="{{ $driver['value'] }}">{{ $driver['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="w-full sm:flex-1">
                                <select wire:model.blur="assignment.vehicle_id" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                    <option value="">{{ __('Select Vehicle') }}</option>
                                    @foreach($vehicles as $vehicle)
                                        <option value="{{ $vehicle['value'] }}">{{ $vehicle['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="w-full sm:flex-1">
                                <select wire:model.blur="assignment.vehicle_position" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                    <option value="">{{ __('Select Position') }}</option>
                                    @foreach($vehicle_positions as $position)
                                        <option value="{{ $position['value'] }}">{{ $position['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <x-action-message class="text-xs font-semibold text-emerald-600" on="updated">
                                {{ __('Assigned') }}
                            </x-action-message>
                            <x-button type="submit" class="w-full justify-center sm:w-auto">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                {{ __('Assign') }}
                            </x-button>
                        </div>
                    </form>

                    <div class="space-y-4">
                        @foreach($job->logs as $log)
                            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Log ID') }}: {{ $log->id }}</p>
                                        <p class="text-sm font-semibold text-slate-900">{{ optional($log->created_at)->toFormattedDateString() }}</p>
                                        <p class="text-xs text-slate-500">{{ __('Driver') }}: {{ $log->driver?->name ?? '—' }}</p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if(auth()->user()->can('update', $log))
                                            @php
                                                $logEditUrl = route('logs.edit', ['log' => $log->id]);
                                            @endphp
                                            <x-button type="button" variant="ghost" onclick="window.location.href='{{ $logEditUrl }}'">
                                                {{ __('Edit Log') }}
                                            </x-button>
                                        @endif
                                        @if(auth()->user()->can('delete', $log))
                                            <livewire:delete-confirmation-button
                                                :action-url="route('logs.destroy', ['log'=> $log->id])"
                                                button-text="{{ __('Delete') }}"
                                                :redirect-route="Route::currentRouteName()"
                                                :model-class="\App\Models\UserLog::class"
                                                :record-id="$log->id"
                                                resource="logs"
                                            />
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-3 md:grid-cols-2">
                                    <div class="space-y-1 text-sm text-slate-600">
                                        <p><span class="font-semibold text-slate-900">{{ __('Start Mileage') }}:</span> {{ $log->start_mileage ?? '—' }}</p>
                                        <p><span class="font-semibold text-slate-900">{{ __('End Mileage') }}:</span> {{ $log->end_mileage ?? '—' }}</p>
                                        <p><span class="font-semibold text-slate-900">{{ __('Total Miles') }}:</span> {{ $log->total_miles ?? '—' }}</p>
                                        <p><span class="font-semibold text-slate-900">{{ __('Billable Override') }}:</span> {{ $log->billable_miles ?? '—' }}</p>
                                    </div>
                                    <div class="space-y-1 text-sm text-slate-600">
                                        <p><span class="font-semibold text-slate-900">{{ __('Deadhead') }}:</span> {{ $log->dead_head_times ?? '—' }}</p>
                                        <p><span class="font-semibold text-slate-900">{{ __('Extra Stops') }}:</span> {{ $log->extra_load_stops ?? '—' }}</p>
                                        <p><span class="font-semibold text-slate-900">{{ __('Tolls') }}:</span> {{ $log->tolls ? '$'.number_format($log->tolls, 2) : '—' }}</p>
                                        <p><span class="font-semibold text-slate-900">{{ __('Hotel') }}:</span> {{ $log->hotel ? '$'.number_format($log->hotel, 2) : '—' }}</p>
                                    </div>
                                </div>

                                <div class="mt-4 text-sm text-slate-600">
                                    <p class="font-semibold text-slate-900">{{ __('Memo') }}</p>
                                    <p class="mt-1 text-slate-500">{{ $log->memo ?? __('No memo recorded.') }}</p>
                                </div>

                                @if($log->attachments?->count())
                                    <div class="mt-4 space-y-2">
                                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Log Attachments') }}</p>
                                        @foreach($log->attachments as $att)
                                            <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                                                <div class="min-w-0 flex-1">
                                                    <a class="text-sm font-semibold text-orange-600 hover:text-orange-700" download href="{{route('attachments.download', ['attachment'=>$att->id])}}">{{ $att->file_name }}</a>
                                                    <p class="text-xs text-slate-500">{{ $att->created_at->diffForHumans() }}</p>
                                                </div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    @can('updateVisibility', $att)
                                                        <livewire:attachment-visibility-toggle :attachment="$att" :key="'log-att-'.$att->id"/>
                                                    @else
                                                        @if($att->is_public)
                                                            <span class="text-xs font-semibold text-emerald-600">{{ __('Visible to customer') }}</span>
                                                        @endif
                                                    @endcan
                                                    <livewire:delete-confirmation-button
                                                        :action-url="route('attachments.destroy', ['attachment'=> $att->id])"
                                                        button-text="&times;"
                                                        :redirect-route="Route::currentRouteName()"
                                                        :model-class="\App\Models\Attachment::class"
                                                        :record-id="$att->id"
                                                        resource="attachments"
                                                    />
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    </div>
</div>