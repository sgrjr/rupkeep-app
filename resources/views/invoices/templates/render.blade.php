@php
    use Illuminate\Support\Number;

    $values = $values ?? (is_array($invoice->values) ? $invoice->values : []);
    $billFrom = $billFrom ?? ($values['bill_from'] ?? []);
    $billTo = $billTo ?? ($values['bill_to'] ?? []);
    $totalDue = $totalDue ?? number_format((float) ($values['total'] ?? 0), 2);
    $billableMiles = $billableMiles ?? ($values['billable_miles'] ?? null);
    $rateValue = $rateValue ?? ($values['rate_value'] ?? null);
    $notes = $notes ?? ($values['notes'] ?? null);
    $isSummary = $isSummary ?? (method_exists($invoice, 'isSummary') ? $invoice->isSummary() : false);
    $summaryItems = $summaryItems ?? ($isSummary ? ($values['summary_items'] ?? []) : []);

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

<div class="page">
    <header>
        <div>
            <h1>{{ __('Invoice #:number', ['number' => $invoice->invoice_number]) }}</h1>
            <div class="muted">{{ __('Issued :date', ['date' => optional($invoice->created_at)->toFormattedDateString()]) }}</div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:1.4rem;font-weight:700;color:var(--invoice-accent);">
                {{ $billFrom['company'] ?? config('app.name') }}
            </div>
            @if(!empty($billFrom['street']))
                <div>{{ $billFrom['street'] }}</div>
            @endif
            @if(!empty($billFrom['city']) || !empty($billFrom['state']))
                <div>{{ $billFrom['city'] ?? '' }} {{ $billFrom['state'] ?? '' }} {{ $billFrom['zip'] ?? '' }}</div>
            @endif
        </div>
    </header>

    <div class="meta">
        <section>
            <h2>{{ __('Bill From') }}</h2>
            <p><strong>{{ $billFrom['company'] ?? '—' }}</strong></p>
            @if(!empty($billFrom['attention']))
                <p>{{ __('Attn: :name', ['name' => $billFrom['attention']]) }}</p>
            @endif
            @if(!empty($billFrom['street']))
                <p>{{ $billFrom['street'] }}</p>
            @endif
            <p>{{ trim(($billFrom['city'] ?? '') . ' ' . ($billFrom['state'] ?? '') . ' ' . ($billFrom['zip'] ?? '')) }}</p>
        </section>

        <section>
            <h2>{{ __('Bill To') }}</h2>
            <p><strong>{{ $billTo['company'] ?? optional($invoice->customer)->name ?? '—' }}</strong></p>
            @if(!empty($billTo['attention']))
                <p>{{ __('Attn: :name', ['name' => $billTo['attention']]) }}</p>
            @endif
            @if(!empty($billTo['street']))
                <p>{{ $billTo['street'] }}</p>
            @endif
            <p>{{ trim(($billTo['city'] ?? '') . ' ' . ($billTo['state'] ?? '') . ' ' . ($billTo['zip'] ?? '')) }}</p>
        </section>

        @if($isSummary)
            <section>
                <h2>{{ __('Summary') }}</h2>
                <p><span class="muted">{{ __('Invoices Included') }}</span> <strong>{{ count($summaryItems) }}</strong></p>
                <p><span class="muted">{{ __('Total Billable Miles') }}</span> {{ $billableMiles ? number_format((float) $billableMiles, 2) : '—' }}</p>
                <p><span class="muted">{{ __('Child Invoice Numbers') }}</span>
                    {{ collect($summaryItems)->pluck('invoice_number')->filter()->join(', ') ?: '—' }}
                </p>
            </section>
        @else
            <section>
                <h2>{{ __('Job Details') }}</h2>
                <p><span class="muted">{{ __('Job #') }}</span> <strong>{{ optional($invoice->job)->job_no ?? '—' }}</strong></p>
                <p><span class="muted">{{ __('Load #') }}</span> {{ optional($invoice->job)->load_no ?? ($values['load_no'] ?? '—') }}</p>
                <p><span class="muted">{{ __('Pickup') }}</span> {{ $values['pickup_address'] ?? optional($invoice->job)->pickup_address ?? '—' }}</p>
                <p><span class="muted">{{ __('Delivery') }}</span> {{ $values['delivery_address'] ?? optional($invoice->job)->delivery_address ?? '—' }}</p>
            </section>
        @endif
    </div>

    <div class="details">
        <table>
            <thead>
                <tr>
                    <th>{{ __('Description') }}</th>
                    <th>{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @if($isSummary)
                    @foreach($summaryItems as $item)
                        <tr>
                            <td>
                                <strong>{{ __('Invoice #:number', ['number' => $item['invoice_number'] ?? '—']) }}</strong>
                                <div style="color:var(--invoice-muted);font-size:0.8rem;">
                                    {{ __('Job') }}: {{ $item['job_no'] ?? '—' }}
                                </div>
                            </td>
                            <td>{{ isset($item['total']) ? '$' . number_format((float) $item['total'], 2) : '—' }}</td>
                        </tr>
                    @endforeach
                @else
                    @foreach($charges as $label => $amount)
                        @continue($amount === null || $amount === '' || $amount === 0 || $amount === '0.00')
                        <tr>
                            <td>{{ $label }}</td>
                            <td>{{ $amount }}</td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>

        <div class="summary">
            <section>
                <h3>{{ __('Totals') }}</h3>
                <p>
                    <span>{{ __('Amount Due') }}</span>
                    <span>${{ $totalDue }}</span>
                </p>
                <p>
                    <span>{{ __('Status') }}</span>
                    <span>{{ $invoice->paid_in_full ? __('PAID') : __('UNPAID') }}</span>
                </p>
            </section>

            @if($notes)
                <section>
                    <h3>{{ __('Notes') }}</h3>
                    <p style="white-space:pre-wrap;">{{ $notes }}</p>
                </section>
            @endif
        </div>
    </div>

    <footer>
        {{ $values['footer'] ?? __('Thank you for choosing Casco Bay Pilot Car. We appreciate your business.') }}
    </footer>
</div>
