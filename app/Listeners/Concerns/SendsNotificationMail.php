<?php

namespace App\Listeners\Concerns;

use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Resilient best-effort mail sending for queued notification listeners.
 *
 * A notification is best-effort: a misconfigured mail transport (e.g. prod
 * pointed at a mailer with no valid transport) or one bad recipient must NOT
 * dead-letter the whole queued job — that was silently piling up failed_jobs
 * and dropping driver/invoice notifications (TASK-338). This mirrors the
 * try → log → log-mailer fallback already used in App\Actions\SendUserNotification.
 */
trait SendsNotificationMail
{
    /**
     * Send a mailable to one address without ever throwing. Returns true on a
     * real send, false if it fell back to logging (or failed entirely).
     */
    protected function mailSafely(string $address, Mailable $mailable): bool
    {
        try {
            Mail::to($address)->send($mailable);

            return true;
        } catch (Throwable $e) {
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
}
