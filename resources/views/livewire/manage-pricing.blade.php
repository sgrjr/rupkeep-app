<div class="bg-slate-100/80 pb-32">
    <div class="mx-auto max-w-6xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-orange-500 via-orange-400 to-orange-300 p-6 text-white shadow-xl">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.25),_transparent_60%)] opacity-70"></div>
            <div class="relative flex flex-wrap items-center justify-between gap-4">
                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-white/75">{{ __('Pricing Management') }}</p>
                    <h1 class="text-3xl font-bold tracking-tight">{{ $organization->name }}</h1>
                    <p class="text-sm text-white/85">{{ __('Manage rates, charges, cancellation rules, and payment terms for this organization.') }}</p>
                </div>
            </div>
        </section>

        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        <!-- Tabs -->
        <div class="flex flex-wrap gap-2 border-b border-slate-200">
            <button wire:click="$set('activeTab', 'rates')" 
                    @class([
                        'px-4 py-2 text-sm font-semibold transition',
                        'border-b-2 border-orange-500 text-orange-600' => $activeTab === 'rates',
                        'text-slate-600 hover:text-slate-900' => $activeTab !== 'rates',
                    ])>
                {{ __('Rates') }}
            </button>
            <button wire:click="$set('activeTab', 'charges')" 
                    @class([
                        'px-4 py-2 text-sm font-semibold transition',
                        'border-b-2 border-orange-500 text-orange-600' => $activeTab === 'charges',
                        'text-slate-600 hover:text-slate-900' => $activeTab !== 'charges',
                    ])>
                {{ __('Charges') }}
            </button>
            <button wire:click="$set('activeTab', 'cancellation')" 
                    @class([
                        'px-4 py-2 text-sm font-semibold transition',
                        'border-b-2 border-orange-500 text-orange-600' => $activeTab === 'cancellation',
                        'text-slate-600 hover:text-slate-900' => $activeTab !== 'cancellation',
                    ])>
                {{ __('Cancellation') }}
            </button>
            <button wire:click="$set('activeTab', 'payment_terms')" 
                    @class([
                        'px-4 py-2 text-sm font-semibold transition',
                        'border-b-2 border-orange-500 text-orange-600' => $activeTab === 'payment_terms',
                        'text-slate-600 hover:text-slate-900' => $activeTab !== 'payment_terms',
                    ])>
                {{ __('Payment Terms') }}
            </button>
        </div>

        <!-- Rates Tab -->
        @if($activeTab === 'rates')
            <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
                <header class="mb-6">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Pricing Rates') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Configure per-mile and flat rates for different job types. Leave blank to use system defaults.') }}</p>
                </header>

                <div class="space-y-6">
                    @foreach($rates as $code => $rate)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                            <div class="mb-4">
                                <h3 class="text-base font-semibold text-slate-900">{{ $rate['name'] }}</h3>
                                <p class="text-xs text-slate-500">{{ $rate['description'] }}</p>
                            </div>

                            @if($rate['type'] === 'per_mile')
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Rate Per Mile') }}</label>
                                        <div class="mt-2 flex items-center gap-2">
                                            <span class="text-slate-500">$</span>
                                            <input type="number" 
                                                   step="0.01" 
                                                   min="0"
                                                   value="{{ $rate['rate_per_mile'] }}"
                                                   wire:change="updateRate('{{ $code }}', 'rate_per_mile', $event.target.value)"
                                                   class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                        </div>
                                    </div>
                                </div>
                            @elseif($rate['type'] === 'flat')
                                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Flat Amount') }}</label>
                                        <div class="mt-2 flex items-center gap-2">
                                            <span class="text-slate-500">$</span>
                                            <input type="number" 
                                                   step="0.01" 
                                                   min="0"
                                                   value="{{ $rate['flat_amount'] }}"
                                                   wire:change="updateRate('{{ $code }}', 'flat_amount', $event.target.value)"
                                                   class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                        </div>
                                    </div>
                                    @if(isset($rate['max_miles']))
                                        <div>
                                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Max Miles') }}</label>
                                            <input type="number" 
                                                   step="1" 
                                                   min="0"
                                                   value="{{ $rate['max_miles'] }}"
                                                   wire:change="updateRate('{{ $code }}', 'max_miles', $event.target.value)"
                                                   class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                        </div>
                                    @endif
                                    @if(isset($rate['max_hours']))
                                        <div>
                                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Max Hours') }}</label>
                                            <input type="number" 
                                                   step="1" 
                                                   min="0"
                                                   value="{{ $rate['max_hours'] }}"
                                                   wire:change="updateRate('{{ $code }}', 'max_hours', $event.target.value)"
                                                   class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <!-- Charges Tab -->
        @if($activeTab === 'charges')
            <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
                <header class="mb-6">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Additional Charges') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Configure charges for wait time, extra stops, tolls, and deadhead miles.') }}</p>
                </header>

                <div class="space-y-6">
                    @foreach($charges as $key => $charge)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                            <div class="mb-4">
                                <h3 class="text-base font-semibold text-slate-900">{{ $charge['name'] }}</h3>
                                @if($charge['description'])
                                    <p class="text-xs text-slate-500">{{ $charge['description'] }}</p>
                                @endif
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                @if(isset($charge['rate_per_hour']))
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Rate Per Hour') }}</label>
                                        <div class="mt-2 flex items-center gap-2">
                                            <span class="text-slate-500">$</span>
                                            <input type="number" 
                                                   step="0.01" 
                                                   min="0"
                                                   value="{{ $charge['rate_per_hour'] }}"
                                                   wire:change="updateCharge('{{ $key }}', 'rate_per_hour', $event.target.value)"
                                                   class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                        </div>
                                    </div>
                                @endif
                                @if(isset($charge['rate_per_stop']))
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Rate Per Stop') }}</label>
                                        <div class="mt-2 flex items-center gap-2">
                                            <span class="text-slate-500">$</span>
                                            <input type="number" 
                                                   step="0.01" 
                                                   min="0"
                                                   value="{{ $charge['rate_per_stop'] }}"
                                                   wire:change="updateCharge('{{ $key }}', 'rate_per_stop', $event.target.value)"
                                                   class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                        </div>
                                    </div>
                                @endif
                                @if(isset($charge['rate_per_mile']))
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Rate Per Mile') }}</label>
                                        <div class="mt-2 flex items-center gap-2">
                                            <span class="text-slate-500">$</span>
                                            <input type="number" 
                                                   step="0.01" 
                                                   min="0"
                                                   value="{{ $charge['rate_per_mile'] }}"
                                                   wire:change="updateCharge('{{ $key }}', 'rate_per_mile', $event.target.value)"
                                                   class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                        </div>
                                    </div>
                                @endif
                                @if(isset($charge['minimum_hours']))
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Minimum Hours') }}</label>
                                        <input type="number" 
                                               step="1" 
                                               min="0"
                                               value="{{ $charge['minimum_hours'] }}"
                                               wire:change="updateCharge('{{ $key }}', 'minimum_hours', $event.target.value)"
                                               class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                    </div>
                                @endif
                                @if(isset($charge['free_miles']))
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Free Miles') }}</label>
                                        <input type="number" 
                                               step="1" 
                                               min="0"
                                               value="{{ $charge['free_miles'] }}"
                                               wire:change="updateCharge('{{ $key }}', 'free_miles', $event.target.value)"
                                               class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <!-- Cancellation Tab -->
        @if($activeTab === 'cancellation')
            <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
                <header class="mb-6">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Cancellation Settings') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Configure how cancellations are handled and billed.') }}</p>
                </header>

                <div class="space-y-6">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <div class="mb-4">
                            <h3 class="text-base font-semibold text-slate-900">{{ __('Auto-Determine Cancellation Type') }}</h3>
                            <p class="text-xs text-slate-500">{{ __('Automatically determine cancellation billing based on timing and circumstances.') }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="checkbox" 
                                   @checked($cancellation['auto_determine'])
                                   wire:change="updateCancellation('auto_determine', $event.target.checked ? 1 : 0)"
                                   class="h-4 w-4 rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                            <label class="text-sm font-semibold text-slate-700">{{ __('Enable auto-determination') }}</label>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <div class="mb-4">
                            <h3 class="text-base font-semibold text-slate-900">{{ __('24-Hour Cancellation Threshold') }}</h3>
                            <p class="text-xs text-slate-500">{{ __('Hours before pickup time to trigger 24-hour cancellation charge.') }}</p>
                        </div>
                        <div>
                            <input type="number" 
                                   step="1" 
                                   min="0"
                                   value="{{ $cancellation['hours_before_pickup_for_24hr_charge'] }}"
                                   wire:change="updateCancellation('hours_before_pickup_for_24hr_charge', $event.target.value)"
                                   class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <!-- Payment Terms Tab -->
        @if($activeTab === 'payment_terms')
            <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
                <header class="mb-6">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Payment Terms') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Configure payment due dates, grace periods, and late fee calculations.') }}</p>
                </header>

                <div class="space-y-6">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <div class="mb-4">
                            <h3 class="text-base font-semibold text-slate-900">{{ __('Due Immediately') }}</h3>
                            <p class="text-xs text-slate-500">{{ __('Invoices are due immediately upon submission.') }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="checkbox" 
                                   @checked($paymentTerms['due_immediately'])
                                   wire:change="updatePaymentTerms('due_immediately', $event.target.checked ? 1 : 0)"
                                   class="h-4 w-4 rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                            <label class="text-sm font-semibold text-slate-700">{{ __('Enable') }}</label>
                        </div>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Grace Period (Days)') }}</label>
                            <input type="number" 
                                   step="1" 
                                   min="0"
                                   value="{{ $paymentTerms['grace_period_days'] }}"
                                   wire:change="updatePaymentTerms('grace_period_days', $event.target.value)"
                                   class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Late Fee Percentage') }}</label>
                            <div class="mt-2 flex items-center gap-2">
                                <input type="number" 
                                       step="0.1" 
                                       min="0"
                                       value="{{ $paymentTerms['late_fee_percentage'] }}"
                                       wire:change="updatePaymentTerms('late_fee_percentage', $event.target.value)"
                                       class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                <span class="text-slate-500">%</span>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Late Fee Period (Days)') }}</label>
                            <input type="number" 
                                   step="1" 
                                   min="0"
                                   value="{{ $paymentTerms['late_fee_period_days'] }}"
                                   wire:change="updatePaymentTerms('late_fee_period_days', $event.target.value)"
                                   class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Payment Terms Text') }}</label>
                        <textarea rows="4"
                                  wire:change="updatePaymentTerms('terms_text', $event.target.value)"
                                  class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">{{ $paymentTerms['terms_text'] }}</textarea>
                    </div>
                </div>
            </section>
        @endif

        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            <p class="font-semibold">{{ __('Note') }}:</p>
            <p class="mt-1">{{ __('Leaving a field blank will revert it to the system default value from the configuration file. Custom values override defaults for this organization only.') }}</p>
        </div>
    </div>
</div>
