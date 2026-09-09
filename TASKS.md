# TASKS — moved to the database

> Tasks are now first-class records in the database. This file is no longer the source of truth.

## PICKUP HERE


Wrapping up. Filing the last two, then committing and dispatching everything.

  Committed d11393e1, pushed to master, ran 2 shell commands, wrote 1 memory

Everything's committed, pushed, dispatched, and the tree is clean.

Drive #2 payload verified

mileage    400 × $2.00 = 800.00
deadhead   204 × $1.00 = 204.00     ← billed, not the 469 driven
wait       2 × $30.00  =  60.00
stop       1 × $30.00  =  30.00
tolls                     18.50
                       ─────────
                        1112.50    ✓ matches
ledger:  escort 400 + deadhead 469 + release 231 = 1,100 ✓

Four things confirm fixes are already live on production: no total_due and no cross-vehicle start_job_mileage/end_job_mileage in the values blob, the chase log's 190 deadhead prefilled, and rate_code: lead_chase_per_mile. The stored invoice matches the computed values exactly — no drift.

Final state

┌────────────────────┬─────────────────────────────────┐
│                    │                                 │
├────────────────────┼─────────────────────────────────┤
│ Commits            │ 12, all pushed through d11393e1 │
├────────────────────┼─────────────────────────────────┤
│ Working tree       │ clean, 0 unpushed               │
├────────────────────┼─────────────────────────────────┤
│ Suite              │ 532 passed, 152 blades compile  │
├────────────────────┼─────────────────────────────────┤
│ Fixed this session │ 30                              │
├────────────────────┼─────────────────────────────────┤
│ Still open         │ 3                               │
└────────────────────┴─────────────────────────────────┘

Deploy before drive 003

From /admin/server-management: Deploy Update → Run database migrations → then on the host:

npm run build && php artisan view:clear
php artisan queue:restart

The build step is not optional — 234 form controls and the border token changed, and without a rebuild those classes don't exist in the CSS bundle.

What's open

- TASK-416 — the 22-entry rate picker. Mary's decision, not a code call.
- TASK-421 — multi-page invoice pagination. Needs a genuinely two-page invoice.
- TASK-393 — activity trail with the 14-month archiving prune.

Plus one unresolved from drive #2: TASK-422 carries a command to explain the modal's empty vehicle report. My guess is the drive's logs were assigned without a vehicle — assignJob() treats it as optional, which makes such a log invisible to every vehicle report. Worth deciding whether it should be required.

For the next session

Written to memory as go-live-drive-throughs.md — the runbook URL, your one-build-per-drive rule, the verification commands, and the two patterns that account for most of the 30 findings: residual categories and two owners for one piece of state.

Say "continue the go-live work" in a new session and it'll pick up from there.

## Where to go

| What you want | Where it lives now |
|---------------|---------------------|
| **Browse / filter all tasks** | `/admin/tasks` (staff) |
| **Kanban board** | `/admin/tasks/board` (staff) |
| **A specific task** | `/admin/tasks/{TASK-###}` |
| **My submitted requests** | `/portal/tasks` (customers) |
| **Public roadmap** | `/documentation/roadmap` |
| **Triage feedback into a task** | `/admin/feedback` → "Promote to Task" |

## File-side bridge

`docs/tasks.jsonld` is the structured export/import file. The database remains canonical.

- **Snapshot DB → file:** `php artisan tasks:export`
- **Restore / bulk load file → DB:** `php artisan tasks:import [--dry-run]`

The JSON-LD `@context` and field meanings are documented in [`docs/TASKS_SCHEMA.md`](docs/TASKS_SCHEMA.md).

## Historical snapshot

The previous markdown orchestration doc is preserved at [`docs/archive/TASKS.md`](docs/archive/TASKS.md) for reference. It was last accurate on **2026-05-26** before migration into the DB.
