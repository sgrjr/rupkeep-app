@php
    use App\Support\Money;
    use App\Support\LocalTime;
@endphp

{{-- The dashboard a customer-portal login sees (TASK-507). Built only from the
     customer's own invoices and requests: no staff counts, no /my/ links. --}}
<div id="customer-dashboard" class="space-y-10 pb-8">
    <section class="relative overflow-hidden bg-gradient-to-r from-orange-500 via-orange-400 to-orange-300 p-6 text-white shadow-2xl sm:p-8">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.25),_transparent_60%)] opacity-70"></div>
        <div class="relative space-y-2">
            <p class="text-xs font-semibold uppercase tracking-wider text-white/75">{{ $organization?->name }}</p>
            <h1 class="text-3xl font-bold tracking-tight md:text-4xl">
                {{ __('Welcome, :name', ['name' => auth()->user()->name]) }}
            </h1>
            <p class="max-w-xl text-sm text-white/80">
                @if($customer)
                    {{ __('Invoices :organization has sent to :customer, and the requests you have made.', ['organization' => $organization?->name, 'customer' => $customer->name]) }}
                @else
                    {{ __('Your invoices and the requests you have made.') }}
                @endif
            </p>
        </div>
    </section>

    <section class="space-y-6 px-4 sm:px-6 lg:px-8">
        <div class="grid gap-4 sm:grid-cols-3 sm:gap-6">
            <div class="rounded-3xl border border-orange-100 bg-white/90 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Balance due') }}</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ Money::currency($balanceDue) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ trans_choice('{0} Nothing open|{1} On :count open invoice|[2,*] Across :count open invoices', $openCount, ['count' => $openCount]) }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white/90 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Invoices') }}</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ $invoiceCount }}</p>
                <a href="{{ route('customer.invoices.index') }}" class="mt-2 inline-flex text-xs font-semibold text-orange-600 hover:text-orange-700 hover:underline">{{ __('View all invoices') }}</a>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white/90 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Open requests') }}</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ $openRequestCount }}</p>
                <a href="{{ route('portal.tasks.index') }}" class="mt-2 inline-flex text-xs font-semibold text-orange-600 hover:text-orange-700 hover:underline">{{ __('View your requests') }}</a>
            </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('Recent invoices') }}</h2>
                <a href="{{ route('customer.invoices.index') }}" class="text-xs font-semibold text-orange-600 hover:text-orange-700 hover:underline">{{ __('All invoices') }}</a>
            </header>

            @if($recentInvoices->isEmpty())
                <p class="mt-4 text-sm text-slate-500">{{ __('No invoices yet. An invoice appears here once :organization sends it to you.', ['organization' => $organization?->name]) }}</p>
            @else
                <ul class="mt-4 divide-y divide-slate-100">
                    @foreach($recentInvoices as $invoice)
                        @php($balance = $invoice->remaining_balance)
                        <li wire:key="customer-invoice-{{ $invoice->id }}">
                            <a href="{{ route('customer.invoices.show', $invoice) }}" class="flex flex-wrap items-center justify-between gap-3 py-3 transition hover:bg-orange-50/40">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ __('Invoice #:number', ['number' => $invoice->invoice_number]) }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ LocalTime::format($invoice->created_at, 'M j, Y') }}
                                        @if($invoice->job?->job_no)
                                            · {{ __('Job :number', ['number' => $invoice->job->job_no]) }}
                                        @endif
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-slate-900">{{ Money::currency((float) ($invoice->values['total'] ?? 0)) }}</p>
                                    @if($balance > 0)
                                        <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700">{{ __(':amount due', ['amount' => Money::currency($balance)]) }}</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">{{ __('Paid') }}</span>
                                    @endif
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if($myRequests->isNotEmpty())
            <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('Your requests') }}</h2>
                <ul class="mt-4 space-y-2">
                    @foreach($myRequests as $task)
                        <li wire:key="customer-request-{{ $task->id }}">
                            <a href="{{ route('portal.tasks.show', $task) }}" class="block rounded-xl border border-slate-100 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm transition hover:border-orange-300 hover:bg-orange-50/40">
                                <span class="font-semibold">{{ $task->code }}</span> · {{ $task->title }}
                                <span class="ml-1 text-xs text-slate-500">({{ str_replace('_', ' ', $task->status) }})</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
</div>
