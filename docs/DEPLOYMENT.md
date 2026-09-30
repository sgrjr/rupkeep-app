# Deployment & Operational Notes

Operational reference for running and updating Rupkeep. Task-tracked deployment work lives in **Dispatch** (`/admin/tasks`, label `epic:production-deployment`) — see [`TASKS_SCHEMA.md`](TASKS_SCHEMA.md).

---

## Environments

| Env | Location | Notes |
|-----|----------|-------|
| Local dev | `C:\Users\sreynoldsjr\Documents\GitHub\rupkeep-app` (Windows) | SQLite or local MySQL, `php artisan serve` |
| Production | `/var/www/rupkeep-app` (Linux, PHP-FPM behind nginx/Apache) | MySQL, queue worker required, GMP extension installed |
| Public URL | `https://pilotcar.io` (SSL) | |

**Stack:** Laravel 12, PHP 8.2+, Livewire 3, Tailwind 3, Vite, SQLite (dev) / MySQL (prod).

> Note: local dev runs on Windows PowerShell; production is Linux. Commands below are labelled where the platform matters.

## Required on production (`.env` checklist, TASK-427)

`.env.example` is production-shaped: copy it and fill the blanks. This is the
same list as a checklist. Check the live file with `php artisan env:check`
(super users can run it from **Server Management**, "Check .env"); it reads
the real values and never prints a secret.

| Key | Must be | Why |
|-----|---------|-----|
| `APP_ENV` | `production` | Enables production guards (`db:reset` refuses, auto-capture allowed) |
| `APP_DEBUG` | `false` | `true` shows config and stack traces to visitors |
| `APP_URL` | `https://pilotcar.io` | Signed URLs, login links and emails are built from it |
| `APP_KEY` | set | Encrypts sessions and cookies |
| `SESSION_SECURE_COOKIE` | `true` | Session cookie never travels over plain http |
| `LOG_STACK` | `daily` | One log file cannot grow forever |
| `DB_CONNECTION` | `mysql` + credentials | SQLite is dev only |
| `MAIL_MAILER` | `brevo` + `MAIL_USERNAME` / `MAIL_PASSWORD` | Real mail goes over Brevo's SMTP relay |
| `MAIL_FROM_ADDRESS` | a Brevo-verified sender | Brevo rejects mail from unverified senders |
| `BREVO_API_KEY` | set | SMS-gateway notifications |
| `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` / `VAPID_SUBJECT` | set | Push library throws without them |
| `APP_DISPLAY_TIMEZONE` | `America/New_York` | Times shown to users |
| `DISPATCH_AUTO_CAPTURE` | `true` | Uncaught 500s open a triage bug task |
| `SETUP_CONSOLE_ENABLED` | `false` or unset | `/setup` can wipe the database |
| `SETUP_PASSWORD` | unset | Same |
| `DISPATCH_REMOTE_URL` / `DISPATCH_REMOTE_TOKEN` | unset | Dev-machine keys; the host must not hold a super token |
| `SUPER_EMAIL` / `SUPER_NAME` / `SUPER_PASSWORD` | set | `super:create` needs them on a fresh install |
| `PRICING_DEFAULT_ORGANIZATION_ID` | set (or blank) | Blank falls back to the org named "Casco Bay Pilot Car" |
| `QUEUE_CONNECTION` | `database` + a running worker | Notifications and mail are queued |

---

## In-app deploy (Super User only)

Super users deploy from **`/admin/server-management`**. The **"Deploy"** button
runs, in the project root, stopping at the first failure (TASK-470):

```
php artisan down --retry=30
php artisan db:dump                      # storage/app/private/backups/db/{stamp}.sql
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --no-audit --no-fund
php artisan assets:build                 # npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan queue:restart                # the supervised worker reloads the new code
php artisan up
```

Whatever step fails, `php artisan up` still runs last, so a failed deploy
leaves the old code serving, not a maintenance page. A dump that cannot be
written stops the deploy before anything is pulled. One deploy at a time: a
second click (or a second tab) gets "Another server command is still running".

The server only pulls. It never commits or pushes; the old "Full Deploy" and
the stage/commit/push buttons are gone. A pull that cannot fast-forward means
something was edited on the host: the dashboard's **"Reset to GitHub master"**
button (two clicks; `git fetch` + `reset --hard origin/master` + `clean -fd`)
throws those edits away, then run Deploy.

**Rollback** rolls back one migration step, never a whole batch, and only after
`ROLLBACK` is typed into the box next to the button.

