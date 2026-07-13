# Deployment & Operational Notes

Operational reference for running and updating Rupkeep. Task-tracked deployment work lives in **Dispatch** (`/admin/tasks`, label `epic:production-deployment`) — see [`TASKS_SCHEMA.md`](TASKS_SCHEMA.md).

---

## Environments

| Env | Location | Notes |
|-----|----------|-------|
| Local dev | `C:\Users\sreynoldsjr\Documents\GitHub\rupkeep-app` (Windows) | SQLite or local MySQL, `php artisan serve` |
| Production | `/var/www/rupkeep-app` (Linux, PHP-FPM behind nginx/Apache) | MySQL, queue worker required, GMP extension installed |
| Public URL | `https://pilotcar.io` (SSL) | |

**Stack:** Laravel 11.9, PHP 8.2+, Livewire 3, Tailwind 3, Vite, SQLite (dev) / MySQL (prod).

> Note: local dev runs on Windows PowerShell; production is Linux. Commands below are labelled where the platform matters.

---

## In-app deploy (Super User only)

Super users deploy from **`/admin/server-management`**. The **"Deploy Update"** button runs, in the project root:

```
git pull  →  php artisan assets:build  →  php artisan optimize:clear  →  php artisan optimize
```

After a deploy that includes a migration, also click **"Run database migrations"** (`php artisan migrate --force`) on the same page. Command output is displayed inline; each command is whitelisted in `app/Http/Controllers/AdminToolsController.php`.

Use **"Deploy Update"** (pull only), not **"Full Deploy"** — the latter also commits and pushes *from the server*, which can diverge from GitHub.

**Security:** all server-management actions are gated by auth + `is_super`.

---

## Server-side git update (manual, discards local state)

```bash
git fetch origin
git reset --hard origin/master
git clean -fd    # optional: removes untracked files/dirs
```

Alternative: set a pull strategy (`git config pull.rebase false`) before `git pull` if you prefer merges.

---

## Build & cache invalidation

After deploy:

```bash
npm run build
php artisan config:clear
php artisan view:clear
php artisan cache:clear
php artisan optimize:clear
```

For DB schema changes:

```bash
php artisan migrate --force
```

> **Don't skip `npm run build` after pulling code that adds or changes Tailwind classes.** Tailwind v3 uses JIT mode — it only compiles classes it finds in the `content` paths at build time. If a blade introduces a new class (e.g. `max-h-80`, `bg-amber-50`, or an arbitrary value like `max-h-[20rem]`) and the CSS bundle isn't rebuilt, the class silently doesn't exist and the styles disappear with no error. Symptom: layout looks right in dev (where Vite auto-rebuilds) but on prod the new utility just… isn't there. Fix is always the same: pull → `npm run build` → `php artisan view:clear`.

---

## Queue worker

Email / push notifications dispatch via the queue. Worker must be running in production.

```bash
php artisan queue:work
```

In **production (Linux)**, keep the worker alive with supervisor or a systemd unit, e.g.:

```ini
# /etc/supervisor/conf.d/rupkeep-worker.conf
[program:rupkeep-worker]
command=php /var/www/rupkeep-app/artisan queue:work --tries=3 --sleep=3
directory=/var/www/rupkeep-app
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/rupkeep-app/storage/logs/worker.log
```

Reload after changes: `sudo supervisorctl reread && sudo supervisorctl update`. Restart the worker after each deploy so it picks up new code (`sudo supervisorctl restart rupkeep-worker`).

For local testing without a worker, you can make listeners synchronous — see [`TESTING_NOTIFICATIONS.md`](TESTING_NOTIFICATIONS.md).

---

## Utilities

### Tail Laravel logs

**Production (Linux):**

```bash
tail -f /var/www/rupkeep-app/storage/logs/laravel.log
```

**Local dev (Windows):**

```powershell
powershell -File .\scripts\tail-laravel-log.ps1 -Lines 200
```

Add `-Follow` to stream, `-Contains "text"` to filter lines. Stack traces are trimmed to first `LOG_STACKTRACE_LIMIT` frames (default 12) — adjust via env var if needed.

### PowerShell chaining (local dev)

In local-dev PowerShell use `;` for command chaining (`&&` is not supported in the shipped Windows PowerShell version):

```powershell
cd C:\Users\sreynoldsjr\Documents\GitHub\rupkeep-app; php artisan test
```

---

## Brevo (email + SMS gateway)

- Configured in `config/mail.php`
- Uses the Brevo PHP SDK (`getbrevo/brevo-php`)
- SMS today is delivered via carrier email-to-SMS gateway addresses stored on users (e.g. `2074168659@mms.uscc.net`)
- Real Brevo SMS API is a future feature (TASK-053)
- Implementation lives in `app/Actions/SendUserNotification.php`

---

## Push notifications (web)

- Service worker + VAPID-based subscription, persisted in `push_subscriptions` table (migration 2026-02-03)
- VAPID env vars **required** in every environment:
  - `VAPID_PUBLIC_KEY`
  - `VAPID_PRIVATE_KEY`
  - `VAPID_SUBJECT` (usually `mailto:admin@yourdomain`)
- If unset, the push library raises `Unable to create the key` — see [BUGS.md TASK-002](BUGS.md#task-002)

---

## Backup strategy

🚧 **Not yet implemented.** Tracked as TASK-103.

Suggested approach:
- Nightly `mysqldump` of production DB → off-server storage
- Periodic restore drill into staging
- File backups for `storage/app/public/` (attachments)
