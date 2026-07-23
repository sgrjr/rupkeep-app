# Rupkeep

Rupkeep is the operations platform for **Casco Bay Pilot Car**, an escort / pilot-car company.
It manages the full job lifecycle — pilot-car jobs, driver logs, invoicing, customers, and
vehicles — replacing an earlier Google Sheets workflow with a single job-tracking, logging,
and billing system.

Production runs at **https://pilotcar.io**.

This is a private business application, not an open-source project.

## What it does

- **Pilot-car jobs** — schedule and track escort assignments for oversized loads.
- **Driver logs** — mobile-first forms drivers use to record daily work against a job
  (mileage, expenses, deadhead, wait time).
- **Invoicing** — calculate billable miles, rates, mini/deadhead charges, and expenses,
  then generate customer invoices (print-optimized HTML, with PDF support via dompdf).
- **Customers & contacts** — customer records with one-time login codes for portal access
  (customers view their invoices without a password).
- **Vehicles** — fleet inventory with odometer, maintenance, and assignment tracking.

The app is multi-tenant: each `Organization` owns its own users, customers, vehicles, and jobs.

## Tech stack

- **PHP 8.2+**, **Laravel 11.9**
- **Livewire 3** for reactive UI, **Jetstream** for auth/teams, **Sanctum** for API tokens
- **Tailwind CSS 3**, built with **Vite**
- **MySQL** in production, **SQLite** in local development; tests run on SQLite `:memory:`
- Email via **Brevo** (`getbrevo/brevo-php`); PDF via **dompdf**; web push notifications
- Primary brand color: `#f9b104` (orange)

## Local development

Requires PHP 8.2+, Composer, and Node.js.

```bash
# 1. Clone and install dependencies
git clone <repo-url> rupkeep-app
cd rupkeep-app
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate
# Set APP_NAME=Rupkeep in .env (the example still ships Laravel defaults).
# DB_CONNECTION defaults to sqlite — no MySQL needed for local dev.

# 3. Database (SQLite)
# Create the SQLite file if it doesn't exist, then migrate.
php artisan migrate

# Optional: seed the Casco Bay org, users, and vehicles (values in config/setup.php)
php artisan db:seed

# 4. Build front-end assets
npm run build      # one-off production build
# — or —
npm run dev        # Vite dev server with hot reload

# 5. Run the app
php artisan serve
```

On Windows, if the SQLite file is missing, create `database/database.sqlite` before
running `php artisan migrate`.

For a full local dev loop (server + queue worker + logs + Vite) in one command:

```bash
composer run dev
```

### Running tests

```bash
php artisan test               # full suite (SQLite :memory:)
php artisan test --filter=TaskTest
```

## Where work is tracked — Dispatch

There is **no `TASKS.md`**. All open work — feature requests, bug reports, tech debt,
verification, and customer-facing roadmap items — lives in the database-backed **Dispatch**
system:

| Surface | Location |
|---------|----------|
| Dev task list | `/admin/tasks` |
| Dev kanban board | `/admin/tasks/board` |
| Public roadmap | `/documentation/roadmap` (public tasks only) |
| Customer portal | `/portal/tasks` |

Agents and developers interact with Dispatch through `php artisan dispatch:*` commands
(`dispatch:next`, `dispatch:show`, `dispatch:note`, `dispatch:done`, `dispatch:pull`,
`dispatch:push`). See [`CLAUDE.md`](CLAUDE.md) for the full workflow and
[`docs/TASKS_SCHEMA.md`](docs/TASKS_SCHEMA.md) for the task schema.

## Documentation

- [`CLAUDE.md`](CLAUDE.md) — working guide for anyone (human or AI) picking up this repo.
- [`docs/ROADMAP.md`](docs/ROADMAP.md) — architectural decisions and domain glossary.
- [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) — deployment and operational runbook.
- [`docs/FEATURE_FLAGS.md`](docs/FEATURE_FLAGS.md) — feature-flag reference.
- [`docs/TASKS_SCHEMA.md`](docs/TASKS_SCHEMA.md) — Dispatch task schema.
- [`docs/BUGS.md`](docs/BUGS.md) — bug repros and investigation notes.

## Glossary (quick reference)

- **Pilot car** — escort vehicle for an oversized load.
- **Job** — a pilot-car assignment; may span multiple days/logs.
- **Log** — a single driver's work record for a job (a daily entry).
- **Deadhead** — empty return trip (flat charge).
- **Mini** — short job at or below the billable-mile threshold (flat charge).
- **Billable miles** — miles charged to the customer (job start to job end).
