- Done. Both JOBS and LOGS can have attachments (ie, Upload Pictures/Permits)
- day rate vs. mileage
- summarized version of invoices & standard invoice
- Invoice must be editable
- contact email & main contact for company
- must be able to override billable miles
- must be able to export data for QuickBooks
- must be able to notify drivers of jobs assigned via (email+phone text)
- driver of pilot car is missing? 
- vehicle maintenance/history
    - oil change (set a schedule )
    - in garage
    - inspection & reg
- only scheduled pickup, no scheduled delivery (move to start time and end time, start job time, end job time) 4 "clocks"
- Invoice should include: Driver Name & Position, start job mileage and end job mileage and start job time and end job time
- main contact tag, a pattern to distinguish "Main Customer Contact"
- invoice "invoice paid" link jto invoice, hide "pickup @my/customers/64" add "show " to the jobs list its missing and only has edit --- default view on dashboard: see list of customers
- safe delete, (I deleted  job by mistake, must make it easier to restore accidentally deleted resources as well as may “confirm before delete”?)
- rate values not being saved
- billable miles, edit values vs calculated values


## Utilities

- Quick log tailing: `powershell -File .\scripts\tail-laravel-log.ps1 -Lines 200`
  - Add `-Follow` to stream (`-Follow`) and `-Contains "text"` to filter lines.
- Stack traces trimmed to first `LOG_STACKTRACE_LIMIT` frames (default 12). Adjust via env var if needed.
- In PowerShell use `;` for command chaining: `cd C:\inetpub\wwwroot\rupkeep-app; php artisan test`
- Server Git update (discard local state and pull latest from GitHub):
  ```bash
  git fetch origin
  git reset --hard origin/master
  git clean -fd    # optional: removes untracked files/dirs
  ```
  *Alternatively set a pull strategy, e.g. `git config pull.rebase false`, before running `git pull` if you prefer merges.*