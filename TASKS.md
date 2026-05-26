# Rupkeep — Tasks (Master Orchestration)

> **Living document.** This is the single entry point for *all* feature requests, bug fixes, and maintenance work on Rupkeep for the lifetime of the project. New items always start here. Detailed specs/repros live in linked docs under [`docs/`](docs/); this file is the dashboard for **"what is next?"**.

---

## How to use this file

1. **New idea, bug, or request?** → Add it to [Triage](#-triage-incoming) with the next available `TASK-###` ID. Don't worry about prioritization yet.
2. **Triaged item ready to work?** → Move it into the appropriate section (Open Bugs / Feature Backlog / Tech Debt) with a priority badge.
3. **Starting work?** → Move it to [Now / In Progress](#-now--in-progress). Limit yourself to ≤ 3 items here.
4. **Shipped?** → Move to [Recently Shipped](#-recently-shipped) with the date and a PR/commit ref. When that section exceeds ~20 entries, prune oldest to [`docs/archive/COMPLETED.md`](docs/archive/).
5. **Need more detail than a single row?** → Link out to a doc in [`docs/`](docs/) (e.g. `docs/BUGS.md#task-001`, `docs/ROADMAP.md#customer-portal`).

**ID scheme:** `TASK-###` sequential, never reused. Next available ID at the [bottom of this file](#next-id).

**Status legend:** 🔴 blocker · 🟠 high · 🟡 medium · 🟢 low

**Type tags:** `bug` · `feat` · `chore` · `debt` · `verify` (smoke-test a claimed-complete item)

---

## 🔥 Now / In Progress

| ID | Title | Type | Priority | Notes |
|----|-------|------|----------|-------|
| _(none — pick from the lists below)_ | | | | |

---

## 🐛 Open Bugs

| ID | Title | Priority | Where | Detail |
|----|-------|----------|-------|--------|
| TASK-001 | `Class "App\Livewire\User" not found` when loading `/dashboard` | 🟠 high | dashboard | [BUGS.md#task-001](docs/BUGS.md#task-001) |
| TASK-002 | `Unable to create the key` on guest hit to `/` | 🟠 high | landing | [BUGS.md#task-002](docs/BUGS.md#task-002) |
| TASK-003 | Rate values not saving on `PilotCarJob` edit form | 🟠 high | jobs edit | repro: edit a job, set per_mile_rate, save, reload — value reverts. Suspected: form validation drops the field. See `app/Livewire/EditPilotCarJob.php`. |
| TASK-004 | Missing "Show" link on some jobs index views | 🟢 low | jobs index | only Edit button shown in some contexts |
| TASK-005 | Main contact tag missing on customer contacts | 🟡 medium | customers show | DEV_NOTES: need pattern to distinguish "Main Customer Contact" |
| TASK-006 | Push notification listener: VAPID key generation can fail (`Unable to create the key`) | 🟡 medium | notifications | likely tied to TASK-002. Check `app/Listeners/SendJobAssignedNotification.php` push branch and VAPID env vars. |
| TASK-007 | [PROD] WebPush triggers GMP/BCMath PHP warning that converts to a fatal error on landing-page hit | 🔴 blocker | production landing | Same root cause as TASK-002 / TASK-006, observed in prod 2026-02-03 via `/user-events`. Stack ends at `minishlink/web-push/src/Utils.php:86`. Fix: install `php-gmp` (or `php-bcmath`) on production server. See [BUGS.md#task-007](docs/BUGS.md#task-007). |

---

## ✨ Feature Backlog

### Epic: Customer Portal & One-Time Login Codes
Customers do not use traditional email/password; they receive one-time login codes (default 24h expiry, configurable). Once logged in they see their invoice index, view individual invoices, comment on invoices, and flag for attention. See [`docs/ROADMAP.md#customer-portal`](docs/ROADMAP.md#epic-customer-portal--login-codes).

| ID | Title | Priority |
|----|-------|----------|
| TASK-010 | `LoginCode` model + migration (code, user_id, expires_at, used_at) | 🟠 high |
| TASK-011 | `LoginCodeController`: generate, validate, consume | 🟠 high |
| TASK-012 | Config key for code expiry (default 24h) | 🟡 medium |
| TASK-013 | Customer-facing login code entry form | 🟠 high |
| TASK-014 | Customer dashboard (invoice list scoped to customer) | 🟠 high |
| TASK-015 | Customer invoice detail (read-only) | 🟠 high |
| TASK-016 | Invoice commenting (Livewire component, persists per-invoice) | 🟡 medium |
| TASK-017 | "Flag for Attention" button + manager notification | 🟡 medium |
| TASK-018 | Email invitation flow for new customer accounts | 🟡 medium |
| TASK-019 | Proof-materials visibility toggle (staff-only vs public) on attachments | 🟡 medium |

### Epic: PDF Invoice Downloads (feature-flagged, currently OFF)
The print-optimized HTML invoice is the launch deliverable. The PDF library install is a follow-up. See [`docs/FEATURE_FLAGS.md`](docs/FEATURE_FLAGS.md).

| ID | Title | Priority |
|----|-------|----------|
| TASK-020 | Decide on PDF library (dompdf vs browsershot) | 🟢 low |
| TASK-021 | Install library, generate from existing print template | 🟢 low |
| TASK-022 | Queue PDF generation (async, store URL) | 🟢 low |
| TASK-023 | Flip `FEATURE_INVOICE_PDF_DOWNLOADS=true` in production after acceptance test | 🟢 low |

### Epic: Manager Dashboard
| ID | Title | Priority |
|----|-------|----------|
| TASK-030 | Status badges on dashboard job cards (pending/in-progress/completed/invoiced/paid) | 🟡 medium |
| TASK-031 | Summary stats cards (total jobs, revenue, unpaid invoices) | 🟡 medium |
| TASK-032 | Quick filters (unpaid, pending, flagged) | 🟡 medium |
| TASK-033 | Recent activity feed | 🟢 low |
| TASK-034 | Default manager landing → Jobs view (not generic dashboard) | 🟢 low |

### Epic: Vehicle Maintenance UI
The "future feature" note in `resources/views/documentation/onboarding.blade.php:359` is stale — maintenance was implemented. Cleanup + remaining polish below.

| ID | Title | Priority |
|----|-------|----------|
| TASK-040 | Remove "future feature" copy from onboarding docs page (line 359) | 🟢 low |
| TASK-041 | Add maintenance-due notifications/reminders | 🟡 medium |
| TASK-042 | Calendar view for upcoming maintenance | 🟢 low |
| TASK-043 | Vehicle "in garage" boolean + UI surface | 🟡 medium |

### Epic: Notifications
Email is the baseline. SMS gateway addresses work via Brevo; push notifications shipped. Outstanding work focuses on coverage and real SMS API.

| ID | Title | Priority |
|----|-------|----------|
| TASK-050 | InvoiceReadyNotification — wire to invoice creation | 🟠 high |
| TASK-051 | InvoiceFlaggedNotification — wire to customer flag action | 🟡 medium |
| TASK-052 | WelcomeNotification — wire to user account creation | 🟡 medium |
| TASK-053 | Real Brevo SMS API (replace gateway-address-via-email workaround) | 🟢 low |
| TASK-054 | User preference: email / SMS / push / all | 🟡 medium |

### Epic: UI/UX Redesign & Mobile
| ID | Title | Priority |
|----|-------|----------|
| TASK-060 | Audit `#f9b104` orange usage; consolidate to CSS variables | 🟡 medium |
| TASK-061 | Reusable button component library (`.btn-primary`, `.btn-secondary`, `.btn-danger`) | 🟡 medium |
| TASK-062 | Mobile pass: test all views at 320 / 375 / 768 px | 🟡 medium |
| TASK-063 | Sticky save buttons on mobile forms | 🟡 medium |
| TASK-064 | Toast notification system (Alpine.js or library) | 🟡 medium |
| TASK-065 | Improved 404/403 pages w/ branding | 🟢 low |
| TASK-066 | PWA manifest (installable app) | 🟢 low |

### Epic: QuickBooks CSV Export
| ID | Title | Priority |
|----|-------|----------|
| TASK-070 | `QuickBooksExportController` + route | 🟡 medium |
| TASK-071 | Map invoice fields → QuickBooks CSV columns | 🟡 medium |
| TASK-072 | "Export to CSV" button on invoices index | 🟡 medium |
| TASK-073 | Date/currency formatting for QB import | 🟡 medium |

### Epic: Safe Delete & Restore
| ID | Title | Priority |
|----|-------|----------|
| TASK-080 | "Restore" buttons on all soft-deleted records | 🟡 medium |
| TASK-081 | "Show Deleted" filter on index views | 🟡 medium |
| TASK-082 | Confirm-before-delete modal for every destructive action | 🟡 medium |
| TASK-083 | "Permanently Delete" (admin-only) action | 🟢 low |

### Epic: Form & Log Reorganization (DEV_NOTES carry-overs)
Several reorg items from `DEV_NOTES.md` have unclear shipping status — see [Verification Backlog](#-verification-backlog).

| ID | Title | Priority |
|----|-------|----------|
| TASK-090 | Move `LOG MEMO (INTERNAL)` field up into "Driver and Vehicle" section | 🟡 medium |
| TASK-091 | Surface & edit `JOB MEMO (EXTERNAL)` from log edit page (placed in Load Information section) | 🟡 medium |
| TASK-092 | Rename "Mileage Stops" → "Job Details" and add `JOB START TIME` / `JOB END TIME` | 🟡 medium |
| TASK-093 | Move `START DAY`/`END DAY` + `START MILEAGE`/`END MILEAGE` out of "Mileage Stops" | 🟡 medium |

### Epic: Production Deployment
| ID | Title | Priority |
|----|-------|----------|
| TASK-100 | Production MySQL setup + `.env.production` | 🔴 blocker |
| TASK-101 | Queue worker setup (Windows Task Scheduler or supervisor) | 🔴 blocker |
| TASK-102 | SSL certificate + domain | 🔴 blocker |
| TASK-103 | Backup strategy + restore drill | 🟠 high |
| TASK-104 | Data migration from Google Sheets (final export → import) | 🟠 high |
| TASK-105 | User docs / training walkthrough | 🟡 medium |

### Epic: Onboarding Wizard
| ID | Title | Priority |
|----|-------|----------|
| TASK-110 | Build out Step 5 "Preferences" (currently a placeholder per `OnboardingWizard.php:70`) | 🟢 low |

### Epic: Pricing & Billing
The `ManagePricing` Livewire component and `PricingSetting::getValueForOrganization()` already cover most rate / charge / cancellation config. These extend the pricing model.

| ID | Title | Priority |
|----|-------|----------|
| TASK-307 | Allow "mini" charge to stack onto flat-rate jobs (currently treated as alternatives) | 🟡 medium |
| TASK-308 | On job show page: compare flat-rate vs. mini billing and surface whichever is greater | 🟡 medium |
| TASK-314 | Verify cancellation flat-rates (`cancellation_24hr`, `show_no_go`, `cancel_without_billing` per `PilotCarJob::determineCancellationType()`) actually flow into invoice generation | 🟠 high |

### Epic: Job Status & Lifecycle
The `PilotCarJob::getStatusAttribute()` accessor (`ACTIVE`/`CANCELLED`/`CANCELLED_NO_GO`/`COMPLETED` — see `PilotCarJob.php:1996`) is in place. UI surfacing + status-change notifications are not.

| ID | Title | Priority |
|----|-------|----------|
| TASK-310 | Surface job status badge prominently on jobs index + job show | 🟠 high |
| TASK-311 | Fire events on status transitions (active→completed, →cancelled, →no-go) and notify assigned drivers | 🟡 medium |
| TASK-312 | SMS text notification to drivers on status change (rides on TASK-053 real SMS, or existing gateway) | 🟢 low |

### Epic: Job Show Page Polish
| ID | Title | Priority |
|----|-------|----------|
| TASK-309 | Display job miles (calculated + billable) more prominently on job show | 🟡 medium |
| TASK-313 | `tel:` call-driver links on driver / customer-contact / truck-driver phone numbers (job show + log edit) | 🟡 medium |

---

## 🔍 Verification Backlog
Items marked complete in archived docs that should be smoke-tested against current code before launch. If a check passes, move row to [Recently Shipped](#-recently-shipped); if it fails, file as a bug.

| ID | What to verify | Source claim | How |
|----|----------------|--------------|-----|
| TASK-200 | `clock_in` / `clock_out` fields display & save on log edit | DEV_NOTES Impl Summary #7-8 | edit a log, set both, reload |
| TASK-201 | Job log approval workflow (pending/confirmed/denied) | CUSTOMER_INTERVIEW 1.1 | assign log, confirm as manager, deny as driver |
| TASK-202 | Wait time / trailer # / truck # / extra stops / `is_deadhead` all persist | CUSTOMER_INTERVIEW 1.5–1.6 | edit a log, save, reload, inspect DB |
| TASK-203 | Jobs table sort: unpaid first, then `scheduled_pickup_at` asc | CUSTOMER_INTERVIEW 3.1 | seed mixed-status jobs, view index |
| TASK-204 | Mobile layout on jobs index (`job_no`, `scheduled_pickup_at`, `load_no`, pickup_address) | CUSTOMER_INTERVIEW 3.2 | view at 375px |
| TASK-205 | Order summary `summary_items` populated on multi-job invoices | CUSTOMER_INTERVIEW 4.2 | create summary invoice w/ 2+ children |
| TASK-206 | Vehicle name normalization on import (Car 06 / Car 006 / Car 6 → same vehicle) | CUSTOMER_INTERVIEW 5.3 | import CSV with all 3 variants |
| TASK-207 | Auto-create invoices on import (checkbox in dashboard) | CUSTOMER_INTERVIEW 5.2 | import w/ checkbox on |
| TASK-208 | Pricing config overrides actually win over defaults | DEV_NOTES Impl Summary #14 | create org-specific pricing setting, generate invoice |
| TASK-209 | Push notifications deliver end-to-end | DEV_NOTES Impl Summary #9 | assign job to subscribed user, observe push |
| TASK-210 | Customer `/my/customers/{id}` access control hides Transaction Register for non-admin staff | DEV_NOTES Impl Summary #10 | log in as standard employee |
| TASK-211 | `Clear all invoices` super-user action on profile page | DEV_NOTES Impl Summary #12 | run as super user, confirm |
| TASK-212 | Vehicle maintenance: overdue / upcoming / history sections render correctly | CUSTOMER_INTERVIEW 7.1 | seed mixed-date maintenance records |
| TASK-213 | `ManagePricing` UI exposes the full pricing matrix (rates, charges, cancellations, payment terms) | `app/Livewire/ManagePricing.php` exists with `updateRate/Charge/Cancellation/PaymentTerms` | open as super-user, compare to `config/pricing.php` |

---

## 🧹 Tech Debt / Cleanup

| ID | Title | Priority |
|----|-------|----------|
| TASK-301 | Remove or finish onboarding wizard "Preferences coming soon" copy (`resources/views/livewire/onboarding-wizard.blade.php:288`) | 🟢 low |
| TASK-302 | Clean up Brevo OAuth example code in email controller (per `.cursorrules` line 116) | 🟢 low |
| TASK-303 | Add eager loading where N+1 queries exist on jobs index | 🟡 medium |
| TASK-304 | Add DB index on `pilot_car_jobs.scheduled_pickup_at` (sort performance) | 🟡 medium |
| TASK-305 | Unit tests: `tests/Unit/InvoiceCalculationTest.php` (mini charge, deadhead, rate codes) | 🟡 medium |
| TASK-306 | Feature tests: multi-tenant isolation, permission boundaries | 🟡 medium |

---

## 🗂️ Triage (incoming)
New ideas, requests, and reports land here first. Anything in this section is "not yet prioritized" — once it's been looked at, move it into the right epic / bugs / debt section above.

| ID | Title | Captured | Notes |
|----|-------|----------|-------|
| _(empty)_ | | | |

---

## ✅ Recently Shipped
Most recent first. After ~20 entries, prune oldest to `docs/archive/COMPLETED.md`.

| Date | Title |
|------|-------|
| 2026-05-26 | Documentation consolidation: TASKS.md + docs/ structure; old planning docs archived |
| 2026-01-01 | Job status accessor: ACTIVE / CANCELLED / CANCELLED_NO_GO / COMPLETED (`PilotCarJob::getStatusAttribute`) |
| 2026-01-01 | Cancellation type logic + rate codes (`cancellation_24hr`, `show_no_go`, `cancel_without_billing`) via `determineCancellationType()` |
| 2026-01-01 | `ManagePricing` Livewire UI for rates / charges / cancellations / payment terms |
| 2026-01-01 | `public_memo` field added to `pilot_car_jobs` (external memo) |
| 2026-02-03 | Push notifications infrastructure (service worker, VAPID, subscription controller, push on JobAssigned) |
| 2026-02-03 | `clock_in` / `clock_out` datetime fields added to user_logs |
| 2026-01-21 | `jobs_invoices` table renamed → `summary_invoice_jobs` |
| 2026-01-20 | Job log confirmation/denial workflow (approval_status + policies + UI) |
| 2026-01-20 | Form persistence fixes: `wait_time_hours`, `trailer_no`, `truck_no`, `extra_load_stops_count`, `is_deadhead` |
| 2026-01-20 | Billable miles in log edit header (calculated vs override distinction) |
| 2026-01-20 | Job details overview section on log edit page |
| 2026-01-20 | Open All / Close All section toggles on log edit |
| 2026-01-20 | Truck driver / new truck driver moved to Load Information section |
| 2026-01-20 | Jobs table sort: unpaid first, then `scheduled_pickup_at` asc; mobile column priority |
| 2026-01-20 | Sticky save button on jobs edit (mobile) |
| 2026-01-20 | Invoice check-number layout fix + responsive grid |
| 2026-01-20 | Order summary `buildSummaryValues()` fallback values + empty-state messaging |
| 2026-01-20 | Job show page: links to summary + individual child invoices |
| 2026-01-20 | Import logging + skipped-row tracking |
| 2026-01-20 | Auto-create invoices on import (dashboard checkbox) |
| 2026-01-20 | Vehicle name normalization (`Car 06` / `Car 006` / `Car 6` → `Car 006`) |
| 2026-01-20 | Vehicle maintenance UI: overdue / upcoming / history sections |
| 2026-01-16 | Soft deletes on `user_logs` |
| 2026-01-15 | `marked_for_attention` flag on invoices |

For older completed items see [`docs/archive/IMPLEMENTATION_SUMMARY.md`](docs/archive/IMPLEMENTATION_SUMMARY.md) and [`docs/archive/CUSTOMER_INTERVIEW_ACTION_PLAN.md`](docs/archive/CUSTOMER_INTERVIEW_ACTION_PLAN.md).

---

## 📚 References

**Planning / strategy**
- [Roadmap (phases & strategy)](docs/ROADMAP.md)
- [Bug repros & investigation notes](docs/BUGS.md)
- [Feature flags reference](docs/FEATURE_FLAGS.md)

**Operations**
- [Deployment notes / utilities](docs/DEPLOYMENT.md)
- [Testing notifications](docs/TESTING_NOTIFICATIONS.md)
- [SMS gateway troubleshooting](docs/SMS_GATEWAY_TROUBLESHOOTING.md)
- [Fix PHP warnings on remote server](docs/FIX_PHP_WARNINGS.md)
- [Linux server setup](docs/SETUP_LINUX_SERVER.md)
- [Remote git setup](docs/SETUP_REMOTE_GIT.md)

**Historical context**
- [Archived planning docs](docs/archive/) — CUSTOMER_INTERVIEW_ACTION_PLAN, IMPLEMENTATION_SUMMARY, DAILY_SCHEDULE, SHIPPING_PLAN, DEV_NOTES, cursor-questions-and-answers

---

<a id="next-id"></a>
**Next available ID:** `TASK-316`
