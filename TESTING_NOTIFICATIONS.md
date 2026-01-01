# Testing Job Cancellation & Uncancel Notifications

## Quick Test Steps

Both **Cancel Job** and **Uncancel Job** actions trigger notifications to assigned drivers.

### 1. Check if Queue is Running
The notification listener runs asynchronously via Laravel's queue system. You need to have a queue worker running:

```bash
php artisan queue:work
```

**OR** for testing, you can temporarily make it synchronous (see below).

### 2. Verify Job Has Assigned Drivers
The notification only sends to drivers who have logs assigned to the job. Check:

```sql
SELECT u.id, u.name, u.email, u.notification_address, ul.id as log_id
FROM users u
INNER JOIN user_logs ul ON ul.user_id = u.id
WHERE ul.pilot_car_job_id = YOUR_JOB_ID;
```

### 3. Check Laravel Logs
After canceling a job, check the log file:

```bash
tail -f storage/logs/laravel.log
```

Or on Windows PowerShell:
```powershell
Get-Content storage/logs/laravel.log -Tail 50 -Wait
```

Look for these log entries:

**When Canceling:**
- `CancelJob: Job canceled, firing event`
- `CancelJob: Event fired`
- `NotifyAssignedDriversOfJobCancellation: Event received`
- `NotifyAssignedDriversOfJobCancellation: Found drivers`
- `SendUserNotification: Preparing to send`
- `SendUserNotification: Email sent successfully`

**When Uncanceling:**
- `ShowPilotCarJob: Job uncanceled, firing event`
- `ShowPilotCarJob: Event fired`
- `NotifyAssignedDriversOfJobUncancellation: Event received`
- `NotifyAssignedDriversOfJobUncancellation: Found drivers`
- `SendUserNotification: Preparing to send`
- `SendUserNotification: Email sent successfully`

### 4. Make Listener Synchronous (For Testing Only)

If you want to test without running a queue worker, temporarily modify:

**File:** `app/Listeners/NotifyAssignedDriversOfJobCancellation.php`

```php
// Remove ShouldQueue for synchronous testing
class NotifyAssignedDriversOfJobCancellation // implements ShouldQueue
{
    // Remove use InteractsWithQueue;
}
```

**Remember to restore it after testing!**

### 5. Test with a Job That Has Logs

1. Create a job
2. Assign a driver and create a log entry for that job
3. **Cancel the job** - Check logs and verify notification was sent
4. **Uncancel the job** - Check logs and verify notification was sent
5. Repeat steps 3-4 to test alternating cancel/uncancel

### 6. Verify Email/SMS Delivery

- Check the recipient's email inbox (if using email)
- Check SMS gateway (if using SMS gateway address like `2074168659@mms.uscc.net`)
- Check Brevo dashboard for sent emails

## Common Issues

### No Drivers Found
If you see "No drivers found for job" in logs:
- The job has no logs assigned
- Create a log entry with a driver assigned first

### Queue Not Processing
- Make sure `php artisan queue:work` is running
- Or temporarily make listener synchronous for testing

### No Recipient Address
If you see "No valid recipient address":
- User needs either `email` or `notification_address` set
- Check user record in database

### Event Not Firing
- Check that `event(new JobWasCanceled(...))` is being called
- Verify event is registered in `AppServiceProvider`
