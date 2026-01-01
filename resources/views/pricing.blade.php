<x-public-layout title="Pricing - Casco Bay Pilot Car">
    <div class="bg-black text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h1 class="text-4xl md:text-5xl font-bold mb-4">Pricing & Policies</h1>
                <p class="text-xl text-gray-300">Transparent pricing for pilot car services</p>
            </div>

            <!-- Rates Section -->
            <div class="mb-16">
                <h2 class="text-3xl font-bold mb-8 text-center border-b border-gray-700 pb-4">Service Rates</h2>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @php
                        $perMileRates = array_filter($rates, fn($rate) => $rate['type'] === 'per_mile');
                        $flatRates = array_filter($rates, fn($rate) => $rate['type'] === 'flat');
                    @endphp

                    @foreach($perMileRates as $code => $rate)
                        <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
                            <h3 class="text-xl font-semibold mb-2">{{ $rate['name'] }}</h3>
                            @if(!empty($rate['description']))
                                <p class="text-gray-400 mb-4">{{ $rate['description'] }}</p>
                            @endif
                            @if(!empty($rate['rate_per_mile']))
                                <p class="text-2xl font-bold text-[#FF2D20]">${{ number_format($rate['rate_per_mile'], 2) }} <span class="text-base text-gray-400">per mile</span></p>
                            @endif
                        </div>
                    @endforeach

                    @foreach($flatRates as $code => $rate)
                        <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
                            <h3 class="text-xl font-semibold mb-2">@if($code === 'cancel_without_billing')Cancellation 24+ Hours*@else{{ $rate['name'] }}@endif</h3>
                            @if($code === 'cancel_without_billing')
                                <p class="text-gray-400 mb-4">Cancellations made more than {{ $cancellation['hours_before_pickup_for_24hr_charge'] }} hours before the scheduled pickup are free of charge. Additionally, weather-related cancellations and other extenuating circumstances may be eligible for cancellation without billing, subject to approval.</p>
                            @elseif(!empty($rate['description']))
                                <p class="text-gray-400 mb-4">{{ $rate['description'] }}</p>
                            @endif
                            @if(!empty($rate['flat_amount']))
                                <p class="text-2xl font-bold text-[#FF2D20]">${{ number_format($rate['flat_amount'], 2) }}</p>
                            @endif
                            @if(!empty($rate['max_miles']))
                                <p class="text-sm text-gray-400 mt-2">Up to {{ $rate['max_miles'] }} miles</p>
                            @endif
                            @if(!empty($rate['max_hours']))
                                <p class="text-sm text-gray-400 mt-2">Up to {{ $rate['max_hours'] }} hours</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Additional Charges Section -->
            <div class="mb-16">
                <h2 class="text-3xl font-bold mb-8 text-center border-b border-gray-700 pb-4">Additional Charges</h2>
                <div class="grid md:grid-cols-2 gap-6">
                    @foreach($charges as $key => $charge)
                        <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
                            <h3 class="text-xl font-semibold mb-2">{{ $charge['name'] }}</h3>
                            @if(!empty($charge['description']))
                                <p class="text-gray-400 mb-4">{{ $charge['description'] }}</p>
                            @endif
                            <div class="space-y-2">
                                @if(!empty($charge['rate_per_hour']))
                                    <p class="text-lg"><span class="text-[#FF2D20] font-semibold">${{ number_format($charge['rate_per_hour'], 2) }}</span> <span class="text-gray-400">per hour</span></p>
                                    @if(!empty($charge['minimum_hours']))
                                        <p class="text-sm text-gray-400">Minimum: {{ $charge['minimum_hours'] }} hour(s)</p>
                                    @endif
                                @endif
                                @if(!empty($charge['rate_per_stop']))
                                    <p class="text-lg"><span class="text-[#FF2D20] font-semibold">${{ number_format($charge['rate_per_stop'], 2) }}</span> <span class="text-gray-400">per stop</span></p>
                                @endif
                                @if(!empty($charge['rate_per_mile']))
                                    <p class="text-lg"><span class="text-[#FF2D20] font-semibold">${{ number_format($charge['rate_per_mile'], 2) }}</span> <span class="text-gray-400">per mile</span></p>
                                    @if(!empty($charge['free_miles']))
                                        <p class="text-sm text-gray-400">First {{ $charge['free_miles'] }} miles free</p>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Cancellation Policy Section -->
            <div class="mb-16">
                <h2 class="text-3xl font-bold mb-8 text-center border-b border-gray-700 pb-4">Cancellation Policy</h2>
                <div class="bg-gray-900 rounded-lg p-8 border border-gray-800 max-w-4xl mx-auto">
                    <p class="text-lg mb-4">
                        Cancellations must be made more than <strong class="text-[#FF2D20]">{{ $cancellation['hours_before_pickup_for_24hr_charge'] }} hours</strong> before the scheduled pickup time to avoid cancellation fees. Cancellations made within {{ $cancellation['hours_before_pickup_for_24hr_charge'] }} hours of the scheduled pickup will be subject to a cancellation fee, unless there are extenuating circumstances such as weather-related conditions or other unforeseen situations that may be given consideration.
                    </p>
                    <div class="mt-6 space-y-4">
                        <div class="flex items-start">
                            <span class="text-[#FF2D20] mr-3">•</span>
                            <p><strong>Cancellation within {{ $cancellation['hours_before_pickup_for_24hr_charge'] }} hours:</strong> ${{ number_format($rates['cancellation_24hr']['flat_amount'] ?? 150, 2) }}</p>
                        </div>
                        <div class="flex items-start">
                            <span class="text-[#FF2D20] mr-3">•</span>
                            <p><strong>Show but No-Go:</strong> ${{ number_format($rates['show_no_go']['flat_amount'] ?? 225, 2) }}</p>
                        </div>
                        <div class="flex items-start">
                            <span class="text-[#FF2D20] mr-3">•</span>
                            <p><strong>Extenuating Circumstances:</strong> Weather-related cancellations and other extenuating circumstances may be eligible for cancellation without billing, subject to approval.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Terms Section -->
            <div class="mb-16">
                <h2 class="text-3xl font-bold mb-8 text-center border-b border-gray-700 pb-4">Payment Terms</h2>
                <div class="bg-gray-900 rounded-lg p-8 border border-gray-800 max-w-4xl mx-auto">
                    @if(!empty($payment_terms['terms_text']))
                        <p class="text-lg mb-6">{{ $payment_terms['terms_text'] }}</p>
                    @else
                        <div class="space-y-4 text-lg">
                            <p>Payment is {{ $payment_terms['due_immediately'] ? 'due upon submission' : 'due within ' . $payment_terms['grace_period_days'] . ' days' }} of invoices.</p>
                            <p>Invoices will be considered past due after <strong class="text-[#FF2D20]">{{ $payment_terms['grace_period_days'] }} days</strong> from the date of the invoice.</p>
                            <p><strong class="text-[#FF2D20]">{{ number_format($payment_terms['late_fee_percentage'], 1) }}%</strong> interest will be charged every <strong class="text-[#FF2D20]">{{ $payment_terms['late_fee_period_days'] }} days</strong> on past due invoices.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Contact Section -->
            <div class="text-center mb-8">
                <p class="text-xl mb-4">Questions about pricing?</p>
                <p class="text-gray-400">Contact us at <a href="mailto:cascobaypc@gmail.com" class="text-[#FF2D20] hover:underline">cascobaypc@gmail.com</a> or <a href="tel:207-712-8064" class="text-[#FF2D20] hover:underline">207-712-8064</a></p>
            </div>
        </div>
    </div>
</x-public-layout>
