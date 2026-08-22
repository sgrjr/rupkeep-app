# Rupkeep — Roadmap

Strategic / phased view of the work. Task IDs link back to [`TASKS.md`](../TASKS.md), which is the source of truth for status. This doc explains **why** and **what order**; `TASKS.md` tracks **what's next**.

> Older planning artifacts (the original `SHIPPING_PLAN.md` w/ Nov 2025 ship target, `DAILY_SCHEDULE.md`, `CUSTOMER_INTERVIEW_ACTION_PLAN.md`, `IMPLEMENTATION_SUMMARY.md`) live in [`docs/archive/`](archive/). They reflect the state and decisions at the time and are preserved for context.

---

## Product context

Rupkeep is a Laravel 11 + Livewire 3 web app that replaces Google-Sheets workflow for a pilot-car / escort-service business (Casco Bay Pilot Car). Core flow:

1. CSV import from Google Sheets → normalized tables
2. Jobs created → Logs filled by drivers (mobile UI) → Invoices generated
3. Invoice calc: mileage rates, deadhead, mini charges, expenses (hotel, tolls, wait time)

Multi-tenant by `Organization`. Roles: `GUEST`, `EMPLOYEE:STANDARD`, `EMPLOYEE:MANAGER`, `CUSTOMER`, `ADMIN/SUPER_USER`.

Primary color: `#f9b104` orange (on-brand construction theme).

---

## Phase status (high level)

| Phase | Focus | State |
|-------|-------|-------|
| Phase 0 — Core CRUD | Auth, jobs, logs, invoice math | ✅ shipped |
| Phase 1 — Customer-interview fixes | Form persistence, sort, mobile, import normalization, approval workflow, push notifications | ✅ shipped (Jan–Feb 2026) — see [Verification Backlog](../TASKS.md#-verification-backlog) for items still worth smoke-testing |
| Phase 2 — Customer Portal | Login codes, customer-facing invoice index, comments, flagging, public-attachment toggle | 🟠 in progress / not started |
| Phase 3 — Production launch | DB, queue worker, SSL, data migration, training | 🔴 pending |
| Phase 4 — Polish & extensions | UI redesign, QuickBooks export, real SMS API, PDF library, dashboard stats | 🟡 backlog |

---

## Epic: Customer Portal & Login Codes

**Decision (Oct 2025):** Customers do *not* use traditional email/password. They request a one-time code, then access their invoice index and detail views.

**Amended (TASK-319, Aug 2026):** Passwordless sign-in is no longer customer-only and no longer code-only. One request form at `/login-code` is open to **every** role and issues a single `login_codes` row carrying two secrets — a high-entropy `link_token` for the one-click link emailed to the user, and the short `code` they can type instead if the link is mangled in transit. Both share one `used_at`, so redeeming either retires the other. Expiry is **2 hours** for both (was 24h), and requests are limited to **3 per email per hour**. Password login is unchanged and remains the primary path; this is an alternative, not a replacement.

The emailed link redeems in two steps: `GET /login-link/{token}` only renders a "Sign me in" button naming the account, and the `POST` behind that button is what actually spends the token. This is deliberate — corporate mail scanners follow every URL in an email, and a GET that logged the user straight in would let a scanner burn a single-use token before the human ever clicked.

**Permissions:** Whitelist-based. A customer can:
- View their own organization's invoices (index + detail)
- Comment on any of their invoices
- Flag invoices for attention
- See only "public" proof materials (logs, attachments marked public by staff)

**Open work:** TASK-010 through TASK-019.

**Architectural note:** Unified `User` table (no separate `Customer` auth table). The `Customer` model represents the *business* entity; a customer-role `User` belongs to it. Login codes are issued against the `User`.

---

## Epic: PDF Invoice Downloads (deferred, feature-flagged)

The MVP ships with **print-optimized HTML** invoices (browser print), not generated PDFs. The PDF library install is a follow-up.

- Feature flag `FEATURE_INVOICE_PDF_DOWNLOADS` defaults to `false`
- All PDF UI is gated behind the flag (see [`docs/FEATURE_FLAGS.md`](FEATURE_FLAGS.md))
- Backlog: TASK-020 → TASK-023

---

## Epic: Notifications

Email is the baseline (Brevo via `getbrevo/brevo-php`). SMS works today via gateway-address-as-email (e.g. `2074168659@mms.uscc.net`). Push notifications shipped (service worker + VAPID + subscription controller) in Feb 2026.

**Pattern:** Laravel Events + Notifications. Existing events: `JobAssigned`, `JobWasCanceled`, job-uncancel. Listeners dispatch to email + (where subscribed) push.

**Outstanding:**
- `InvoiceReady`, `InvoiceFlagged`, `Welcome` notifications not yet wired
- Real Brevo SMS API integration (replace gateway workaround) — low priority
- Per-user notification preferences (email / SMS / push)

See TASK-050 → TASK-054.

---

## Epic: Vehicle Maintenance

Shipped: separated **Overdue** / **Upcoming** / **History** sections on the vehicles view; oil change & inspection dates tracked. Outstanding polish in TASK-040 → TASK-043 (reminders, calendar view, "in garage" boolean).

Note: the onboarding documentation page still has a "future feature" callout (`resources/views/documentation/onboarding.blade.php:359`) — that copy is stale; see TASK-040.

---

## Epic: UI/UX Redesign & Mobile

Design direction (locked in Oct 2025):
- Keep `#f9b104` orange theme; bright construction aesthetic
- Inspiration: singleparentproject.org (orange-as-accent, not overdone)
- Mobile-first for driver log entry forms

Outstanding work in TASK-060 → TASK-066.

---

## Epic: Production Deployment

Hardest unscheduled work. Blockers on launch:
- TASK-100 MySQL production DB
- TASK-101 Queue worker (notifications + future PDF generation depend on it)
- TASK-102 SSL + domain
- TASK-103 Backup + restore drill
- TASK-104 Final data migration from Google Sheets
- TASK-105 User docs / training walkthrough

Dev environment is Windows IIS at `C:\inetpub\wwwroot\rupkeep-app`. Production target was originally Nov 21, 2025; revised target TBD — track here when set.

---

## Glossary
- **Pilot Car** — escort vehicle for oversized loads
- **Load** — the oversized cargo being escorted
- **Job** — a pilot-car assignment (may span multiple days/logs)
- **Log** — a single driver's work record for a job (daily entry)
- **Deadhead** — empty return trip (flat $250 charge)
- **Mini** — short job ≤125 billable miles (flat $250 charge)
- **Billable Miles** — miles charged to customer (job start → job end)
- **Non-billable Miles** — personal vehicle miles (home → job start, etc.)
- **Rate Code** — pricing model (`per_mile_rate`, `flat_rate`, etc.)
- **Charge** (standing) — a named rate on the published price list, sibling of Dead Head Miles and Extra Stop: applies to any job, quoted publicly at `/pricing`, edited at `/my/pricing`. Seeded from `config/pricing.php`; an org can add its own (TASK-377), stored as `charges.{key}.{field}` PricingSetting rows indexed by a `charges.custom` registry row. Display-only — invoice math reads `charges.wait_time.*` and `charges.extra_stop.*` by name and nothing else.
- **Extra Charge** (ad-hoc) — a one-off dollar amount with a description attached to a single driver log, for something like renting special equipment on an unusual job (TASK-330). Lives in `log_extra_charges`, becomes its own invoice line, never appears on a price list. Not to be confused with a standing charge: different lifetime, different audience, different storage.
