# Deployment & Operational Notes

Operational reference for running and updating Rupkeep. Task-tracked deployment work lives in [`TASKS.md`](../TASKS.md) (Epic: Production Deployment).

---

## Environments

| Env | Location | Notes |
|-----|----------|-------|
| Local dev | `c:\Users\sreynoldsjr\Documents\GitHub\rupkeep-app` | SQLite, `php artisan serve` |
| Production | `C:\inetpub\wwwroot\rupkeep-app` (Windows IIS) | MySQL, queue worker required |
| Public URL | `https://pilotcar.io` | |

**Stack:** Laravel 11.9, PHP 8.2+, Livewire 3, Tailwind 3, Vite, SQLite (dev) / MySQL (prod).

---

## In-app git update (Super User only)

Super users can pull latest code from the Dashboard via **"Pull Latest Code"**. This runs, in the project root:

```bash
git fetch origin
git reset --hard origin/master
git clean -fd
```

Output is displayed inline; any failure stops the sequence.

**Security:** Endpoint is protected by auth + `is_super` check on the server.

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

---

## Queue worker

Email / push notifications dispatch via the queue. Worker must be running in production.

```bash
php artisan queue:work
```

For long-running, prefer:
- **Windows:** Task Scheduler launching `php artisan queue:work --tries=3` at boot
- **Linux:** supervisor (sample config in `docs/archive/SETUP_LINUX_SERVER.md` if present)

For local testing without a worker, you can make listeners synchronous — see [`TESTING_NOTIFICATIONS.md`](TESTING_NOTIFICATIONS.md).

---

## Utilities

### Tail Laravel logs (Windows)

```powershell
powershell -File .\scripts\tail-laravel-log.ps1 -Lines 200
```

Add `-Follow` to stream, `-Contains "text"` to filter lines. Stack traces are trimmed to first `LOG_STACKTRACE_LIMIT` frames (default 12) — adjust via env var if needed.

### PowerShell chaining

In PowerShell use `;` for command chaining (`&&` is not supported in the shipped Windows PowerShell version):

```powershell
cd C:\inetpub\wwwroot\rupkeep-app; php artisan test
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
