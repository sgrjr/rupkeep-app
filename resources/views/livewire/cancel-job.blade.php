<div>
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-cloak>
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50 transition-opacity" x-on:click="show = false"></div>
                
                <div class="relative w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-slate-900">{{ __('Cancel Job') }}</h3>
                            <button type="button" wire:click="closeModal" class="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <p class="mt-1 text-sm text-slate-500">{{ __('Job #:number', ['number' => $job->job_no ?? __('Unnumbered')]) }}</p>
                    </div>

                    <form wire:submit="cancel" class="px-6 py-4">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700">{{ __('Cancellation Type') }}</label>
                                <p class="mt-1 text-xs text-slate-500">{{ __('Choose how to handle billing for this cancellation.') }}</p>
                                <select wire:model="cancellationType" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                    @foreach($this->getCancellationTypeOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('cancellationType')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-700">{{ __('Reason') }} <span class="text-red-500">*</span></label>
                                <select wire:model="cancellationReason" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                    <option value="">{{ __('Select a reason') }}</option>
                                    @foreach($this->getCancellationReasons() as $key => $label)
                                        <option value="{{ $label }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('cancellationReason')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-700">{{ __('Additional Details') }} <span class="text-xs font-normal text-slate-500">({{ __('Optional') }})</span></label>
                                <textarea wire:model="customReason" rows="3" placeholder="{{ __('Add any additional details about the cancellation...') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"></textarea>
                                @error('customReason')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            @if($cancellationType === 'CANCEL' && $job->scheduled_pickup_at)
                                <div class="rounded-xl border border-blue-100 bg-blue-50 p-3 text-xs text-blue-700">
                                    <p class="font-semibold">{{ __('Auto-determination will check:') }}</p>
                                    <ul class="mt-1 list-inside list-disc space-y-1">
                                        <li>{{ __('If canceled within 24 hours of pickup: $150.00 charge') }}</li>
                                        <li>{{ __('If driver showed up but job didn\'t proceed: $225.00 charge') }}</li>
                                        <li>{{ __('Otherwise: No charge') }}</li>
                                    </ul>
                                </div>
                            @endif
                        </div>

                        <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-4">
                            <button type="button" wire:click="closeModal" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                                {{ __('Confirm Cancellation') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
