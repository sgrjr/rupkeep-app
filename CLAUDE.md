# Agent guide — Rupkeep (Casco Bay Pilot Car)

This file is a guide for any AI coding agent working in this repository. **Read it first** when you join a new session — it explains where the work lives, how to pick it up, and how to hand it back.

## What this project is

Rupkeep is a Laravel 11 + Livewire 3 + Jetstream app for managing pilot-car / escort operations (jobs, driver logs, invoices, customers, vehicles). Primary stack:

- **PHP 8.2+**, **Laravel 11.9**, **Livewire 3**, **Tailwind 3**, **Jetstream**, **Sanctum**
- **MySQL** in prod, **SQLite** in dev (tests use SQLite `:memory:`)
- Primary color: `#f9b104` (orange — on-brand for construction / escort vehicles)
- Production lives at **https://pilotcar.io** (`/var/www/rupkeep-app/` on the Linux host)

## Where the work lives — **Dispatch**

All open work (feature requests, bug reports, tech debt, verification, customer-facing roadmap items) is tracked in the **Dispatch** system. There is **no** TASKS.md to update anymore — task state is in the database.

| Surface | Where |
|---------|-------|
| Dev list view | `/admin/tasks` |
| Dev kanban board | `/admin/tasks/board` |
| Individual task | `/admin/tasks/{TASK-###}` |
| Triage queue (where new submissions land) | `/admin/tasks?statusFilter=triage` |
| Customer-facing list | `/portal/tasks` |
| Public roadmap | `/documentation/roadmap` (only `is_public=true` tasks) |
| Customer feedback intake | The `<livewire:feedback-form>` modal (footer + dedicated `/feedback` page). Submissions create a **task** in `triage` status with label `source:feedback`. There is no separate feedback inbox anymore. |
| File-side bridge | `docs/tasks.jsonld` — read/written by `dispatch:pull`/`push` and `tasks:export`/`import`. NOT the source of truth. |
| Schema doc | [`docs/TASKS_SCHEMA.md`](docs/TASKS_SCHEMA.md) |

## Agent workflow — the seven verbs

These artisan commands are designed for a single agent's working loop. Use them like you would have used a markdown checklist.

### Start of session
```bash
php artisan dispatch:pull        # production → local DB (optional; only if you want to start from live state)
php artisan dispatch:next        # "what should I work on?"
```

`dispatch:next` returns the single highest-priority open task (by status then priority then code). Add filters as needed:

```bash
php artisan dispatch:next --type=bug
php artisan dispatch:next --label=epic:customer-portal-one-time-login-codes
php artisan dispatch:next --json     # machine-readable
```

For more breadth:

```bash
php artisan dispatch:queue --n=10
php artisan dispatch:queue --priority=high --type=bug
php artisan dispatch:queue --status=triage --label=source:feedback   # new customer submissions
```

### While you work
```bash
php artisan dispatch:show TASK-042                    # full detail + thread
php artisan dispatch:show TASK-042 --no-internal      # what the customer sees
php artisan dispatch:show TASK-042 --json             # for further tooling

php artisan dispatch:note TASK-042 "Found that X depends on Y — checking Z next"
php artisan dispatch:note TASK-042 "Shipped behind feature flag" --public
```

`dispatch:note` defaults to **internal** (customer never sees it). Pass `--public` only if you want it visible in the customer portal.

### Ending work on a task
```bash
php artisan dispatch:done TASK-042 --ref=abc123ef
php artisan dispatch:done TASK-042 --ref=PR#142 --note="Shipped — see PR for details"
php artisan dispatch:done TASK-042 --status=verifying --note="Awaiting QA"
php artisan dispatch:done TASK-042 --status=declined --note="Out of scope, see CRD-9"
```

Status options: `done` (default), `declined`, `verifying`. The closing comment is auto-created with the from/to transition and any `--ref` / `--note` you pass.

### End of session
```bash
php artisan dispatch:push        # local DB → production
```

This re-exports the local DB to `docs/tasks.jsonld` and POSTs it to the production `apply` endpoint. **Run this once you're done** — until you push, the customer sees the previous state.

## Where customer feedback lands

Submissions through `<livewire:feedback-form>` create a `Task` directly:

- `status` = `triage`
- `type` = `bug` (if severity=error) or `feature` (if severity=info)
- `is_public` = false (dev decides what to expose)
- `submitter_user_id` = the submitting user
- `label` = `source:feedback` (auto-attached so you can filter the queue)

The customer immediately sees their submission at `/portal/tasks/{code}` and the success message shows the task code. There is no longer a "promote" step.

**Historical data**: legacy `user_events` rows with `type='feedback'` (from before this integration) are converted with `php artisan dispatch:backfill-feedback`. Idempotent — safe to re-run.

## Sync setup (one-time, for new dev environments)

Edit `.env` and add:

```ini
DISPATCH_REMOTE_URL=https://pilotcar.io
DISPATCH_REMOTE_TOKEN={a Sanctum personal-access token issued on production to a super user}
```

Issue the token by signing into the production app as a super user, going to Jetstream's API tokens page, creating a token, and copying it once. The HTTP endpoints (`/api/dispatch/snapshot`, `/api/dispatch/apply`) reject any non-super-user token with 403.

## What NOT to do

- **Don't edit `TASKS.md`** — it's a stub now. The DB is canonical.
- **Don't add tasks by editing `docs/tasks.jsonld` directly** — the file is regenerated on every `dispatch:export`/`push`. Use the CLI (`dispatch:note`, etc.) or the web UI.
- **Don't push to production every keystroke.** Push at session boundaries (end of a task or end of a working block).
- **Don't mark a task done before you push** — if you're not done and you push, the customer sees "in progress". That's fine. But don't mark done unless the code is actually shipped.
- **Don't commit your token to git.** `.env` is gitignored already; just don't put `DISPATCH_REMOTE_TOKEN` anywhere else.

## Other helpful pointers

- **Tests**: `php artisan test`. Suite is currently green (54/54). Use `--filter=TaskTest` to scope.
- **Currency formatting** in views: use `App\Support\Money::currency($amount)` — it falls back gracefully when `ext-intl` isn't loaded.
- **Memory** for session-spanning facts: see `C:\Users\sreynoldsjr\.claude\projects\.../memory/MEMORY.md` (Claude Code only).
- **Architectural decisions** + **glossary** are in [`docs/ROADMAP.md`](docs/ROADMAP.md).
- **Deployment / operational ops**: [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).
- **Bug repros + investigation notes**: [`docs/BUGS.md`](docs/BUGS.md).
- **Feature-flag reference**: [`docs/FEATURE_FLAGS.md`](docs/FEATURE_FLAGS.md).

## Auto-capture skill

There is a project skill at [`.claude/skills/dispatch-track/SKILL.md`](.claude/skills/dispatch-track/SKILL.md) that tells Claude Code to **automatically** create a Dispatch task whenever the user describes a bug, feature request, follow-up, or any actionable item — without prompting. If you notice something worth tracking during a conversation, just run `dispatch:add` and report what you captured at the end of your response.

## The shortest loop, summarized

```bash
php artisan dispatch:pull              # sync down from prod (optional)
php artisan dispatch:next              # what's next?
# … do the work, commit code …
php artisan dispatch:note TASK-042 "X done; Y still open"
php artisan dispatch:done TASK-042 --ref=$(git rev-parse --short HEAD)
php artisan dispatch:push              # sync up to prod
```

That's the entire ritual.
