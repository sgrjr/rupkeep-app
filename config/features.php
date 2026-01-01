<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Control which features are enabled in the application. Set to true to
    | enable a feature, false to disable it. This allows easy toggling of
    | incomplete or experimental features without code changes.
    |
    */

    /*
    | PDF Invoice Downloads
    | 
    | When enabled, users can download invoices as PDF files. When disabled,
    | all PDF download links and buttons are hidden from the UI.
    |
    | Set to true to enable PDF downloads, false to disable.
    */
    'invoice_pdf_downloads' => env('FEATURE_INVOICE_PDF_DOWNLOADS', false),

];
