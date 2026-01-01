@php
    $organization = $invoice->organization ?? null;
    $customer = $invoice->customer ?? null;
@endphp

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Invoice #:number', ['number' => $invoice->invoice_number]) }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #172232;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #f9b104;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            max-height: 60px;
            margin-bottom: 15px;
        }
        .preliminary-text {
            background: #fff7e6;
            border-left: 4px solid #f9b104;
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 4px;
        }
        .invoice-preview {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            font-size: 0.9em;
            color: #6b7280;
        }
        a {
            color: #f9b104;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="header">
        @if($organization && $organization->logo)
            <img src="{{ $organization->logo }}" alt="{{ $organization->name }}" class="logo" />
        @endif
        <h1 style="margin: 0; color: #172232;">{{ __('Invoice #:number', ['number' => $invoice->invoice_number]) }}</h1>
        <p style="margin: 5px 0 0; color: #6b7280;">{{ __('Date: :date', ['date' => optional($invoice->created_at)->toFormattedDateString()]) }}</p>
    </div>

    @if($preliminaryText)
        <div class="preliminary-text">
            {!! nl2br(e($preliminaryText)) !!}
        </div>
    @endif

    <div class="invoice-preview" style="max-width: 100%; overflow-x: auto;">
        @include('invoices.templates.render', [
            'invoice' => $invoice,
            'values' => $values,
            'isSummary' => $isSummary ?? false,
        ])
    </div>

    <div class="footer">
        <p>{{ __('This invoice was sent by :company.', ['company' => $organization?->name ?? config('app.name')]) }}</p>
        @if($organization && $organization->email)
            <p>{{ __('Questions? Contact us at :email', ['email' => $organization->email]) }}</p>
        @endif
    </div>
</body>
</html>

