---
name: dispatch-work
description: The canonical Dispatch working session for this project — sync task state down from production, pick the next task, work it, close it, sync back up. Use at the START of any working session ("what should I work on?", "work on this project", "pick up the next task", "what's next?", "work canonically"), and at the END of one ("wrap up", "push the tasks", "sync to prod"). Complements dispatch-track, which only covers capturing NEW tasks mid-conversation.
---

# Dispatch working session

Dispatch is this project's database-backed task tracker (see [CLAUDE.md](../../../CLAUDE.md)). The **local dev DB is where the agent works**; production (pilotcar.io) holds the customer-visible state. The two are reconciled explicitly with `dispatch:pull` / `dispatch:push` — nothing syncs automatically. `docs/tasks.jsonld` is a regenerated transport file, never the source of truth, never hand-edited.

**Where tasks get created:** locally, via `dispatch:add` or the `/admin/tasks` UI — this is canonical, not a workaround. Local tasks reach customers only when you push, so wording and `is_public` can be reviewed first. The one exception: customers create tasks directly on production through the feedback form (they arrive in `triage` labeled `source:feedback`); you receive those by pulling.

---

## Start of session

Pull safely, then pick work:

```bash
php artisan dispatch:pull --dry-run     # fetch prod snapshot to docs/tasks.jsonld, no import yet
```

**Collision check before importing** — task codes are minted independently on each side, so if the local DB has unpushed tasks, grep the fetched snapshot for their codes:

```bash
grep -E "TASK-3(29|30|31)" docs/tasks.jsonld   # substitute your unpushed codes
```

- No matches → safe: `php artisan tasks:import` (pure upsert by code; local-only tasks survive).
- Matches → STOP and reconcile by hand: the import would overwrite your local task's title/body with production's different task of the same code. Renumber or push first after review.

If there are no unpushed local tasks, plain `php artisan dispatch:pull` does fetch + import in one step.

Then:

```bash
php artisan dispatch:next                                        # single highest-priority task
php artisan dispatch:queue --n=10                                # or: breadth
php artisan dispatch:queue --status=triage --label=source:feedback   # new customer submissions
php artisan dispatch:show TASK-XXX                               # full detail + comment thread
```

## Sanity-check before working a task

Old tasks rot. Before investing in what `dispatch:next` returns, ask: **is this still true?**

- Pre-launch infrastructure tasks (epic:production-deployment) may describe things that shipped long ago — production being live at https://pilotcar.io over SSL with MySQL is itself evidence.
- Old error-log bugs may reference code that no longer exists. Scrub with Grep and `git log -S` before attempting a "fix"; a one-time error from a localhost session months ago is usually stale cache, not a live bug.

Close stale tasks with an evidence-bearing note instead of silently skipping them:

```bash
php artisan dispatch:done TASK-XXX --note="Closing as shipped: <evidence>"
php artisan dispatch:done TASK-XXX --status=declined --note="Not reproducible: <what you checked>"
php artisan dispatch:done TASK-XXX --status=verifying --note="Can't confirm from dev; verify on host with: <command>"
```

## While working

```bash
php artisan dispatch:note TASK-XXX "Found X depends on Y — checking Z next"   # internal by default
php artisan dispatch:note TASK-XXX "Shipped behind feature flag" --public     # customer-visible
```

Leave findings in the thread as you go — the note is for the next agent with zero context. New actionable items discovered along the way: use the **dispatch-track** skill (`dispatch:add`), don't fold them into the current task.

## Closing a task

Only mark `done` when the code is actually committed/shipped:

```bash
php artisan dispatch:done TASK-XXX --ref=$(git rev-parse --short HEAD)
php artisan dispatch:done TASK-XXX --ref=PR#142 --note="Shipped — see PR"
```

## End of session

```bash
php artisan dispatch:push        # local → production; customer sees new state
```

Push at session boundaries, not every keystroke. **Do not push without the user's go-ahead** if the session created new tasks they haven't reviewed — new tasks default to non-public, but titles/notes still appear in the dev views and the push publishes all pending status transitions at once. Until you push, customers see the previous state — that's fine; say so in your wrap-up.

---

## Don'ts (from CLAUDE.md, enforced here)

- Don't edit `TASKS.md` (retired stub) or `docs/tasks.jsonld` (regenerated).
- Don't mark `done` for unshipped work — use `verifying` or leave a note.
- Don't commit `DISPATCH_REMOTE_TOKEN` anywhere; it lives only in `.env`.

## See also

- [`CLAUDE.md`](../../../CLAUDE.md) — full workflow reference
- [`.claude/skills/dispatch-track/SKILL.md`](../dispatch-track/SKILL.md) — capturing new tasks mid-conversation
- [`docs/TASKS_SCHEMA.md`](../../../docs/TASKS_SCHEMA.md) — task fields, statuses, label conventions
