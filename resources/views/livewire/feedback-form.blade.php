<div>
    <!-- Modal Trigger Button (for hamburger menu) -->
    @if($showModal === false && !$hideTrigger)
        <button type="button" wire:click="openModal" 
                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-orange-300 hover:text-orange-600">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
            </svg>
            {{ __('Feedback') }}
        </button>
    @endif

    <!-- Modal -->
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:click="closeModal" 
             x-data="{ show: @entangle('showModal') }" 
             x-show="show" 
             x-transition
             @feedback-submitted.window="setTimeout(() => { show = false; }, 2000)">
            <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white shadow-xl" wire:click.stop>
                <div class="border-b border-slate-200 bg-gradient-to-r from-orange-500 via-orange-400 to-orange-300 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-white">{{ __('Send Feedback') }}</h3>
                        <button type="button" wire:click="closeModal" class="text-white/80 hover:text-white">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="p-6">
                    @if($submitted)
                        <div class="text-center">
                            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100">
                                <svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-slate-900">{{ __('Thank you for your feedback!') }}</p>
                            <p class="mt-1 text-xs text-slate-600">{{ __('We appreciate your input and will review it soon.') }}</p>
                            <button type="button" wire:click="closeModal" 
                                    @click="$dispatch('feedback-submitted')"
                                    class="mt-4 inline-flex items-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-orange-600">
                                {{ __('Close') }}
                            </button>
                        </div>
                    @else
                        <form wire:submit="submit" class="space-y-4">
                            <div>
                                <label for="severity" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Type') }}</label>
                                <select id="severity" wire:model="severity" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                    <option value="info">{{ __('Informational') }} - {{ __('Suggestion, general feedback') }}</option>
                                    <option value="error">{{ __('Critical') }} - {{ __('Bug, error, fix this, need this now') }}</option>
                                </select>
                                @error('severity') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="feedback" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Your Feedback') }}</label>
                                <textarea id="feedback" wire:model="feedback" rows="6" 
                                          placeholder="{{ __('Please share your thoughts, suggestions, or report any issues you\'ve encountered...') }}"
                                          class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"></textarea>
                                @error('feedback') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                <p class="mt-1 text-xs text-slate-500">{{ __('Minimum 3 characters, maximum 5000 characters.') }}</p>
                            </div>

                            <div class="flex items-center justify-end gap-3">
                                <button type="button" wire:click="closeModal" 
                                        class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="submit" 
                                        class="inline-flex items-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-orange-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                                    </svg>
                                    {{ __('Send Feedback') }}
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Form for external modal (footer) -->
    @if($renderInModal)
        @if($submitted)
            <div class="text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100">
                    <svg class="h-6 w-6 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-slate-900">{{ __('Thank you for your feedback!') }}</p>
                <p class="mt-1 text-xs text-slate-600">{{ __('We appreciate your input and will review it soon.') }}</p>
            </div>
        @else
            <form wire:submit="submit" class="space-y-4">
                <div>
                    <label for="severity_modal" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Type') }}</label>
                    <select id="severity_modal" wire:model="severity" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                        <option value="info">{{ __('Informational') }} - {{ __('Suggestion, general feedback') }}</option>
                        <option value="error">{{ __('Critical') }} - {{ __('Bug, error, fix this, need this now') }}</option>
                    </select>
                    @error('severity') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="feedback_modal" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Your Feedback') }}</label>
                    <textarea id="feedback_modal" wire:model="feedback" rows="6" 
                              placeholder="{{ __('Please share your thoughts, suggestions, or report any issues you\'ve encountered...') }}"
                              class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"></textarea>
                    @error('feedback') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-slate-500">{{ __('Minimum 3 characters, maximum 5000 characters.') }}</p>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <button type="button" onclick="document.getElementById('footer-feedback-modal').classList.add('hidden')" 
                            class="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" 
                            class="inline-flex items-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-orange-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                        </svg>
                        {{ __('Send Feedback') }}
                    </button>
                </div>
            </form>
        @endif
    @endif

    <!-- Inline Form (for footer or dedicated page) -->
    @if(!$showModal && $inline && !$renderInModal)
        <div class="w-full rounded-2xl border border-slate-200 bg-white/90 p-6 shadow-sm">
            <h3 class="mb-4 text-lg font-semibold text-slate-900">{{ __('Send Feedback') }}</h3>
            
            @if($submitted)
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    {{ __('Thank you for your feedback! We appreciate your input.') }}
                </div>
            @else
                <form wire:submit="submit" class="space-y-4">
                    <div>
                        <label for="severity_inline" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Type') }}</label>
                        <select id="severity_inline" wire:model="severity" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            <option value="info">{{ __('Informational') }} - {{ __('Suggestion, general feedback') }}</option>
                            <option value="error">{{ __('Critical') }} - {{ __('Bug, error, fix this, need this now') }}</option>
                        </select>
                        @error('severity') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="feedback_inline" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Your Feedback') }}</label>
                        <textarea id="feedback_inline" wire:model="feedback" rows="6" 
                                  placeholder="{{ __('Please share your thoughts, suggestions, or report any issues you\'ve encountered...') }}"
                                  class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"></textarea>
                        @error('feedback') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-slate-500">{{ __('Minimum 3 characters, maximum 5000 characters.') }}</p>
                    </div>

                    <div class="flex items-center justify-end">
                        <button type="submit" 
                                class="inline-flex items-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-orange-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                            </svg>
                            {{ __('Send Feedback') }}
                        </button>
                    </div>
                </form>
            @endif
        </div>
    @endif
</div>