**Git over SSH** trusts only the GitHub host keys committed in
`resources/ssh/github_known_hosts`. If a pull fails with "Host key verification
failed", compare with `ssh-keyscan -t ed25519,ecdsa github.com` and update the
file. (An HTTPS remote is unaffected.)

**Timeouts.** The deploy runs inside one request. PHP allows 600 s
(`IsSuperAdmin`), and the host must match it or nginx answers 504 while the
deploy carries on blind:

```nginx
# in the pilotcar.io server block, location ~ \.php$
fastcgi_read_timeout 600;
```

```ini
; /etc/php/8.2/fpm/pool.d/www.conf
request_terminate_timeout = 600
```

If a deploy times out anyway, run the same commands over SSH.

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

## Super users (application-wide access)

"Super user" is application-wide platform access, and is deliberately **not**
the same thing as `organization_role = admin`, which is scoped to a single
organization. Mary and Matthew are org admins; they are not super users.

It is a real column, `users.is_super`, set only by:

```bash
php artisan super:grant                    # list current super users
php artisan super:grant someone@example.com
php artisan super:grant someone@example.com --revoke
```

`is_super` is absent from `User::$fillable`, so nothing in the HTTP layer can
grant it. Revoking the last super user is refused — nothing else in the app can
grant it back.

**On an existing database** (`php artisan migrate --force`), the TASK-366
migration backfills the flag onto the admins of the organization that used to be
treated as super. Confirm the result with `php artisan super:grant` before
relying on it.

**On a fresh install** (`php artisan db:reset`, i.e. `migrate:fresh` →
`super:create` → `db:seed`), the migration runs against an empty `users` table
and promotes nobody. `super:create` is what mints the super user, from
`SUPER_EMAIL` / `SUPER_NAME` / `SUPER_PASSWORD` in `.env`. **If `SUPER_EMAIL` is
unset the command now fails loudly** rather than leaving the install with no
super user and every admin tool unreachable.

## Fresh install and the setup console (TASK-424)

`php artisan db:reset` is `migrate:fresh --force` → `super:create` →
`db:seed --force`. **It refuses to run when `APP_ENV=production`** unless you
pass `--force-production`:

```bash
php artisan db:reset                      # refused on production
php artisan db:reset --force-production   # wipes and reseeds; you asked for it
```

