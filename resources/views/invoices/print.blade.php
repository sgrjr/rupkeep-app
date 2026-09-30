<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Invoice #:number', ['number' => $invoice->invoice_number]) }}</title>
    @include('invoices.templates.styles')
</head>
{{-- $forPdf is set only by the dompdf route. The browser preview adds
     --screen, which restores a letter-width page; see styles.blade.php. --}}
<body class="invoice-doc invoice-doc--print @unless($forPdf ?? false) invoice-doc--screen @endunless">
    @php
        $values = is_array($values ?? $invoice->values) ? ($values ?? $invoice->values) : [];
    @endphp

    {{-- Internal log memos appear only on the staff browser preview (the route
         is staff-gated and the block is .no-print). Never on the PDF, which
         goes to the customer (TASK-444). --}}
    @include('invoices.templates.render', [
        'invoice' => $invoice,
        'values' => $values,
        'showInternalMemos' => ! ($forPdf ?? false) && auth()->check() && ! auth()->user()->isCustomer(),
    ])
</body>
</html>

