<div>
    <!-- Trigger Button -->
    <button 
        wire:click="openModal"
        class="inline-flex items-center gap-2 rounded-full bg-orange-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-orange-700"
    >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
        </svg>
        {{ __('Create Summary Invoice') }}
    </button>

    <!-- Modal -->
    @if($showModal)
        <div 
            class="fixed inset-0 z-50 overflow-y-auto"
            x-data="{ show: @entangle('showModal') }"
            x-show="show"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @keydown.escape.window="show = false"
        >
            <div class="flex min-h-screen items-center justify-center p-4">
                <!-- Backdrop -->
                <div 
                    class="fixed inset-0 bg-black/50 transition-opacity"
                    x-show="show"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    @click="show = false"
                ></div>

                <!-- Modal Panel -->
                <div 
                    class="relative z-10 w-full max-w-4xl transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all"
                    x-show="show"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                >
                    <!-- Header -->
                    <div class="border-b border-slate-200 bg-gradient-to-r from-orange-500 to-orange-400 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-white">{{ __('Create Summary Invoice') }}</h3>
                                <p class="mt-1 text-sm text-white/90">{{ __('Select multiple invoices to group into a summary invoice.') }}</p>
                            </div>
                            <button 
                                wire:click="closeModal"
                                class="rounded-full p-1 text-white/80 transition hover:bg-white/20 hover:text-white"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="max-h-[60vh] overflow-y-auto p-6">
                        @if(count($invoices) === 0)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-8 text-center">
                                <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h11.25c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/>
                                </svg>
                                <p class="mt-4 text-sm font-semibold text-slate-900">{{ __('No invoices available') }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ __('All invoices are already part of a summary or there are no invoices to group.') }}</p>
                            </div>
                        @else
                            <div class="space-y-3">
                                <div class="flex items-center justify-between rounded-lg border border-orange-200 bg-orange-50 px-4 py-2">
                                    <p class="text-sm font-semibold text-orange-900">
                                        {{ __('Selected: :count invoice(s)', ['count' => count($selectedInvoiceIds)]) }}
                                    </p>
                                    @if(count($selectedInvoiceIds) >= 2)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-700">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            {{ __('Ready to create') }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-500">{{ __('Select at least 2 invoices') }}</span>
                                    @endif
                                </div>

                                <div class="space-y-2">
                                    @foreach($invoices as $invoice)
                                        @php
                                            $isSelected = in_array($invoice->id, $selectedInvoiceIds);
                                            $values = $invoice->values ?? [];
                                            $total = (float)($values['total'] ?? 0);
                                        @endphp
                                        <label 
                                            class="flex cursor-pointer items-start gap-4 rounded-xl border-2 p-4 transition hover:bg-slate-50 {{ $isSelected ? 'border-orange-500 bg-orange-50' : 'border-slate-200' }}"
                                        >
                                            <input 
                                                type="checkbox" 
                                                wire:click="toggleInvoice({{ $invoice->id }})"
                                                {{ $isSelected ? 'checked' : '' }}
                                                class="mt-1 h-4 w-4 rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                                            >
                                            <div class="flex-1">
                                                <div class="flex items-start justify-between">
                                                    <div class="flex-1">
                                                        <p class="font-semibold text-slate-900">
                                                            {{ __('Invoice #:number', ['number' => $invoice->invoice_number]) }}
                                                        </p>
                                                        <p class="mt-1 text-xs text-slate-600">
                                                            {{ __('Customer: :name', ['name' => $invoice->customer->name ?? '—']) }}
                                                        </p>
                                                        @if($invoice->job)
                                                            <p class="mt-1 text-xs text-slate-500">
                                                                {{ __('Job #:job_no', ['job_no' => $invoice->job->job_no ?? '—']) }}
                                                                @if($invoice->job->load_no)
                                                                    • {{ __('Load #:load_no', ['load_no' => $invoice->job->load_no]) }}
                                                                @endif
                                                            </p>
                                                        @endif
                                                        <p class="mt-1 text-xs text-slate-500">
                                                            {{ __('Created: :date', ['date' => $invoice->created_at->format('M j, Y')]) }}
                                                        </p>
                                                    </div>
                                                    <div class="text-right">
                                                        <p class="text-lg font-bold text-slate-900">${{ number_format($total, 2) }}</p>
                                                        @if($invoice->paid_in_full)
                                                            <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-700">
                                                                {{ __('Paid') }}
                                                            </span>
                                                        @else
                                                            <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2 py-0.5 text-[10px] font-semibold text-yellow-700">
                                                                {{ __('Unpaid') }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Footer -->
                    <div class="border-t border-slate-200 bg-slate-50 px-6 py-4">
                        <div class="flex items-center justify-end gap-3">
                            <button 
                                wire:click="closeModal"
                                class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                            >
                                {{ __('Cancel') }}
                            </button>
                            <button 
                                wire:click="createSummary"
                                wire:loading.attr="disabled"
                                wire:target="createSummary"
                                class="inline-flex items-center gap-2 rounded-full bg-orange-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-orange-700 disabled:opacity-50"
                                {{ count($selectedInvoiceIds) < 2 ? 'disabled' : '' }}
                            >
                                <svg wire:loading.remove wire:target="createSummary" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                <svg wire:loading wire:target="createSummary" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ __('Create Summary Invoice') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
