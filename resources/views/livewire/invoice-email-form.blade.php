<div>
    <!-- Modal -->
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" 
             wire:click="closeModal" 
             x-data="{ show: @entangle('showModal') }" 
             x-show="show" 
             x-transition>
            <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-3xl border border-slate-200 bg-white shadow-xl" wire:click.stop>
                <div class="sticky top-0 z-10 border-b border-slate-200 bg-gradient-to-r from-orange-500 via-orange-400 to-orange-300 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-white">{{ __('Email Invoice') }}</h3>
                            <p class="mt-1 text-xs text-white/80">{{ __('Send invoice #:number to customer', ['number' => $invoice->invoice_number]) }}</p>
                        </div>
                        <button type="button" wire:click="closeModal" class="text-white/80 hover:text-white">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    <form wire:submit="send" class="space-y-5">
                        <div>
                            <label for="to" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('To') }} <span class="text-red-500">*</span></label>
                            <input type="email" id="to" wire:model="to" required
                                   placeholder="{{ __('customer@example.com') }}"
                                   class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('to') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-slate-500">{{ __('Multiple emails: separate with commas') }}</p>
                        </div>

                        <div>
                            <label for="bcc" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('BCC (Blind Copy)') }}</label>
                            <input type="email" id="bcc" wire:model="bcc"
                                   placeholder="{{ __('your@email.com') }}"
                                   class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            @error('bcc') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-slate-500">{{ __('Your email is pre-filled. Add others separated by commas.') }}</p>
                        </div>

                        <div>
                            <label for="preliminaryText" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Preliminary Text') }}</label>
                            <textarea id="preliminaryText" wire:model="preliminaryText" rows="4"
                                      placeholder="{{ __('Add a personal message that will appear above the invoice in the email...') }}"
                                      class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"></textarea>
                            @error('preliminaryText') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-slate-500">{{ __('Optional message to include before the invoice details') }}</p>
                        </div>

                        @if(config('features.invoice_pdf_downloads', false))
                            <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                                <input type="checkbox" id="includePdf" wire:model="includePdf" 
                                       class="h-4 w-4 rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                                <label for="includePdf" class="flex-1 cursor-pointer text-sm text-slate-700">
                                    <span class="font-semibold">{{ __('Include PDF attachment') }}</span>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ __('Attach a downloadable PDF version of the invoice to the email') }}</p>
                                </label>
                            </div>
                        @endif

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
                            <button type="button" wire:click="closeModal" 
                                    class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit" 
                                    class="inline-flex items-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-orange-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                                </svg>
                                {{ __('Send Invoice') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
