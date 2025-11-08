<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{$invoice->invoice_number}}</title>
    <style>
        :root {
            color-scheme: light;
            font-family: "Segoe UI", Arial, sans-serif;
            --accent: #f9b104;
            --border: #d4d4d4;
            --text: #1f2933;
            --muted: #6b7280;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f8fafc;
            color: var(--text);
        }

        .page {
            max-width: 900px;
            margin: 2rem auto;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.15);
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2rem 2.5rem;
            background: linear-gradient(135deg, #fff7e6 0%, #ffeccc 100%);
            border-bottom: 1px solid var(--border);
        }

        header h1 {
            margin: 0;
            font-size: 1.8rem;
            letter-spacing: 0.04em;
        }

        .meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
            padding: 2rem 2.5rem;
        }

        .meta section {
            padding: 1rem 1.25rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #fff;
        }

        .meta h2 {
            margin: 0 0 0.75rem;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
        }

        .meta p {
            margin: 0.2rem 0;
            line-height: 1.4;
        }

        .details {
            padding: 0 2.5rem 2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1.5rem;
        }

        thead th {
            text-align: left;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            padding-bottom: 0.6rem;
            border-bottom: 1px solid var(--border);
        }

        tbody tr {
            border-bottom: 1px solid var(--border);
        }

        tbody td {
            padding: 0.8rem 0;
            font-size: 0.92rem;
        }

        tbody td:last-child {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .summary {
            margin-top: 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
        }

        .summary section {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
        }

        .summary h3 {
            margin: 0 0 1rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
        }

        .summary p {
            display: flex;
            justify-content: space-between;
            margin: 0.4rem 0;
            font-size: 0.95rem;
        }

        .summary p span:last-child {
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }

        footer {
            margin-top: 2.5rem;
            padding: 1.5rem 2.5rem;
            border-top: 1px solid var(--border);
            font-size: 0.9rem;
            color: var(--muted);
        }

        @media print {
            body {
                background: none;
            }
            .page {
                margin: 0;
                border-radius: 0;
                border: none;
                box-shadow: none;
            }
            header {
                border-bottom: none;
            }
            footer {
                border-top: none;
            }
        }
    </style>
</head>
<body>
    @php
        $values = $values ?? [];
        $billFrom = $values['bill_from'] ?? [];
        $billTo = $values['bill_to'] ?? [];
        $totalDue = number_format((float)($values['total'] ?? 0), 2);
        $billableMiles = $values['billable_miles'] ?? null;
        $rateValue = $values['rate_value'] ?? null;
        $notes = $values['notes'] ?? null;

        $charges = [
            'Billable Miles' => $billableMiles ? number_format((float)$billableMiles, 2) : null,
            'Rate Applied' => $rateValue ? '$' . number_format((float)$rateValue, 2) : null,
            'Deadhead Trips' => $values['dead_head'] ?? null,
            'Tolls' => isset($values['tolls']) ? '$' . number_format((float)$values['tolls'], 2) : null,
            'Hotel' => isset($values['hotel']) ? '$' . number_format((float)$values['hotel'], 2) : null,
            'Extra Charges' => isset($values['extra_charge']) ? '$' . number_format((float)$values['extra_charge'], 2) : null,
            'Extra Load Stops' => $values['extra_load_stops_count'] ?? null,
            'Wait Time (hrs)' => $values['wait_time_hours'] ?? null,
        ];
    @endphp

    <div class="page">
        <header>
            <div>
                <h1>Invoice {{$invoice->invoice_number}}</h1>
                <div class="muted">Issued {{optional($invoice->created_at)->toFormattedDateString()}}</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:1.4rem;font-weight:700;color:var(--accent);">
                    {{$billFrom['company'] ?? config('app.name')}}
                </div>
                @if(!empty($billFrom['street']))
                    <div>{{$billFrom['street']}}</div>
                @endif
                @if(!empty($billFrom['city']) || !empty($billFrom['state']))
                    <div>{{$billFrom['city'] ?? ''}} {{$billFrom['state'] ?? ''}} {{$billFrom['zip'] ?? ''}}</div>
                @endif
            </div>
        </header>

        <div class="meta">
            <section>
                <h2>Bill From</h2>
                <p><strong>{{$billFrom['company'] ?? '—'}}</strong></p>
                @if(!empty($billFrom['attention']))
                    <p>Attn: {{$billFrom['attention']}}</p>
                @endif
                @if(!empty($billFrom['street']))
                    <p>{{$billFrom['street']}}</p>
                @endif
                <p>{{$billFrom['city'] ?? ''}} {{$billFrom['state'] ?? ''}} {{$billFrom['zip'] ?? ''}}</p>
            </section>

            <section>
                <h2>Bill To</h2>
                <p><strong>{{$billTo['company'] ?? optional($invoice->customer)->name ?? '—'}}</strong></p>
                @if(!empty($billTo['attention']))
                    <p>Attn: {{$billTo['attention']}}</p>
                @endif
                @if(!empty($billTo['street']))
                    <p>{{$billTo['street']}}</p>
                @endif
                <p>{{$billTo['city'] ?? ''}} {{$billTo['state'] ?? ''}} {{$billTo['zip'] ?? ''}}</p>
            </section>

            <section>
                <h2>Job Details</h2>
                <p><span class="muted">Job #</span> <strong>{{optional($invoice->job)->job_no ?? '—'}}</strong></p>
                <p><span class="muted">Load #</span> {{optional($invoice->job)->load_no ?? '—'}}</p>
                <p><span class="muted">Pickup</span> {{$values['pickup_address'] ?? optional($invoice->job)->pickup_address ?? '—'}}</p>
                <p><span class="muted">Delivery</span> {{$values['delivery_address'] ?? optional($invoice->job)->delivery_address ?? '—'}}</p>
            </section>
        </div>

        <div class="details">
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($charges as $label => $amount)
                        @continue($amount === null || $amount === '' || $amount === 0 || $amount === '0.00')
                        <tr>
                            <td>{{$label}}</td>
                            <td>{{$amount}}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="summary">
                <section>
                    <h3>Totals</h3>
                    <p>
                        <span>Amount Due</span>
                        <span>${{$totalDue}}</span>
                    </p>
                    <p>
                        <span>Status</span>
                        <span>{{$invoice->paid_in_full ? 'PAID' : 'UNPAID'}}</span>
                    </p>
                </section>

                @if($notes)
                    <section>
                        <h3>Notes</h3>
                        <p style="white-space:pre-wrap;">{{$notes}}</p>
                    </section>
                @endif
            </div>
        </div>

        <footer>
            {{ $values['footer'] ?? 'Thank you for choosing Casco Bay Pilot Car. We appreciate your business.' }}
        </footer>
    </div>
</body>
</html>

