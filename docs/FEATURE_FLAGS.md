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

## Dispatch Auto-Capture of Exceptions

**Config Key**: `dispatch.auto_capture.enabled`
**Environment Variable**: `DISPATCH_AUTO_CAPTURE`
**Default**: `false` (disabled)

### Description

When enabled, an uncaught (500-level) application exception automatically opens
a Dispatch **bug** task in `triage`, labeled `source:exception`, so runtime
failures reach the backlog instead of only living in the logs. See
`App\Services\ExceptionCaptureService` (TASK-337).

Behavior and guardrails:

- **Environment gate**: even when the flag is on, capture only runs in the
  environments listed in `config('dispatch.auto_capture.environments')`
  (`['production']` by default) — so a developer's constant local exceptions
  don't spam the board.
- **Dedupe**: a stable signature (exception class + normalized message + top app
  frame) is stored on the task. A recurrence of a *still-open* task appends an
  internal "occurrence #N" comment instead of creating a duplicate. A recurrence
  of an already-resolved (done/declined) task opens a fresh task (regression).
- **Ignore list**: expected client errors are skipped — 404
  (`NotFoundHttpException`, `ModelNotFoundException`), 401
  (`AuthenticationException`), 403 (`AuthorizationException`), 419
  (`TokenMismatchException`), 422 (`ValidationException`), and any
  `HttpException` with status `< 500`.
- **Recursion guard**: a failure inside the capture path is logged and swallowed
  — it never turns one 500 into two.
- **PII**: only method + URL (with sensitive query keys redacted) + user id/role
  + the top stack frames are stored. The request body is never captured.

### How to Enable

Add to production `.env`:

```env
DISPATCH_AUTO_CAPTURE=true
```

Then `php artisan config:clear`. (Production is already in the default
environments list; no other change is needed.)

### Configuration File

Defined in `config/dispatch.php`:

```php
'auto_capture' => [
    'enabled' => env('DISPATCH_AUTO_CAPTURE', false),
    'environments' => ['production'],
    'label' => 'source:exception',
],
```
