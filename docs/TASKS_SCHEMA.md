# Tasks JSON-LD schema (`docs/tasks.jsonld`)

The `tasks` table is the canonical source of truth in the application database. `docs/tasks.jsonld` is the **structured bridge file** for export, backup, and bulk import — not the source of truth.

- **DB → file:** `php artisan tasks:export`
- **File → DB:** `php artisan tasks:import [--dry-run]` (upserts by `code`)
- **First-time bootstrap from legacy TASKS.md:** `php artisan tasks:bootstrap-from-markdown`

## Top-level shape

```jsonld
{
  "@context": { ... },
  "@type": "TaskCollection",
  "schemaVersion": "1.0",
  "exportedAt": "ISO-8601 timestamp",
  "exportedBy": "tasks:export | tasks:bootstrap-from-markdown",
  "tasks": [ Task, ... ],
  "labels": [ Label, ... ]
}
```

The `@context` defines a JSON-LD vocabulary so the file can be consumed by linked-data tools (or just treated as plain JSON for everyday use):

```jsonld
"@context": {
  "@vocab": "https://rupkeep.app/schema/tasks/v1#",
  "code": "@id",
  "labels": { "@container": "@set" },
  "comments": { "@container": "@list" },
  "createdAt": { "@type": "http://www.w3.org/2001/XMLSchema#dateTime" },
  "updatedAt": { "@type": "http://www.w3.org/2001/XMLSchema#dateTime" }
}
```

## `Task`

| Field | Type | Notes |
|-------|------|-------|
| `@type` | string | always `"Task"` |
| `code` | string | unique, e.g. `"TASK-042"` (or `"SHIP-001"` for legacy shipped items). Doubles as the `@id` |
| `title` | string | |
| `description` | string \| null | markdown body |
| `type` | enum | `bug` / `feature` / `chore` / `debt` / `verify` |
| `priority` | enum | `blocker` / `high` / `medium` / `low` |
| `status` | enum | `triage` / `open` / `in_progress` / `verifying` / `done` / `declined` |
| `isPublic` | boolean | true → visible to customers on `/documentation/roadmap` |
| `labels` | string[] | label names (resolved against the top-level `labels` array) |
| `submitter` | string \| null | submitter user email (for cross-system reference; FK is resolved by email on import) |
| `assignee` | string \| null | assignee user email |
| `createdAt` | ISO-8601 | export-time only; not used on import |
| `updatedAt` | ISO-8601 | export-time only; not used on import |
| `comments` | Comment[] | optional, embedded thread |

## `Comment`

| Field | Type | Notes |
|-------|------|-------|
| `@type` | string | always `"Comment"` |
| `body` | string | markdown |
| `author` | string \| null | user email |
| `isInternal` | boolean | true → hidden from customer in portal view |
| `sentToCustomer` | boolean | true → "Send Customer Update" was triggered |
| `eventType` | string | `comment` (default), `status_change`, `assignee_change`, `label_added`, `label_removed`, `is_public_toggle`, `promoted` |
| `meta` | object \| null | per-event payload, e.g. `{"from":"open","to":"in_progress"}` |
| `createdAt` | ISO-8601 | |

## `Label`

| Field | Type | Notes |
|-------|------|-------|
| `@type` | string | always `"Label"` |
| `name` | string | unique. Conventions: `epic:*` for epic groupings, `area:*` for code areas, `release:*` for releases |
| `color` | string \| null | hex code, e.g. `"#fb923c"` |
| `description` | string \| null | optional |

## Import semantics

`tasks:import` upserts by `code`:

- **Labels** are upserted first (`name` is the unique key).
- **Tasks** are upserted by `code`; existing rows have their fillable fields replaced.
- **Comments**: if a task in the JSON-LD has a `comments` array, the existing comments on that task are deleted and replaced. If the JSON-LD has no `comments` for a task, existing DB comments are left alone — this lets you do partial re-imports without losing thread history.
- **Label sync** uses `BelongsToMany::sync()` — labels not in the import are removed from the task.

Use `--dry-run` to preview the diff inside a rolled-back transaction.

## Compatibility

- The `schemaVersion` field lets future readers branch on shape. Increment when introducing breaking field changes.
- New fields are additive — old importers can ignore unknown keys.
- The `@context` URI (`https://rupkeep.app/schema/tasks/v1#`) is a stable identifier; v2 would be a new URI.