The `/setup` web console runs the same command and never passes the flag, so it
cannot wipe production. It is also off by default (`SETUP_CONSOLE_ENABLED`),
and when on it requires a signed-in super user plus `SETUP_PASSWORD`. See
[`FEATURE_FLAGS.md`](FEATURE_FLAGS.md#setup-console-task-424). On the
production host, leave `SETUP_CONSOLE_ENABLED` unset (or `false`) and
`SETUP_PASSWORD` unset.

## Deadhead miles (TASK-354)

`user_logs` carries two deadhead figures: `dead_head_driven` (what the vehicle
drove to reach the pickup — tracked always) and `dead_head_billed` (what the
customer is charged — opt-in, and capped at driven minus the published
`free_miles`). Nothing bills until a human enters a number.

The migration that added them **only adds columns**. Seeding `dead_head_driven`
from the odometer is a separate, re-runnable command:

```bash
php artisan deadhead:backfill-driven            # dry run — reports what it would fill
php artisan deadhead:backfill-driven --write    # apply
```

It fills only logs where the field is NULL, so re-running is safe and a figure
someone entered by hand is never overwritten. It never touches
`dead_head_billed`.

**Run it after any bulk data load** — a CSV import, a restored dump, or real
history arriving on a database that was carrying test data when the schema
changed. A migration runs exactly once, so logs that land afterwards would
otherwise sit blank with their deadhead miles visible in the odometer the whole
time. (The CSV importer seeds new rows on its own; the command is the catch-up
for anything that bypassed it.)

Logs whose four odometer readings are missing, out of order, or imply an
approach over 1,000 miles are left NULL on purpose — "we don't know" has to stay
distinguishable from "drove straight there", and production holds a row implying
a 190,065-mile drive to the pickup.

## Build & cache invalidation

After deploy:

```bash
npm run build
php artisan config:clear
php artisan view:clear
php artisan cache:clear
php artisan optimize:clear
php artisan queue:restart   # workers cache code in memory — signal them to reload (see Queue worker)
```

For DB schema changes:

```bash
php artisan migrate --force
```

> **Always run `php artisan queue:restart` after a deploy.** A long-running
> `queue:work` process holds the *old* code in memory and will keep executing it
> against new jobs until it restarts — so a fix to a job, listener, notification,
> or mailable won't take effect until the worker is cycled. `queue:restart` tells
> workers to exit gracefully after their current job; supervisor then respawns
> them on the new code. (The `--max-time=3600` flag recycles them hourly as a
> safety net, but don't rely on it — restart explicitly.)

> **Don't skip `npm run build` after pulling code that adds or changes Tailwind classes.** Tailwind v3 uses JIT mode — it only compiles classes it finds in the `content` paths at build time. If a blade introduces a new class (e.g. `max-h-80`, `bg-amber-50`, or an arbitrary value like `max-h-[20rem]`) and the CSS bundle isn't rebuilt, the class silently doesn't exist and the styles disappear with no error. Symptom: layout looks right in dev (where Vite auto-rebuilds) but on prod the new utility just… isn't there. Fix is always the same: pull → `npm run build` → `php artisan view:clear`.

---

## Queue worker

Email / push notifications dispatch via the queue. Worker must be running in production.

```bash
php artisan queue:work
```

Production runs `QUEUE_CONNECTION=database`, so jobs are stored in the `jobs`
table and drained by a supervised worker (verified live 2026-07-14 — TASK-101).
Failures after `--tries` land in `failed_jobs`.

In **production (Linux)**, the worker is kept alive by **supervisor** (installed,
`enabled` for boot, `autorestart`), matching the verified running command:

```ini
# /etc/supervisor/conf.d/rupkeep-worker.conf
[program:rupkeep-worker]
command=php /var/www/rupkeep-app/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
directory=/var/www/rupkeep-app
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/rupkeep-app/storage/logs/worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5
```

The two `stdout_logfile_*` lines keep `worker.log` from growing forever
(TASK-469); supervisor rotates it itself, no logrotate needed.

Reload after editing the conf: `sudo supervisorctl reread && sudo supervisorctl update`.

**Restarting after a deploy** — prefer the framework-native, graceful restart,
which needs no sudo and works with supervisor:

```bash
php artisan queue:restart      # workers finish the current job, then exit; supervisor respawns them on new code
```

Use `sudo supervisorctl restart rupkeep-worker` only if you need to force-cycle
the process itself (e.g. after changing the supervisor conf).

Verify the worker is up:

```bash
ps aux | grep queue:work
systemctl status supervisor
```

For local testing without a worker, you can make listeners synchronous — see [`TESTING_NOTIFICATIONS.md`](TESTING_NOTIFICATIONS.md).

---

## Monitoring

Three things watch production once the scheduler cron below is installed
(TASK-469):

- **`queue:health --notify`** runs every fifteen minutes. It is unhealthy when
  no `queue:work` process is running, when the oldest pending job is five or
  more minutes old, when more than 100 jobs are queued, or when `failed_jobs`
  is not empty. On a problem it emails every super user (at most once per six
  hours for the same problem) and emails once more when the problem clears.
  Run it by hand any time: `php artisan queue:health` (exit code 1 = needs
  attention; `--json` for scripts).
- **`/up`** answers `503` when the database cannot be reached or the oldest
  pending job is five or more minutes old, and `200` otherwise. Point an
  external uptime monitor (UptimeRobot, Better Stack, a cron on another box)
  at `https://pilotcar.io/up` every minute or two. That is the alarm for "the
  site is down" and "the worker is dead"; nothing inside the app can raise it
  when the app itself is down.
- **Logs**: `LOG_STACK=daily` (see the env table above) rotates `laravel.log`;
  supervisor rotates `worker.log` (see the worker section).

## Scheduler (cron)

Scheduled commands (defined in `routes/console.php` — currently the daily
`vehicles:send-maintenance-reminders` digest, TASK-041) only run if the host
cron invokes Laravel's scheduler every minute:

```bash
# crontab -e  (as the web/app user)
* * * * * cd /var/www/rupkeep-app && php artisan schedule:run >> /dev/null 2>&1
```

**Until this cron entry exists on the host, the maintenance digest never
runs** (TASK-466 confirmed it had not, as of 2026-09-30). Install it once as the
web/app user, then verify what's due with:

```bash
php artisan schedule:list
```

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
- File backups for `storage/app/private/` — job and log attachments live there under `jobs/attachments_{job_id}/{uuid}.{ext}` (TASK-454); the DB row's `location` is relative to that folder, so a restore into any directory works as long as the two are restored together
