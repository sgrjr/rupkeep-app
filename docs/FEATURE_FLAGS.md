# Feature Flags

This document describes the feature flags available in the application and how to enable/disable them.

## Invoice PDF Downloads

**Config Key**: `features.invoice_pdf_downloads`  
**Environment Variable**: `FEATURE_INVOICE_PDF_DOWNLOADS`  
**Default**: `false` (disabled)

### Description

Controls whether users can download invoices as PDF files. When disabled:
- All PDF download buttons and links are hidden from the UI
- Direct access to PDF routes returns a 404 error
- The "Include PDF attachment" option in email forms is hidden

### How to Enable

Add the following to your `.env` file:

```env
FEATURE_INVOICE_PDF_DOWNLOADS=true
```

Then clear your config cache:

```bash
php artisan config:clear
```

### How to Disable

Set the environment variable to `false` or remove it entirely:

```env
FEATURE_INVOICE_PDF_DOWNLOADS=false
```

Or simply remove the line from `.env` (defaults to `false`).

### Locations Affected

- Job show page: PDF download button in invoice list
- Invoice edit page: PDF download button
- Invoice email form: "Include PDF attachment" checkbox
- PDF download route: Returns 404 when disabled

### Configuration File

The feature flag is defined in `config/features.php`:

```php
'invoice_pdf_downloads' => env('FEATURE_INVOICE_PDF_DOWNLOADS', false),
```
