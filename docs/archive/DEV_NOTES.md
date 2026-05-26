- At the start of the job, the creator needs to be able to select driver even before any car logs are started. So we need to add this driver field to the job header itself and each individual car log created can be seeded from that value, but the logs can be independantly updated. create a migration that adds the same filed that is now on the car log onto the Job table. add this select field to teh job creation form that once a client/customer is selected it can be poplulated with their drivers or allow creating of a new driver optionally. 

- Add indications of job accepted in the "My Assigned Jobs" list.
- Currently Deny only is avaiable for assigned (unaccepted) jobs, there should be an accept button as well. --- ../logs/1019
- replace the update log until job is excepted. having weird edit behavior
- https://pilotcar.io/my/invoices/1023/print ... tolls and other expenses are not editing correctly
- delete invoice is HARD, how? where? make this easier. On the Job show page where the user can view a link to the invoice and generated an invoice they should be able to delete it without opening it up.

- move existing fields to the car log/ driver log: "CLOCK IN" & Clock Out to Driver and Vehicle section
- START DAY, END DAY + START MILEAGE, END MILEAGE move from "Mileage Stops"
- Change Mileage and Stops to "Job Details" and put new "JOB START TIME", JOB END TIME"
- move LOG MEMO (INTERNAL) up into the "Driver and Vehicle Section"
- JOB MEMO (EXTERNAL) must be displayed and editable from the log edit page (keep it a proprt of the job itself, but editable adn viewable from bothpages) --- place this down in the load information section on the edit log page


- Explore native noticaion options in addition to emails notificatsion sent on job assignement and changes ---- push notificaitons? with email --- 

- block edit page https://pilotcar.io/my/customers/1 for all non-admin staff --- on the show page staff should seee all their jobs by that company. but hide everything else exepct for compandy details card and contacts card for non-admins. (hide Transaction Register, )

- Admin can accept a job log on behalf of a user. Make this happen.
- Add ability to Clear all invoices to profile page like super can for jobs and users, and cars ,etc..

- Merge this User with Other User -- fix this text to appear as a button add clearification "Merge content of this user to anoher user and then delete this user"


- double check that pricing configurations are being honored over defualts when they exist getting overwritten


-----


 Implementation Summary

  Completed Features:

  1. Driver Selection on Job Creation (Verified) - Already implemented with default_driver_id and default_truck_driver_id fields in
  CreatePilotCarJob component.
  2. Job Acceptance Indicators in Dashboard - Added approval status badges (pending/confirmed/denied) with icons and aggregate counts per job in
  dashboard.blade.php.
  3. Accept/Deny Buttons for Assigned Jobs - Added confirmLog() and denyLog() methods to Dashboard.php with corresponding UI buttons.
  4. Replace Update Log Until Job Accepted (Verified) - Already implemented with $blockEditing variable that prevents editing for pending logs
  while allowing admins/managers to still edit.
  5. Invoice Tolls/Expenses Editing Bug - Fixed MyInvoicesController.php by adding explicit handling for top-level numeric keys (tolls, hotel,
  extra_charge, etc.) that weren't being captured by Arr::dot/set.
  6. Easier Invoice Deletion from Job Show Page - Added deleteInvoice() method to ShowPilotCarJob.php with delete button in the view.
  7. Form Field Reorganization Migration - Created migration for clock_in and clock_out datetime fields.
  8. Form Field Reorganization UI - Added clock_in/clock_out fields to EditLogForm and updated the edit-user-log.blade.php view.
  9. Native Push Notifications - Completed the implementation by:
    - Updating JobUpdate notification to accept job data and generate proper push messages
    - Adding push notification sending to SendJobAssignedNotification listener
    - The infrastructure (service worker, subscription controller, VAPID key component) was already in place
  10. Customer Page Access Control - Added viewSensitive() method to CustomerPolicy and wrapped sensitive sections in customers/show.blade.php
  with @can directives.
  11. Admin Accept Job Log on Behalf of User - Updated edit-user-log.blade.php to allow admins/managers to see and use approval buttons for logs
  assigned to other users.
  12. Clear Invoices on Profile Page - Added clearInvoices() method to UserProfile.php with UI for super users.
  13. User Merge Button Text Fix - Updated user-profile.blade.php with proper description text for the merge user feature.
  14. Pricing Configuration Overrides (Verified) - PricingSetting::getValueForOrganization() is properly used throughout Invoice.php and
  PilotCarJob.php for all pricing calculations.

----------------
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

## Deployment - In-App Git Update (Super User)

- Super users can pull latest code from the Dashboard via “Pull Latest Code”.
- This runs the following commands in project root:
  - `git fetch origin`
  - `git reset --hard origin/master`
  - `git clean -fd`
- Output is displayed inline after execution. Any failure stops the sequence.

Security: Endpoint is protected by auth and `is_super` check on the server.