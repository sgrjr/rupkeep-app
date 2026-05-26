# TASKS — moved to the database

> Tasks are now first-class records in the database. This file is no longer the source of truth.

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
