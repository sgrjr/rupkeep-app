<?php

namespace App\Listeners\Concerns;

use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Mail sending for notification listeners that retries before it gives up.
 *
 * A notification is best-effort at the end, not at the start. The first
 * version of this (TASK-338) caught every failure, wrote the message to the
 * log mailer and returned normally -- which stopped queued jobs from
 * dead-lettering, but also meant a Brevo blip or rate limit dropped the
 * driver's assignment text for good, with a warning in laravel.log that
 * nobody reads (TASK-465).
 *
 * Now, when the listener is running as a queued job with attempts left, a
 * failed send is thrown back to the worker, which releases the job with the
 * listener's backoff and tries again. Only on the final attempt -- or when
 * there is no queue to retry for us (a command, a controller, the sync
 * driver) -- does it fall back to the log mailer as before.
 */
trait SendsNotificationMail
{
    /**
     * Send a mailable to one address. Returns true on a real send, false if
     * it fell back to logging. Throws only when the queue will retry.
     */
    protected function mailSafely(string $address, Mailable $mailable): bool
    {
        try {
            Mail::to($address)->send($mailable);

            return true;
        } catch (Throwable $e) {
            if ($this->mailShouldRetry()) {
                Log::warning('Notification email failed; the queue will retry it', [
                    'listener' => static::class,
                    'address' => $address,
                    'mailer' => config('mail.default'),
                    'attempt' => $this->job->attempts(),
                    'max_tries' => $this->job->maxTries(),
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                ]);

                throw $e;
            }

            Log::warning('Notification email failed; attempting log-mailer fallback', [
                'listener' => static::class,
                'address' => $address,
                'mailer' => config('mail.default'),
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            // Record the notification via the log mailer so it isn't lost, as
            // long as the default mailer wasn't already 'log'.
            if (config('mail.default') !== 'log') {
                try {
                    Mail::mailer('log')->to($address)->send($mailable);
                } catch (Throwable $inner) {
                    Log::error('Notification email failed even via log mailer', [
                        'listener' => static::class,
                        'address' => $address,
                        'error' => $inner->getMessage(),
                    ]);
                }
            }

            return false;
        }
    }

    /**
     * Whether a failed send should go back to the queue: only when this is
     * running as a queued job, on a real queue, with attempts left. The sync
     * driver would surface the throw to whoever fired the event, and a
     * synchronous caller has nobody to retry for it.
     */
    protected function mailShouldRetry(): bool
    {
        $queueJob = property_exists($this, 'job') ? $this->job : null;

        if (! $queueJob instanceof QueueJob || $queueJob instanceof SyncJob) {
            return false;
        }

        $maxTries = $queueJob->maxTries() ?? ($this->tries ?? 1);

        return $queueJob->attempts() < $maxTries;
    }
}
