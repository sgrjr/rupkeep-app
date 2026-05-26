---
name: dispatch-track
description: PROACTIVELY capture any actionable item — bug, feature request, follow-up, "same recipe for X", "we should also...", customer feedback, "track this", "future task" — as a Dispatch task in this project's database via the `dispatch:add` CLI. Do this in the SAME response that surfaces the item; don't ask permission first. Report what was tracked at the end. Also use when the user explicitly says "track ...", "add a task for ...", "log this as ...", "future task: ...", "remember to ...".
---

# Dispatch task capture

This project tracks all open work — bugs, features, follow-ups, tech debt, customer feedback — in a database-backed system called **Dispatch** (see [CLAUDE.md](../../../CLAUDE.md) for the full workflow). The DB is the canonical source of truth; the markdown TASKS.md is retired.

When you (the agent) spot any actionable item in the conversation, **capture it immediately as a Dispatch task** rather than letting it slip into prose and disappear from history.

---

## When to invoke

Auto-invoke this skill the moment you spot any of these patterns — in the user's message OR in your own draft response:

| Pattern | Example phrase |
|---------|----------------|
| Bug described | "X isn't working", "Y fails when...", "this is broken", "regression in Z" |
| Feature requested | "we should add...", "it would be nice if...", "I want...", "can you build..." |
| Follow-up emerging | "same recipe for...", "do this elsewhere too", "we'll also need to...", "TBD" |
| Customer feedback | "the customer reported...", relayed quotes from a customer |
| Tech debt named | "we should clean up...", "this is hacky", "refactor later" |
| Explicit command | "track this", "log this as a task", "future task:", "remember to..." |

If a single user message describes multiple items, **track each one separately** with its own `dispatch:add` call.

### Do NOT use when

- The user is exploring an idea conversationally with no clear action item ("what do you think about...?", "could we...?")
- The work is already being completed in the current session (the actionable item IS what you're working on right now)
- The item is already a TASK-### we're actively working on (just note it in `dispatch:note` instead)
- The user explicitly says "don't track this" or "this is just for context"

---

## How to invoke

Run `php artisan dispatch:add` from the project root:

```bash
php artisan dispatch:add "<title>" \
  --type=<bug|feature|chore|debt|verify> \
  --priority=<blocker|high|medium|low> \
  --description="<full markdown body>" \
  --label=source:<customer|agent> \
  --label=area:<area> \
  [--label=epic:<slug>]
```

### `title` — required positional arg

- ~10 words, present-tense imperative or noun phrase
- Specific enough to scan in a list: "Form fields not saving on job creation" ✓, "Bug in jobs" ✗

### `--type`

- `bug` — broken behavior, regression, error, defect, customer complaint about how something works
- `feature` — new capability, enhancement, "would be nice if"
- `chore` — UI polish, refactor, doc update, dev experience
- `debt` — known tech debt, security hardening, performance, "should fix this later"
- `verify` — a previously-claimed-done thing that needs smoke-testing

### `--priority`

- `blocker` — production is broken right now, customers can't use the app
- `high` — customer-blocking, security issue, or a noisy bug
- `medium` — default; pick this when unclear
- `low` — nice-to-have polish, idea for someday

### `--description` — write it as if for a future agent who has no context

Include:
- What was reported / what triggers the issue
- What success looks like (acceptance criteria, even one line)
- Relevant file paths, function names, line numbers if known
- Related TASK-### IDs (link to existing epics or repro docs)
- Any commands or one-liners that reproduce the issue

Use markdown freely. Multi-line via shell heredoc or properly-escaped quotes. Code blocks render in the web UI.

### `--label` (repeat for each)

Conventions already in the DB:

- `source:customer` — customer-reported (via FeedbackForm or relayed)
- `source:agent` — you noticed it during work
- `area:auth`, `area:forms`, `area:notifications`, `area:invoices`, `area:jobs`, `area:logs`, `area:ui`, `area:timezone`, `area:dashboard`, `area:reporting`
- `epic:<slug>` — only if it clearly belongs to an existing epic (check `docs/ROADMAP.md` first)

Labels are auto-created if missing — no setup required.

---

## After creating

Mention what you tracked at the end of your response, one line:

> Captured **TASK-XXX** *(title)* as a `<type>` (priority: `<priority>`).

If you created multiple, list them all.

**Do not push to production automatically.** Local task creation stays local until the user explicitly runs `dispatch:push`. New tasks sit in the local DB so the user can review/edit before sharing.

---

## Examples

### Customer reports a bug while we're working on something else

> User: "Also, when I went to change my password yesterday it didn't work — pretty sure that broke."

Action:
```bash
php artisan dispatch:add "Customers cannot update their password" \
  --type=bug \
  --priority=high \
  --description="Customer reported the password-change flow is not working. This is a Jetstream-default feature, so something in our integration is preventing it. Check:

- /user/password route (Fortify config)
- resources/views/profile/update-password-form.blade.php is being rendered
- app/Actions/Fortify/UpdateUserPassword.php for role gating that might exclude customers

Repro: log in as a customer-role user, visit /user/profile, scroll to Update Password, submit. Expect: 'password updated' confirmation." \
  --label=source:customer \
  --label=area:auth
```

Report: "Captured **TASK-XXX** *(Customers cannot update their password)* as a `bug` (priority: `high`)."

### Agent notices a follow-up while shipping something

> Agent draft: "Pushed `581811ab..fdde5839`. The same recipe should be applied to logs / invoices / dashboard for consistency..."

Action: create a `chore` task for the propagation BEFORE finalizing the response, so the response can end with "Captured TASK-XXX for that follow-up."

### User says "remember to..."

> User: "Oh, remember to look at the deadhead count rendering on the invoice — it always shows zero."

Action:
```bash
php artisan dispatch:add "Deadhead count always rendering as zero on invoice" \
  --type=bug \
  --priority=medium \
  --description="User reports that the deadhead_count field on invoices always displays as 0 regardless of the underlying value. Check the Invoice values JSON for the source field name vs. what the blade is reading." \
  --label=source:customer \
  --label=area:invoices
```

---

## See also

- [`CLAUDE.md`](../../../CLAUDE.md) — the full agent workflow for Dispatch
- [`docs/ROADMAP.md`](../../../docs/ROADMAP.md) — existing epic groupings; reuse epic labels where applicable
- `php artisan dispatch:next` — pick up the next task to work on
- `php artisan dispatch:show TASK-XXX` — inspect a task you've just created
- `php artisan dispatch:push` — sync local task state up to production
