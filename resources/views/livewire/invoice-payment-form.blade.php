<div>
    <button type="button" onclick="Livewire.dispatch('open-invoice-payment-modal-{{ $invoice->id }}')" 
            class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"/>
        </svg>
        {{ __('Record Payment') }}
    </button>

    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-transition style="display: none;" x-cloak>
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50 transition-opacity" @click="$wire.closeModal()"></div>
                
                <div class="relative w-full max-w-2xl rounded-3xl border border-slate-200 bg-white shadow-xl" @click.stop>
                    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                        <h3 class="text-lg font-semibold text-slate-900">{{ __('Record Payment') }}</h3>
                        <button type="button" @click="$wire.closeModal()" class="text-slate-400 hover:text-slate-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form wire:submit="applyPayment" class="space-y-6 p-6">
                        <div class="grid gap-4 rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                            <div class="grid grid-cols-3 gap-4 text-sm">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Total Due') }}</p>
                                    <p class="mt-1 text-lg font-semibold text-slate-900">${{ number_format($totalDue, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Total Paid') }}</p>
                                    <p class="mt-1 text-lg font-semibold text-emerald-600">${{ number_format($totalPaid, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Remaining') }}</p>
                                    <p class="mt-1 text-lg font-semibold {{ $remainingBalance > 0 ? 'text-amber-600' : 'text-emerald-600' }}">
                                        ${{ number_format($remainingBalance, 2) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600 mb-1">
                                    {{ __('Payment Amount') }} <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500">$</span>
                                    <input type="number" step="0.01" min="0.01" wire:model="paymentAmount" 
                                           class="w-full rounded-xl border border-slate-200 bg-white pl-8 pr-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"
                                           placeholder="0.00">
                                </div>
                                @error('paymentAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600 mb-1">
                                    {{ __('Payment Date') }}
                                </label>
                                <input type="date" wire:model="paymentDate" 
                                       class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                @error('paymentDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600 mb-1">
                                    {{ __('Payment Method') }}
                                </label>
                                <select wire:model="paymentMethod" 
                                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                    <option value="">{{ __('Select method') }}</option>
                                    <option value="check">{{ __('Check') }}</option>
                                    <option value="cash">{{ __('Cash') }}</option>
                                    <option value="wire_transfer">{{ __('Wire Transfer') }}</option>
                                    <option value="ach">{{ __('ACH') }}</option>
                                    <option value="credit_card">{{ __('Credit Card') }}</option>
                                    <option value="other">{{ __('Other') }}</option>
                                </select>
                                @error('paymentMethod') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600 mb-1">
                                    {{ __('Check Number') }}
                                </label>
                                <input type="text" wire:model="checkNumber" 
                                       class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"
                                       placeholder="{{ __('If applicable') }}">
                                @error('checkNumber') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        @if($availableCredit > 0)
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" wire:model.live="useAccountCredit" 
                                           class="h-4 w-4 rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-emerald-900">{{ __('Apply Account Credit') }}</p>
                                        <p class="text-xs text-emerald-700">
                                            {{ __('Available credit: $:amount', ['amount' => number_format($availableCredit, 2)]) }}
                                        </p>
                                    </div>
                                </label>

                                @if($useAccountCredit)
                                    <div class="mt-3">
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600 mb-1">
                                            {{ __('Credit Amount to Apply') }}
                                        </label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500">$</span>
                                            <input type="number" step="0.01" min="0" max="{{ $availableCredit }}" wire:model="creditAmount" 
                                                   class="w-full rounded-xl border border-slate-200 bg-white pl-8 pr-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"
                                                   placeholder="0.00">
                                        </div>
                                        @error('creditAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                @endif
                            </div>
                        @endif

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600 mb-1">
                                {{ __('Notes') }}
                            </label>
                            <textarea wire:model="notes" rows="3" 
                                      class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"
                                      placeholder="{{ __('Optional payment notes') }}"></textarea>
                            @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
                            <button type="button" @click="$wire.closeModal()" 
                                    class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit" 
                                    class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-500 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-orange-600">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                </svg>
                                {{ __('Record Payment') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
