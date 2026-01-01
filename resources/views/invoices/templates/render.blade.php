@php
    use Illuminate\Support\Number;

    $values = $values ?? (is_array($invoice->values) ? $invoice->values : []);
    $billFrom = $billFrom ?? ($values['bill_from'] ?? []);
    $billTo = $billTo ?? ($values['bill_to'] ?? []);
    $totalDue = $totalDue ?? number_format((float) ($values['total'] ?? 0), 2);
    $billableMiles = $billableMiles ?? ($values['billable_miles'] ?? null);
    $rateValue = $rateValue ?? ($values['rate_value'] ?? null);
    // Use override if set, otherwise job-level public memo, otherwise empty
    $notes = $notes ?? ($values['notes'] ?? ($invoice->job->public_memo ?? null));
    $isSummary = $isSummary ?? (method_exists($invoice, 'isSummary') ? $invoice->isSummary() : false);
    $summaryItems = $summaryItems ?? ($isSummary ? ($values['summary_items'] ?? []) : []);

    // Get organization for logo
    $organization = $invoice->organization ?? (auth()->check() ? auth()->user()->organization : null);
    $logoUrl = $organization?->logo ?? null;

    // Build line items for single invoice (Description, Quantity, Rate, Amount format)
    $lineItems = [];
    if (!$isSummary) {
        // Calculate base service amount (total minus all other charges)
        $waitTimeAmount = isset($values['cost_of_wait_time']) ? (float) $values['cost_of_wait_time'] : 0;
        $extraStopsAmount = isset($values['cost_of_extra_stop']) && isset($values['extra_load_stops_count'])
            ? (float) ($values['extra_load_stops_count'] * $values['cost_of_extra_stop'])
            : 0;
        $deadAmount = isset($values['dead_head_charge']) ? (float) $values['dead_head_charge'] : 0;
        $tollsAmount = isset($values['tolls']) ? (float) $values['tolls'] : 0;
        $totalMileageAmount = isset($values['cost_for_mileage']) ? (float) $values['cost_for_mileage'] : 0;
        
        $otherChargesTotal = $waitTimeAmount + $extraStopsAmount + $deadAmount + $tollsAmount + $totalMileageAmount;
        $pilotCarServiceAmount = (float) ($values['total'] ?? 0) - $otherChargesTotal;
        
        // Pilot Car Service - main service charge (always show)
        $pilotCarServiceRate = $pilotCarServiceAmount;
        $pilotCarServiceQty = 1;
        $lineItems[] = [
            'description' => __('Pilot Car Service'),
            'quantity' => $pilotCarServiceQty,
            'rate' => $pilotCarServiceRate,
            'amount' => $pilotCarServiceAmount,
        ];

        // Wait Time (always show, even if 0)
        $waitTimeQty = isset($values['wait_time_hours']) ? (float) $values['wait_time_hours'] : 0;
        $waitTimeRate = $waitTimeQty > 0 ? ($waitTimeAmount / $waitTimeQty) : 0;
        $lineItems[] = [
            'description' => __('Wait Time'),
            'quantity' => $waitTimeQty,
            'rate' => $waitTimeRate,
            'amount' => $waitTimeAmount,
        ];

        // Extra Stops (always show, even if 0)
        $extraStopsQty = isset($values['extra_load_stops_count']) ? (float) $values['extra_load_stops_count'] : 0;
        $extraStopsRate = $extraStopsQty > 0 ? ($extraStopsAmount / $extraStopsQty) : 0;
        $lineItems[] = [
            'description' => __('Extra Stops'),
            'quantity' => $extraStopsQty,
            'rate' => $extraStopsRate,
            'amount' => $extraStopsAmount,
        ];

        // Dead (Deadhead) (always show, even if 0)
        $deadQty = isset($values['dead_head']) ? (float) $values['dead_head'] : 0;
        $deadRate = $deadQty > 0 ? ($deadAmount / $deadQty) : 0;
        $lineItems[] = [
            'description' => __('Dead'),
            'quantity' => $deadQty,
            'rate' => $deadRate,
            'amount' => $deadAmount,
        ];

        // Tolls (always show, even if 0)
        $tollsQty = $tollsAmount > 0 ? 1 : 0;
        $tollsRate = $tollsAmount;
        $lineItems[] = [
            'description' => __('Tolls'),
            'quantity' => $tollsQty,
            'rate' => $tollsRate,
            'amount' => $tollsAmount,
        ];

        // Total Mileage (always show, even if 0)
        $totalMileageQty = isset($values['billable_miles']) ? (float) $values['billable_miles'] : 0;
        $totalMileageRate = $totalMileageQty > 0 ? ($totalMileageAmount / $totalMileageQty) : 0;
        $lineItems[] = [
            'description' => __('Total Mileage'),
            'quantity' => $totalMileageQty,
            'rate' => $totalMileageRate,
            'amount' => $totalMileageAmount,
        ];
    }

    $charges = $charges ?? [
        __('Billable Miles') => $billableMiles ? number_format((float) $billableMiles, 2) : null,
        __('Rate Applied') => $rateValue ? '$' . number_format((float) $rateValue, 2) : null,
        __('Deadhead Trips') => $values['dead_head'] ?? null,
        __('Tolls') => isset($values['tolls']) ? '$' . number_format((float) $values['tolls'], 2) : null,
        __('Hotel') => isset($values['hotel']) ? '$' . number_format((float) $values['hotel'], 2) : null,
        __('Extra Charges') => isset($values['extra_charge']) ? '$' . number_format((float) $values['extra_charge'], 2) : null,
        __('Extra Load Stops') => $values['extra_load_stops_count'] ?? null,
        __('Wait Time (hrs)') => $values['wait_time_hours'] ?? null,
    ];
