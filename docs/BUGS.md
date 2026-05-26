# Bug Repros & Investigation Notes

Detail backing the **Open Bugs** section of [`TASKS.md`](../TASKS.md). One section per `TASK-###`. Add repro steps, suspected cause, and any relevant file paths.

When a bug is fixed, leave the section in place but prefix the heading with `~~strikethrough~~` and add a "Fixed in" line — that way the history stays browsable. Periodically prune to `docs/archive/BUGS_RESOLVED.md`.

---

## TASK-001
**Title:** `Class "App\Livewire\User" not found` when loading `/dashboard`

**Where seen:** Captured in the in-app user-events error log (`/user-events`, ~3 months old as of 2026-05-26). Affected user: stephengreynoldsjr@gmail.com. URL: `http://localhost:8000/dashboard`.

**Symptoms**
- 500 error rendering the dashboard
- Stack mentions `App\Livewire\User` (which doesn't exist — the model is `App\Models\User`)

**Suspected cause**
Stale `bootstrap/cache/*` or a Livewire component reference resolving `User` to the wrong namespace. Possible culprits:
- A `<livewire:user .../>` tag in a blade that should reference a different component
- A `Livewire::component('user', ...)` registration colliding with the `User` model import
- A cached compiled view in `storage/framework/views/` referencing the old class

**Repro steps**
1. Visit `/dashboard` while authenticated
2. If the error doesn't reproduce, scrub for stale `<livewire:user` tags: `Grep "<livewire:user|@livewire\(.user." in resources/views/`

**Suggested investigation**
- `php artisan view:clear && php artisan cache:clear && php artisan optimize:clear`
- `Grep` for `Livewire\\User` and `'user'\s*=>` in `app/Providers/`
- Inspect `app/Livewire/Dashboard.php` for component registrations
- If reproducible, capture the full stack from `storage/logs/laravel.log`

**Status:** open

---

## TASK-002
**Title:** `Unable to create the key` on guest hit to `/`

**Where seen:** In-app user-events log (~3 months old). Guest user, URL `http://localhost`.

**Symptoms**
- 500 error on the landing page for unauthenticated users
- Error text: "Unable to create the key"

**Suspected cause**
- Most likely VAPID web-push key generation (`minishlink/web-push`) being invoked in the landing-page render path or in a Livewire component that mounts unconditionally. The push-subscriptions table was added 2026-02-03; if VAPID env vars aren't set in development the key generator throws this exact message.
- Alternate: `APP_KEY` not set, but that would usually phrase as "No application encryption key has been specified" — less likely here.

**Repro steps**
1. Open an incognito/private window
2. Visit `http://localhost` (or wherever local dev runs)
3. Observe error

**Suggested investigation**
- `Grep "VAPID|WebPush|PushSubscription"` in `app/Http/Controllers/` and `app/Livewire/`
- Confirm `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` are present in `.env` (and in `.env.example` for new devs)
- If the landing page mounts a Livewire component that touches push subscriptions, gate it behind `auth()->check()`
- Generate keys: `php artisan webpush:vapid` (if `laravel-notification-channels/webpush` is installed) or via minishlink helper

**Related:** TASK-006 (push notification VAPID handling)

**Status:** open

---

## TASK-003
**Title:** Rate values not saving on `PilotCarJob` edit form

**Where:** `app/Livewire/EditPilotCarJob.php`, `app/Models/PilotCarJob.php`

**Symptoms**
- User edits a job, sets `per_mile_rate` (or other rate field), saves
- On reload the value is gone or reverted to the customer default

**Suspected cause**
- Form property name mismatch (similar pattern to the `wait_time_hours` / `trailer_no` fixes from CUSTOMER_INTERVIEW 1.5)
- Validation rule silently rejecting the field
- Rate computed via `PilotCarJob::invoiceValues()` accessor masking the stored value

**Repro steps**
1. Open an existing job
2. Set `per_mile_rate` to a non-default value (e.g. 5.25)
3. Save
4. Reload — value is back to default

**Status:** open

---

## TASK-004
**Title:** Missing "Show" link on some jobs index views

**Where:** Job index variants (per `.cursorrules` known issues and `DEV_NOTES` raw notes)

**Symptoms**
- Some job rows show only an Edit button — no link through to the read-only Show page
- Customer-scoped views may also be affected

**Status:** open

---

## TASK-005
**Title:** Main contact tag missing on customer contacts

**Where:** `app/Models/CustomerContact.php` (or similar), customer show view

**Symptoms**
- No way to designate which `CustomerContact` is the primary/main contact
- Drivers/managers can't easily identify who to reach first

**Suggested approach**
- Add `is_main_contact` boolean (or `main_contact_id` on `Customer`)
- UI: star/badge in contact list, single click to promote
- Ensure only one main contact per customer (enforce in model boot)

**Status:** open

---

## TASK-006
**Title:** Push notification VAPID key generation can fail

**Where:** `app/Listeners/SendJobAssignedNotification.php` push branch, `app/Notifications/JobUpdate.php`

**Symptoms**
- Same "Unable to create the key" string as TASK-002 — likely the same root cause surfaced from a different code path
- May leave job-assigned emails ungent if exception isn't caught

**Suggested fix**
- Wrap push send in try/catch and log without aborting the listener
- Ensure VAPID env vars are validated at config-cache time (fail loudly in production startup, not at first push)

**Related:** TASK-002

**Status:** open