@endphp

@if($invoice->job && auth()->check() && auth()->user()->organization_id)
    @php
        $logMemos = $invoice->job->logs()->whereNotNull('memo')->where('memo', '!=', '')->get();
    @endphp
    @if($logMemos->count() > 0)
        <div class="no-print" style="max-width: 920px; margin: 2rem auto; padding: 1.5rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
            <h3 style="margin: 0 0 1rem; font-size: 1rem; font-weight: 700; color: #172232;">{{ __('Internal Log Memos (Not Printed on Invoice)') }}</h3>
            <p style="margin: 0 0 1rem; font-size: 0.85rem; color: #6b7280;">{{ __('Internal memos - not printed on invoice. To include information on the invoice, add it to the job-level public memo.') }}</p>
            <div style="space-y: 0.75rem;">
                @foreach($logMemos as $log)
                    <div style="margin-bottom: 0.75rem; padding: 1rem; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; font-size: 0.8rem; color: #6b7280;">
                            <span style="font-weight: 600;">{{ $log->started_at ? \Carbon\Carbon::parse($log->started_at)->format('M j, Y') : '—' }}</span>
                            @if($log->user)
                                <span>•</span>
                                <span>{{ $log->user->name }}</span>
                            @endif
                        </div>
                        <p style="margin: 0; font-size: 0.9rem; color: #172232; white-space: pre-wrap;">{{ $log->memo }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endif

<div class="page">
    <header>
        <div class="header-left">
            @if($logoUrl)
                <div class="invoice-logo">
                    <img src="{{ $logoUrl }}" alt="{{ $billFrom['company'] ?? $organization?->name ?? config('app.name') }}" />
                </div>
            @endif
            <div class="invoice-title">
                @if($isSummary)
                    {{-- Summary invoice: Organization info on left --}}
                    <div class="organization-info">
                        <div class="organization-name">{{ $billFrom['company'] ?? $organization?->name ?? config('app.name') }}</div>
                        @if(!empty($billFrom['attention']))
                            <div class="organization-contact">{{ $billFrom['attention'] }}</div>
                        @endif
                        @if(!empty($billFrom['street']))
                            <div class="organization-address">{{ $billFrom['street'] }}</div>
                        @endif
                        @if(!empty($billFrom['city']) || !empty($billFrom['state']))
                            <div class="organization-address">{{ trim(($billFrom['city'] ?? '') . ', ' . ($billFrom['state'] ?? '') . ' ' . ($billFrom['zip'] ?? '')) }}</div>
                        @endif
                    </div>
                @else
                    {{-- Single invoice: Company name and tagline --}}
                    <h1 class="company-name-large">{{ $billFrom['company'] ?? $organization?->name ?? config('app.name') }}</h1>
                    <p class="company-tagline">{{ __('We\'re with you all the way') }}</p>
                    <div class="company-info">
                        @if(!empty($billFrom['attention']))
                            <div>{{ $billFrom['attention'] }}</div>
                        @endif
                        @if(!empty($billFrom['street']))
                            <div>{{ $billFrom['street'] }}</div>
                        @endif
                        @if(!empty($billFrom['city']) || !empty($billFrom['state']))
                            <div>{{ trim(($billFrom['city'] ?? '') . ', ' . ($billFrom['state'] ?? '') . ' ' . ($billFrom['zip'] ?? '')) }}</div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
        <div class="header-right">
            @if($isSummary)
                {{-- Summary invoice: Title and complete Bill To on right --}}
                <h1 class="invoice-summary-title">{{ __('INVOICE SUMMARY') }}</h1>
                <div class="summary-id">{{ __('Summary ID: :id', ['id' => $invoice->invoice_number]) }}</div>
                <div class="bill-to-section">
                    <h2 class="bill-to-label">{{ __('Bill To:') }}</h2>
                    <div class="bill-to-info">
                        <div class="bill-to-company">{{ $billTo['company'] ?? optional($invoice->customer)->name ?? '—' }}</div>
                        @if(!empty($billTo['attention']))
                            <div class="bill-to-contact">{{ $billTo['attention'] }}</div>
                        @endif
                        @if(!empty($billTo['street']))
                            <div class="bill-to-address">{{ $billTo['street'] }}</div>
                        @endif
                        @if(!empty($billTo['city']) || !empty($billTo['state']))
                            <div class="bill-to-address">{{ trim(($billTo['city'] ?? '') . ', ' . ($billTo['state'] ?? '') . ' ' . ($billTo['zip'] ?? '')) }}</div>
                        @endif
                        @if(!empty($billTo['phone']))
                            <div class="bill-to-contact">{{ $billTo['phone'] }}</div>
                        @endif
                        @if(!empty($billTo['email']))
                            <div class="bill-to-contact">{{ $billTo['email'] }}</div>
                        @endif
                    </div>
                </div>
            @else
                {{-- Single invoice: Title, invoice number, date, and Bill To --}}
                <h1 class="invoice-title-right">{{ __('INVOICE') }}</h1>
                <div class="invoice-number">{{ __('Invoice #:number', ['number' => $invoice->invoice_number]) }}</div>
                <div class="invoice-date">{{ __('Date: :date', ['date' => optional($invoice->created_at)->format('m/d/Y')]) }}</div>
                <div class="bill-to-section">
                    <h2 class="bill-to-label">{{ __('Bill To:') }}</h2>
                    <div class="bill-to-info">
                        <div class="bill-to-company">{{ $billTo['company'] ?? optional($invoice->customer)->name ?? '—' }}</div>
                        @if(!empty($billTo['attention']))
                            <div class="bill-to-contact">{{ $billTo['attention'] }}</div>
                        @endif
                        @if(!empty($billTo['street']))
                            <div class="bill-to-address">{{ $billTo['street'] }}</div>
                        @endif
                        @if(!empty($billTo['city']) || !empty($billTo['state']))
                            <div class="bill-to-address">{{ trim(($billTo['city'] ?? '') . ', ' . ($billTo['state'] ?? '') . ' ' . ($billTo['zip'] ?? '')) }}</div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </header>


    <div class="details">
        <table>
            <thead>
                <tr>
                    @if($isSummary)
                        <th>{{ __('Date Of Service') }}</th>
                        <th>{{ __('Invoice #') }}</th>
                        <th>{{ __('Description of Work') }}</th>
                        <th>{{ __('Job #') }}</th>
                        <th>{{ __('Load #.') }}</th>
                        <th>{{ __('Amount Due') }}</th>
                    @else
                        <th>{{ __('Description') }}</th>
                        <th>{{ __('Quantity') }}</th>
                        <th>{{ __('Rate') }}</th>
                        <th>{{ __('Amount') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @if($isSummary)
                    @foreach($summaryItems as $item)
                        <tr>
                            <td>{{ isset($item['date_of_service']) ? \Carbon\Carbon::parse($item['date_of_service'])->format('m/d/Y') : (isset($item['created_at']) ? \Carbon\Carbon::parse($item['created_at'])->format('m/d/Y') : '—') }}</td>
                            <td>{{ $item['invoice_number'] ?? '—' }}</td>
                            <td>{{ $item['description'] ?? \App\Models\Invoice::generateDescriptionOfWork($item['pickup_address'] ?? null, $item['delivery_address'] ?? null) }}</td>
                            <td>{{ $item['job_no'] ?? '—' }}</td>
                            <td>{{ $item['load_no'] ?? '—' }}</td>
                            <td class="amount-due">{{ isset($item['total']) ? '$' . number_format((float) $item['total'], 2) : '—' }}</td>
                        </tr>
                    @endforeach
                    {{-- Total Due row as the last row in tbody for summary invoices --}}
                    <tr class="total-due-row">
                        <td colspan="5" class="total-due-label">{{ __('Total Due') }}</td>
                        <td class="total-due-amount">${{ $totalDue }}</td>
                    </tr>
                @else
                    @foreach($lineItems as $item)
                        <tr>
                            <td>{{ $item['description'] }}</td>
                            <td class="text-right">{{ number_format($item['quantity'], 0) }}</td>
                            <td class="text-right">${{ number_format($item['rate'], 2) }}</td>
                            <td class="text-right">${{ number_format($item['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                    {{-- Total Due row as the last row in tbody for single invoices --}}
                    <tr class="total-due-row">
                        <td colspan="3" class="total-due-label">{{ __('Total Due') }}</td>
                        <td class="total-due-amount">${{ $totalDue }}</td>
                    </tr>
                @endif
            </tbody>
        </table>

        @if(!$isSummary)
        <div class="summary">
            <section>
                <h3>{{ __('Summary') }}</h3>
                @php
                    $lateFees = method_exists($invoice, 'calculateLateFees') ? $invoice->calculateLateFees() : [
                        'is_past_due' => false,
                        'days_overdue' => 0,
                        'late_fee_periods' => 0,
                        'late_fee_amount' => 0.0,
                        'total_with_late_fees' => (float) $totalDue,
                        'due_date' => $invoice->created_at->copy()->addDays(30),
                    ];
                @endphp
                <p>
                    <span>{{ __('Subtotal') }}</span>
                    <span>${{ $totalDue }}</span>
                </p>
                @if($lateFees['is_past_due'] && $lateFees['late_fee_amount'] > 0)
                    <p>
                        <span>{{ __('Late Fee') }} ({{ $lateFees['late_fee_periods'] }} {{ trans_choice('period|periods', $lateFees['late_fee_periods']) }})</span>
                        <span style="color: #dc2626;">${{ number_format($lateFees['late_fee_amount'], 2) }}</span>
                    </p>
                @endif
                <p>
                    <span>{{ __('Status') }}</span>
                    <span>
                        @if($invoice->paid_in_full)
                            <strong style="color: #059669;">{{ __('PAID') }}</strong>
                        @elseif($lateFees['is_past_due'])
                            <strong style="color: #dc2626;">{{ __('PAST DUE') }}</strong>
                        @else
                            {{ __('UNPAID') }}
                        @endif
                    </span>
                </p>
                <p>
                    <span>{{ __('Due Date') }}</span>
                    <span>{{ $lateFees['due_date']->format('M j, Y') }}</span>
                </p>
                <p>
                    <span>{{ __('Total Due') }}</span>
                    <span style="font-weight: 700;">${{ number_format($lateFees['total_with_late_fees'], 2) }}</span>
                </p>
            </section>

            @if($notes)
                <section>
                    <h3>{{ __('Notes') }}</h3>
                    <p style="white-space:pre-wrap;">{{ $notes }}</p>
                </section>
            @endif
        </div>
        @endif
    </div>

    <footer>
        @php
            $orgName = $billFrom['company'] ?? $organization?->name ?? 'Casco Bay Pilot Car';
            $orgPhone = $organization?->telephone ?? '207-712-8064';
            $footerText = $values['footer'] ?? __(':company would like to thank you for your service, Thank you! If you have any questions or concerns feel free to contact me at :phone', [
                'company' => $orgName,
                'phone' => $orgPhone
            ]);
        @endphp
        <p>{{ $footerText }}</p>
        @php
            $paymentTerms = config('pricing.payment_terms.terms_text', '');
        @endphp
        @if($paymentTerms && !$invoice->paid_in_full)
            <p style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--invoice-border); font-size: 0.85rem; color: var(--invoice-muted);">
                <strong>{{ __('Payment Terms') }}:</strong> {{ $paymentTerms }}
            </p>
        @endif
    </footer>
</div>
